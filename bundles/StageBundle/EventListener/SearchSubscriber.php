<?php

declare(strict_types=1);

namespace Mautic\StageBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event as MauticEvents;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\EventListener\GlobalSearchTrait;
use Mautic\StageBundle\Model\StageModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class SearchSubscriber implements EventSubscriberInterface
{
    use GlobalSearchTrait;

    public function __construct(
        private StageModel $stageModel,
        private CorePermissions $security,
        private Environment $twig
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::GLOBAL_SEARCH      => ['onGlobalSearch', 0],
            CoreEvents::BUILD_COMMAND_LIST => ['onBuildCommandList', 0],
        ];
    }

    public function onGlobalSearch(MauticEvents\GlobalSearchEvent $event): void
    {
        if (!$this->security->isGranted('stage:stages:view')) {
            return;
        }

        $searchString = $event->getSearchString();
        if (empty($searchString)) {
            return;
        }

        $items = $this->stageModel->getEntities([
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
            'mautic.stage.actions.header.index',
            '@MauticStage/SubscribedEvents/Search/global.html.twig',
            ['canEdit' => $this->security->isGranted('stage:stages:edit')]
        );
    }

    public function onBuildCommandList(MauticEvents\CommandListEvent $event): void
    {
        if ($this->security->isGranted('stage:stages:view')) {
            $event->addCommands(
                'mautic.stage.actions.header.index',
                $this->stageModel->getCommandList()
            );
        }
    }
}
