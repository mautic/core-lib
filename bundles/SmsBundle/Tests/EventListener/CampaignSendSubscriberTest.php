<?php

namespace Mautic\SmsBundle\Tests\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Illuminate\Contracts\Translation\Translator;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\ActionAccessor;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadEventLog;
use Mautic\SmsBundle\Entity\Sms;
use Mautic\SmsBundle\EventListener\CampaignSendSubscriber;
use Mautic\SmsBundle\Model\SmsModel;
use Mautic\SmsBundle\Sms\TransportChain;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignSendSubscriberTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var mixed[]
     */
    private $args;

    private MockObject&SmsModel $smsModel;

    private MockObject&TransportChain $transportChain;

    private MockObject&TranslatorInterface $translator;

    private CampaignSendSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->smsModel       = $this->createMock(SmsModel::class);
        $this->transportChain = $this->createMock(TransportChain::class);
        $this->translator     = $this->createMock(TranslatorInterface::class);
        $this->subscriber     = new CampaignSendSubscriber($this->smsModel, $this->transportChain, $this->translator);

        $lead = new Lead();
        $lead->setId(1);
        $this->args = [
            'lead'            => $lead,
            'event'           => [
                'type'       => 'sms.send_text_sms',
                'properties' => ['sms' => 1],
            ],
            'eventDetails'    => [],
            'systemTriggered' => true,
            'eventSettings'   => [],
        ];
    }

    public function testSendDeletedSms(): void
    {
        $this->smsModel->expects(self::once())->method('getEntity')->willReturn(null);

        $event = new CampaignExecutionEvent($this->args, false, null);

        $this->subscriber->onCampaignTriggerAction($event);
        self::assertTrue((bool) $event->getResult()['failed']);
        self::assertSame('mautic.sms.campaign.failed.missing_entity', $event->getResult()['reason']);
    }

    public function testSendUnpublishedSms(): void
    {
        $lead = new Lead();
        $lead->setId(1);
        $sms = new Sms();
        $sms->setIsPublished(false);
        $this->smsModel->expects(self::once())->method('getEntity')->willReturn($sms);

        $event = new CampaignExecutionEvent($this->args, false, null);

        $this->subscriber->onCampaignTriggerAction($event);
        self::assertTrue((bool) $event->getResult()['failed']);
        self::assertSame('mautic.sms.campaign.failed.unpublished', $event->getResult()['reason']);
    }

    public function testOnCampaignTriggerBatchAction(): void
    {
        $sms = $this->createMock(Sms::class);
        $sms->expects($this->any())
            ->method('getId')
            ->willReturn(1);

        // Partial mock, mocks just getRepository
        $smsModel = $this->getMockBuilder(SmsModel::class)
            ->disableOriginalConstructor()
            ->setMethods(['sendSms', 'getEntity'])
            ->getMock();

        $smsModel->method('sendSms')
            ->willReturn(true);
        $smsModel->method('getEntity')
            ->willReturn($sms);

        $transportChain = $this->createMock(TransportChain::class);

        $event    = new Event();
        $campaign = new class() extends Campaign {
            public function getId()
            {
                return 111;
            }
        };
        $leadLog = new class() extends LeadEventLog {
            public function getId()
            {
                return 456;
            }
        };
        $contact = new class() extends Lead {
            public function getId()
            {
                return 789;
            }
        };

        $leadLog->setLead($contact);

        $translator = new class() extends Translator {
            public function __construct()
            {
            }
        };

        $subscriber = new CampaignSendSubscriber(
            $smsModel,
            $transportChain,
            $translator
        );

        $event->setProperties(['sms' => 1]);
        $event->setCampaign($campaign);

        $pendingEvent = new PendingEvent(new ActionAccessor([]), $event, new ArrayCollection([$leadLog->getId() => $leadLog]));

        $this->assertCount(1, $pendingEvent->getContacts());
        $subscriber->onCampaignTriggerBatchAction($pendingEvent);
    }
}
