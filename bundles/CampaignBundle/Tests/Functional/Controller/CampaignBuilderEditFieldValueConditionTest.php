<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\Tests\Functional\Controller;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\CreateTestEntitiesTrait;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class CampaignBuilderEditFieldValueConditionTest extends MauticMysqlTestCase
{
    use CampaignControllerTrait;
    use CreateTestEntitiesTrait;

    public function testCampaignBuilderFormForFieldValueConditionForInOperator(): void
    {
        $campaign = $this->setupCampaignWithLeadList();
        $version  = $campaign->getVersion();

        $campaignCondition = $this->setupCampaignEvent($campaign);

        $campaignAction = new Event();
        $campaignAction->setCampaign($campaign);
        $campaignAction->setParent($campaignCondition);
        $campaignAction->setName('Send Email 1');
        $campaignAction->setType('email.send');
        $campaignAction->setEventType('action');
        $campaignAction->setProperties([]);
        $this->em->persist($campaignAction);

        $this->em->flush();
        $this->em->clear();

        $conditionArray = $campaignCondition->convertToArray();
        unset($conditionArray['campaign'], $conditionArray['children'], $conditionArray['log'], $conditionArray['changes']);

        $campaignArray = $campaignAction->convertToArray();
        unset($campaignArray['campaign'], $campaignArray['children'], $campaignArray['log'], $campaignArray['changes'], $campaignArray['parent']);

        $modifiedEvents = [
            $campaignCondition->getId() => $conditionArray,
            $campaignAction->getId()    => $campaignArray,
        ];

        $payload = [
            'modifiedEvents' => json_encode($modifiedEvents),
        ];

        $this->client->request(Request::METHOD_POST, sprintf('/s/campaigns/events/edit/%s', $campaignCondition->getId()), $payload, [], $this->createAjaxHeaders());
        Assert::assertTrue($this->client->getResponse()->isOk());

        // version should be incremented as campaign's "modified by user" is updated
        $this->refreshAndSubmitForm($campaign, ++$version);
    }

    public function testSwitchScalarValueToAnArrayOne(): void
    {
        $campaign = $this->setupCampaignWithLeadList();

        $campaignCondition = $this->setupCampaignEvent($campaign);
        $campaignCondition->setProperties([
            'field'    => 'country',
            'operator' => '=',
            'value'    => 'Afghanistan',
        ]);

        $this->em->flush();
        $this->em->clear();

        // request for the update form
        $this->client->request(Request::METHOD_POST, sprintf('/s/campaigns/events/edit/%s', $campaignCondition->getId()), [], [], $this->createAjaxHeaders());
        $response = $this->client->getResponse();
        Assert::assertTrue($response->isOk());
        Assert::assertJson($response->getContent());

        // transform the update form and change the values
        $content      = $response->getContent();
        $responseData = json_decode($content, true);
        $content      = $responseData['newContent'];
        $crawler      = new Crawler($content, $this->client->getInternalRequest()->getUri());
        $formCrawler  = $crawler->filter('form');
        $this->assertSame(1, $formCrawler->count());
        $form                                               = $formCrawler->form();
        $payload                                            = $form->getPhpValues();
        $payload['campaignevent']['properties']['operator'] = 'in';
        $payload['campaignevent']['properties']['value']    = ['Albania'];

        // submit the update form and verify we no longer get HTTP 500
        $this->client->request($form->getMethod(), $form->getUri(), $payload, $form->getPhpFiles(), $this->createAjaxHeaders());
        $response = $this->client->getResponse();
        Assert::assertTrue($response->isOk());

        // assert operator and value has been changed properly
        Assert::assertJson($response->getContent());
        $data       = json_decode($response->getContent(), true);
        $properties = $data['modifiedEvents'][$campaignCondition->getId()]['properties'] ?? null;
        Assert::assertIsArray($properties);
        Assert::assertSame('in', $properties['operator'] ?? null);
        Assert::assertSame(['Albania'], $properties['value'] ?? null);
    }

    private function setupCampaignWithLeadList(): Campaign
    {
        $leadList = new LeadList();
        $leadList->setName('Test list');
        $leadList->setPublicName('Test list');
        $leadList->setAlias('test-list');
        $this->em->persist($leadList);

        $campaign = new Campaign();
        $campaign->setName('Test campaign');
        $campaign->addList($leadList);
        $this->em->persist($campaign);

        $lead = new Lead();
        $lead->setFirstname('Test Lead');
        $this->em->persist($lead);

        return $campaign;
    }

    private function setupCampaignEvent(Campaign $campaign): Event
    {
        $campaignCondition = new Event();
        $campaignCondition->setCampaign($campaign);
        $campaignCondition->setName('Check for country');
        $campaignCondition->setType('lead.field_value');
        $campaignCondition->setEventType('condition');
        $campaignCondition->setProperties([
            'field'    => 'country',
            'operator' => 'in',
            'value'    => ['Afghanistan'],
        ]);
        $this->em->persist($campaignCondition);

        return $campaignCondition;
    }
}
