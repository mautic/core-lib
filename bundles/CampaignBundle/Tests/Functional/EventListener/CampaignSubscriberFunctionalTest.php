<?php

declare(strict_types=1);

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignLead;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Model\FieldModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\EventListener\CampaignSubscriber;

final class CampaignSubscriberFunctionalTest extends MauticMysqlTestCase
{
    private CampaignSubscriber $campaignSubscriber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campaignSubscriber = self::$container->get('mautic.lead.campaignbundle.subscriber');
    }

    public function testCampaignTriggerConditionLeadIsInCampaign(): void
    {
        $field = ['type' => 'text', 'alias' => 'test_text_field'];
        $this->makeField($field);
        $lead = $this->createTestLead($field);

        // Create a campaign.
        $campaign = new Campaign();
        $campaign->setName('My campaign');
        $campaign->setIsPublished(true);
        $campaign->setDateAdded(new \DateTime());
        $this->em->persist($campaign);
        $this->em->flush();

        $campaignLead = new CampaignLead();
        $campaignLead->setCampaign($campaign);
        $campaignLead->setLead($lead);
        $campaignLead->setDateAdded(new \DateTime());
        $this->em->persist($campaignLead);

        // Create an event for campaign.
        $entityEvent = new Event();
        $entityEvent->setCampaign($campaign);
        $entityEvent->setName('Test Condition');
        $entityEvent->setEventType('condition');
        $entityEvent->setType('lead.campaigns');
        $entityEvent->setProperties([
            'campaigns' => [$campaign->getId()],
        ]);

        $this->em->persist($entityEvent);
        $this->em->flush();

        $eventProperties = [
            'lead'            => $lead,
            'event'           => $entityEvent,
            'eventDetails'    => [],
            'systemTriggered' => false,
            'eventSettings'   => [],
        ];

        $campaignExecutionEvent = new CampaignExecutionEvent($eventProperties, false);
        $result                 = $this->campaignSubscriber->onCampaignTriggerCondition($campaignExecutionEvent);
        $this->assertInstanceOf(CampaignExecutionEvent::class, $result);
        $this->assertTrue($result->getResult());
    }

    /**
     * @param mixed[] $fieldDetails
     */
    private function makeField(array $fieldDetails): void
    {
        // Create a field and add it to the lead object.
        $field = new LeadField();
        $field->setLabel($fieldDetails['alias']);
        $field->setType($fieldDetails['type']);
        $field->setObject('lead');
        $field->setGroup('core');
        $field->setAlias($fieldDetails['alias']);

        /** @var FieldModel $fieldModel */
        $fieldModel = self::$container->get('mautic.lead.model.field');
        $fieldModel->saveEntity($field);
    }

    /**
     * @param mixed[] $fieldDetails
     */
    private function createTestLead(array $fieldDetails): Lead
    {
        // Create a contact
        $lead = new Lead();
        $lead->setFirstname('Test');
        $lead->setFields([
            'core' => [
                $fieldDetails['alias'] => [
                    'value' => '',
                    'type'  => $fieldDetails['type'],
                ],
            ],
        ]);

        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }
}
