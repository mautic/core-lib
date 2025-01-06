<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\EventListener;

use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event as MauticEvents;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\EventListener\GlobalSearchTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class SearchSubscriber implements EventSubscriberInterface
{
    use GlobalSearchTrait;

    public function __construct(
        private CampaignModel $campaignModel,
        private CorePermissions $security,
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
        if ($this->security->isGranted('campaign:campaigns:view')) {
            $str = $event->getSearchString();
            if (empty($str)) {
                return;
            }

            $campaigns = $this->campaignModel->getEntities(
                [
                    'filter'           => $str,
                    'start'            => 0,
                    'limit'            => MauticEvents\GlobalSearchEvent::RESULTS_LIMIT,
                    'ignore_paginator' => true,
                    'with_total_count' => true,
                ]);

            $this->addGlobalSearchResults(
                $this->twig,
                $event,
                $campaigns,
                'mautic.campaign.campaigns',
                '@MauticCampaign/SubscribedEvents/Search/global.html.twig'
            );
        }
    }

    public function onBuildCommandList(MauticEvents\CommandListEvent $event): void
    {
        $security = $this->security;
        if ($security->isGranted('campaign:campaigns:view')) {
            $event->addCommands(
                'mautic.campaign.campaigns',
                $this->campaignModel->getCommandList()
            );
        }
    }
}
