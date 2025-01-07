<?php

declare(strict_types=1);

namespace Mautic\SmsBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event as MauticEvents;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\EventListener\GlobalSearchTrait;
use Mautic\SmsBundle\Model\SmsModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class SearchSubscriber implements EventSubscriberInterface
{
    use GlobalSearchTrait;

    public function __construct(
        private SmsModel $model,
        private CorePermissions $security,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::GLOBAL_SEARCH => ['onGlobalSearch', 0],
        ];
    }

    public function onGlobalSearch(MauticEvents\GlobalSearchEvent $event): void
    {
        if (!$this->security->isGranted('sms:smses:view')) {
            return;
        }

        $searchString = $event->getSearchString();
        if (empty($searchString)) {
            return;
        }

        $items = $this->model->getEntities([
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
            'mautic.sms.smses.header',
            '@MauticSms/SubscribedEvents/Search/global.html.twig',
            ['canEdit' => $this->security->isGranted('sms:smses:edit')]
        );
    }
}
