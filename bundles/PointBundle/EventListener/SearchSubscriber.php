<?php

declare(strict_types=1);

namespace Mautic\PointBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event as MauticEvents;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\EventListener\GlobalSearchTrait;
use Mautic\PointBundle\Model\PointModel;
use Mautic\PointBundle\Model\TriggerModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class SearchSubscriber implements EventSubscriberInterface
{
    use GlobalSearchTrait;

    public function __construct(
        private PointModel $pointModel,
        private TriggerModel $pointTriggerModel,
        private CorePermissions $security,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::GLOBAL_SEARCH => [
                ['onGlobalSearchPointActions', 0],
                ['onGlobalSearchPointTriggers', 0],
            ],
            CoreEvents::BUILD_COMMAND_LIST => ['onBuildCommandList', 0],
        ];
    }

    public function onGlobalSearchPointActions(MauticEvents\GlobalSearchEvent $event)
    {
        if (!$this->security->isGranted('point:points:view')) {
            return;
        }

        $searchString = $event->getSearchString();
        if (empty($searchString)) {
            return;
        }

        $items = $this->pointModel->getEntities([
            'filter'           => $searchString,
            'start'            => 0,
            'limit'            => MauticEvents\GlobalSearchEvent::RESULTS_LIMIT,
            'ignore_paginator' => true,
            'with_total_count' => true,
        ]);

        $this->addGlobalSearchResults(
            $this->twig,
            $event,
            $items,
            'mautic.point.actions.header.index',
            '@MauticPoint/SubscribedEvents/Search/global_point.html.twig',
            ['canEdit' => $this->security->isGranted('point:points:edit')]
        );
    }

    public function onGlobalSearchPointTriggers(MauticEvents\GlobalSearchEvent $event): void
    {
        if (!$this->security->isGranted('point:triggers:view')) {
            return;
        }

        $searchString = $event->getSearchString();
        if (empty($searchString)) {
            return;
        }

        $items = $this->pointTriggerModel->getEntities([
            'filter'           => $searchString,
            'start'            => 0,
            'limit'            => MauticEvents\GlobalSearchEvent::RESULTS_LIMIT,
            'ignore_paginator' => true,
            'with_total_count' => true,
        ]);

        $this->addGlobalSearchResults(
            $this->twig,
            $event,
            $items,
            'mautic.point.trigger.header.index',
            '@MauticPoint/SubscribedEvents/Search/global_trigger.html.twig',
            ['canEdit' => $this->security->isGranted('point:triggers:edit')]
        );
    }

    public function onBuildCommandList(MauticEvents\CommandListEvent $event): void
    {
        $security = $this->security;
        if ($security->isGranted('point:points:view')) {
            $event->addCommands(
                'mautic.point.actions.header.index',
                $this->pointModel->getCommandList()
            );
        }
    }
}
