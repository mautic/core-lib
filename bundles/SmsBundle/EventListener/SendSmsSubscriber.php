<?php

declare(strict_types=1);

namespace Mautic\SmsBundle\EventListener;

use Mautic\ChannelBundle\Entity\MessageQueue;
use Mautic\ChannelBundle\Model\MessageQueueModel;
use Mautic\LeadBundle\Entity\DoNotContactRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\SmsBundle\Event\DncEvent;
use Mautic\SmsBundle\Event\FilterEvent;
use Mautic\SmsBundle\Event\QueueEvent;
use Mautic\SmsBundle\SmsEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SendSmsSubscriber implements EventSubscriberInterface
{
    /**
     * @var DoNotContactRepository
     */
    private $dncRepo;

    /**
     * @var MessageQueueModel
     */
    private $messageQueueModel;

    public function __construct(DoNotContactRepository $dncRepo, MessageQueueModel $messageQueueModel)
    {
        $this->dncRepo           = $dncRepo;
        $this->messageQueueModel = $messageQueueModel;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function getSubscribedEvents()
    {
        return [
            SmsEvents::DNC_FILTER_CONTACTS_ON_SEND   => ['dncFilter', 0],
            SmsEvents::QUEUE_FILTER_CONTACTS_ON_SEND => ['queueFilter', 0],
            SmsEvents::FILTER_CONTACTS_ON_SEND       => ['genericFilter' => 0],
        ];
    }

    public function dncFilter(DncEvent $event): void
    {
        $dnc = $this->dncRepo->getChannelList('sms', array_keys($event->getContacts()));

        if (empty($dnc)) {
            return;
        }

        $event->removeContacts(array_keys($dnc));
    }

    public function queueFilter(QueueEvent $event): void
    {
        $options         = $event->getOptions();
        $messageQueue    = $options['resend_message_queue'] ?? null;
        $channel         = $options['channel'] ?? null;
        $campaignEventId = (is_array($channel) && 'campaign.event' === $channel[0] && !empty($channel[1])) ? $channel[1] : null;

        $queuedContacts = $this->messageQueueModel->processFrequencyRules(
            $contacts,
            'sms',
            $options['sms_id'],
            $campaignEventId,
            3,
            MessageQueue::PRIORITY_NORMAL,
            $messageQueue,
            'sms_message_stats'
        );

        $event->queueContacts($queuedContacts);
    }

    public function genericFilter(FilterEvent $event): void
    {
        $contactsWithoutNumbers = array_filter($event->getContacts(), function (Lead $contact) {
            return empty($contact->getLeadPhoneNumber());
        });

        $event->removeContacts(array_map(function (Lead $contact) {
            return $contact->getId();
        }, $contactsWithoutNumbers));
    }
}
