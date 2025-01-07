<?php

declare(strict_types=1);

namespace Mautic\NotificationBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event as MauticEvents;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\EventListener\GlobalSearchTrait;
use Mautic\NotificationBundle\Model\NotificationModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class SearchSubscriber implements EventSubscriberInterface
{
    use GlobalSearchTrait;

    public function __construct(
        private NotificationModel $model,
        private CorePermissions $security,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::GLOBAL_SEARCH => [
                ['onGlobalSearchWebNotification', 0],
                ['onGlobalSearchMobileNotification', 0],
            ],
        ];
    }

    public function onGlobalSearchWebNotification(MauticEvents\GlobalSearchEvent $event): void
    {
        if (!$this->security->isGranted('notification:notifications:view')) {
            return;
        }

        $searchString = $event->getSearchString();
        if (empty($searchString)) {
            return;
        }

        $filter = [
            'string' => $searchString,
            'where'  => [
                [
                    'expr' => 'eq',
                    'col'  => 'mobile',
                    'val'  => 0,
                ],
            ],
        ];

        $items = $this->model->getEntities([
            'filter'           => $filter,
            'start'            => 0,
            'limit'            => MauticEvents\GlobalSearchEvent::RESULTS_LIMIT,
            'ignore_paginator' => true,
            'with_total_count' => true,
        ]);

        $this->addGlobalSearchResults(
            $this->twig,
            $event,
            $items,
            'mautic.notification.notification.header',
            '@MauticNotification/SubscribedEvents/Search/global-web.html.twig',
            ['canEdit' => $this->security->isGranted('notification:notifications:edit')]
        );
    }

    public function onGlobalSearchMobileNotification(MauticEvents\GlobalSearchEvent $event): void
    {
        if (!$this->security->isGranted('notification:notifications:view')) {
            return;
        }

        $searchString = $event->getSearchString();
        if (empty($searchString)) {
            return;
        }

        $filter = [
            'string' => $searchString,
            'where'  => [
                [
                    'expr' => 'eq',
                    'col'  => 'mobile',
                    'val'  => 1,
                ],
            ],
        ];

        $items = $this->model->getEntities([
            'filter'           => $filter,
            'start'            => 0,
            'limit'            => MauticEvents\GlobalSearchEvent::RESULTS_LIMIT,
            'ignore_paginator' => true,
            'with_total_count' => true,
        ]);

        $this->addGlobalSearchResults(
            $this->twig,
            $event,
            $items,
            'mautic.notification.mobile_notification.header',
            '@MauticNotification/SubscribedEvents/Search/global-mobile.html.twig',
            ['canEdit' => $this->security->isGranted('notification:notifications:edit')]
        );
    }
}
