<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Functional\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Model\FieldModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadDevice;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\EventListener\CampaignSubscriber;
use PHPUnit\Framework\Assert;

class CampaignSubscriberTest extends MauticMysqlTestCase
{
    private CampaignSubscriber $campaignSubscriber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campaignSubscriber = $this->getContainer()->get(CampaignSubscriber::class);
    }

    public function testOnCampaignTriggerConditionReturnsCorrectResultForLeadDeviceContext(): void
    {
        $lead = new Lead();
        $lead->setFirstname('Test');
        $this->em->persist($lead);

        $now         = new \DateTime();
        $leadDevice1 = new LeadDevice();
        $leadDevice1->setLead($lead);
        $leadDevice1->setDateAdded($now);
        $leadDevice1->setDevice('desktop');
        $leadDevice1->setDeviceBrand('AP');
        $leadDevice1->setDeviceModel('MacBook');
        $leadDevice1->setDeviceOsName('Mac');
        $this->em->persist($leadDevice1);

        $campaign = new Campaign();
        $campaign->setName('My campaign');
        $campaign->setIsPublished(true);
        $this->em->persist($campaign);

        $entityEvent = new Event();
        $entityEvent->setCampaign($campaign);
        $entityEvent->setName('Test Condition');
        $entityEvent->setEventType('condition');
        $entityEvent->setType('lead.device');
        $entityEvent->setProperties([
            'device_type' => [
                'desktop',
                'mobile',
                'tablet',
            ],
            'device_brand' => [
                'AP',
                'NOKIA',
                'SAMSUNG',
            ],
            'device_os' => [
                'Chrome OS',
                'Mac',
                'iOS',
            ],
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

        $campaignExecutionEvent = new CampaignExecutionEvent($eventProperties, false); // @phpstan-ignore new.deprecated
        $result                 = $this->campaignSubscriber->onCampaignTriggerCondition($campaignExecutionEvent);
        Assert::assertInstanceOf(CampaignExecutionEvent::class, $result); // @phpstan-ignore classConstant.deprecatedClass
        Assert::assertTrue($result->getResult());
    }

    public function dataEventProperties(): iterable
    {
        yield [
            ['type' => 'datetime', 'alias' => 'date_field'],
            ['field' => 'date_field', 'operator' => 'empty'],
            true,
        ];
        yield [
            ['type' => 'datetime', 'alias' => 'date_field_another'],
            ['field' => 'date_field_another', 'operator' => '!empty'],
            false,
        ];
        yield [
            ['type' => 'text', 'alias' => 'test_text_field'],
            ['field' => 'firstname', 'operator' => 'empty'],
            false,
        ];
    }

    /**
     * @dataProvider dataEventProperties
     */
    public function testOnCampaignTriggerConditionReturnsCorrectResultsForLeadFieldContext(array $field, array $properties, bool $expected): void
    {
        $this->makeField($field);
        $lead = $this->createTestLead($field);

        // Create a campaign.
        $campaign = new Campaign();
        $campaign->setName('My campaign');
        $campaign->setIsPublished(true);
        $this->em->persist($campaign);

        // Create an event for campaign.
        $entityEvent = new Event();
        $entityEvent->setCampaign($campaign);
        $entityEvent->setName('Test Condition');
        $entityEvent->setEventType('condition');
        $entityEvent->setType('lead.field_value');
        $entityEvent->setProperties($properties);

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
        $this->assertSame($expected, $result->getResult());
    }

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
        $fieldModel = $this->container->get('mautic.lead.model.field');
        $fieldModel->saveEntity($field);
    }

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
