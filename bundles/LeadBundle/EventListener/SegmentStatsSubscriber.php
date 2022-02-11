<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\EventListener;

use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Event\GetStatDataEvent;
use Mautic\LeadBundle\LeadEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SegmentStatsSubscriber implements EventSubscriberInterface
{
    /**
     * @var LeadListRepository
     */
    private $leadListRepository;

    public function __construct(LeadListRepository $leadListRepository)
    {
        $this->leadListRepository = $leadListRepository;
    }

    /**
     * @return mixed[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LEAD_LIST_STAT   => ['getStatsLeadEvents', 0],
        ];
    }

    public function getStatsLeadEvents(GetStatDataEvent $event): void
    {
        $result = array_merge(
            $this->leadListRepository->getCampaignEntryPoints(),
            $this->leadListRepository->getEmailIncludeExcludeList(),
            $this->leadListRepository->getCampaignChangeSegmentAction(),
            $this->leadListRepository->getFilterSegmentsAction(),
            $this->leadListRepository->getLeadListLeads(),
            $this->leadListRepository->getNotificationIncludedList(),
            $this->leadListRepository->getRandomSegment(),
            $this->leadListRepository->getRandomSegmentContacts(),
            $this->leadListRepository->getSMSIncludedList(),
            $this->leadListRepository->getFormAction()
        );

        $allSegments = $this->leadListRepository->getAllSegments();

        $stats = array_map(function ($data) use ($result) {
            if (array_filter($result, function ($res) use ($data) {
                return $res['item_id'] === $data['item_id'];
            })) {
                $data['is_used'] = 1;
            }

            return $data;
        }, $allSegments);

        $event->addResult($stats);
    }
}
