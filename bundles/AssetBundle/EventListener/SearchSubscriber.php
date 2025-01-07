<?php

declare(strict_types=1);

namespace Mautic\AssetBundle\EventListener;

use Mautic\AssetBundle\Model\AssetModel;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event as MauticEvents;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\EventListener\GlobalSearchTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class SearchSubscriber implements EventSubscriberInterface
{
    use GlobalSearchTrait;

    public function __construct(
        private AssetModel $assetModel,
        private CorePermissions $security,
        private UserHelper $userHelper,
        private Environment $twig,
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
        $str = $event->getSearchString();
        if (empty($str)) {
            return;
        }

        $permissions = $this->security->isGranted(
            ['asset:assets:viewown', 'asset:assets:viewother'],
            'RETURN_ARRAY'
        );

        if ($permissions['asset:assets:viewown'] || $permissions['asset:assets:viewother']) {
            $filter = ['string' => $str, 'force' => []];

            if (!$permissions['asset:assets:viewother']) {
                $filter['force'][] = [
                    'column' => 'a.createdBy',
                    'expr'   => 'eq',
                    'value'  => $this->userHelper->getUser()->getId(),
                ];
            }

            $assets = $this->assetModel->getEntities(
                [
                    'filter'           => $filter,
                    'start'            => 0,
                    'limit'            => MauticEvents\GlobalSearchEvent::RESULTS_LIMIT,
                    'ignore_paginator' => true,
                    'with_total_count' => true,
                ]);

            $this->addGlobalSearchResults(
                $this->twig,
                $event,
                $assets,
                'mautic.asset.assets',
                '@MauticAsset/SubscribedEvents/Search/global.html.twig'
            );
        }
    }

    public function onBuildCommandList(MauticEvents\CommandListEvent $event): void
    {
        if ($this->security->isGranted(['asset:assets:viewown', 'asset:assets:viewother'], 'MATCH_ONE')) {
            $event->addCommands(
                'mautic.asset.assets',
                $this->assetModel->getCommandList()
            );
        }
    }
}
