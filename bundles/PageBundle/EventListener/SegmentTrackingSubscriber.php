<?php

declare(strict_types=1);

namespace Mautic\PageBundle\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\PageBundle\Event\UrlTokenReplaceEvent;
use Mautic\PageBundle\PageEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Appends contact segment IDs to tracking URLs for third-party integrations like VWO.
 */
class SegmentTrackingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CoreParametersHelper $coreParametersHelper,
        private LeadListRepository $leadListRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PageEvents::ON_URL_TOKEN_REPLACE => ['onUrlTokenReplace', -100],
        ];
    }

    /**
     * Append segment IDs to the final redirect URL.
     */
    public function onUrlTokenReplace(UrlTokenReplaceEvent $event): void
    {
        $leadData = $event->getLead();

        if (!$this->coreParametersHelper->get('append_segment_id_tracking_url') || !$leadData) {
            return;
        }

        $contactId = $leadData instanceof Lead ? $leadData->getId() : $leadData;
        if (!$contactId) {
            return;
        }

        $segmentIds = $this->leadListRepository->getContactSegmentIds((string) $contactId);
        if ($segmentIds) {
            $this->appendSegmentIdsToUrl($event, $segmentIds);
        }
    }

    /**
     * Append segment IDs as a query parameter to the URL in the event.
     *
     * @param UrlTokenReplaceEvent $event      The event containing the URL
     * @param int[]                $segmentIds Array of segment IDs to append
     */
    private function appendSegmentIdsToUrl(UrlTokenReplaceEvent $event, array $segmentIds): void
    {
        $url             = $event->getContent();
        $segmentIdsParam = 'segment_ids='.implode(',', $segmentIds);

        $fragment = '';
        if (str_contains($url, '#')) {
            $parts    = explode('#', $url, 2);
            $url      = $parts[0];
            $fragment = '#'.$parts[1];
        }

        $separator = (str_contains($url, '?')) ? '&' : '?';
        $url .= $separator.$segmentIdsParam.$fragment;

        $event->setContent($url);
    }
}
