<?php

declare(strict_types=1);

namespace Mautic\DynamicContentBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\GlobalSearchEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\DynamicContentBundle\Model\DynamicContentModel;
use Mautic\LeadBundle\EventListener\GlobalSearchTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SearchSubscriber implements EventSubscriberInterface
{
    use GlobalSearchTrait;

    public function __construct(
        private DynamicContentModel $dynamicContentModel,
        private CorePermissions $security,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::GLOBAL_SEARCH  => ['onGlobalSearch', 0],
        ];
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function onGlobalSearch(GlobalSearchEvent $event): void
    {
        $str = $event->getSearchString();
        if (empty($str)) {
            return;
        }

        $filter      = ['string' => $str, 'force' => ''];
        $permissions = $this->security->isGranted(
            ['dynamiccontent:dynamiccontents:viewown', 'dynamiccontent:dynamiccontents:viewother'],
            'RETURN_ARRAY'
        );

        if ($permissions['dynamiccontent:dynamiccontents:viewown'] || $permissions['dynamiccontent:dynamiccontents:viewother']) {
            $results = $this->dynamicContentModel->getEntities([
                'start'             => 0,
                'limit'             => GlobalSearchEvent::RESULTS_LIMIT,
                'filter'            => $filter,
                'with_total_count'  => true,
                'ignore_paginator'  => true,
            ]);

            $this->addGlobalSearchResults(
                $this->twig,
                $event,
                $results,
                'mautic.dynamicContent.dynamicContent',
                '@MauticDynamicContent/SubscribedEvents/Search/global.html.twig'
            );
        }
    }
}
