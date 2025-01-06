<?php

declare(strict_types=1);

namespace Mautic\FormBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event as MauticEvents;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\FormBundle\Model\FormModel;
use Mautic\LeadBundle\EventListener\GlobalSearchTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twig\Environment;

class SearchSubscriber implements EventSubscriberInterface
{
    use GlobalSearchTrait;

    public function __construct(
        private UserHelper $userHelper,
        private FormModel $formModel,
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
        $str = $event->getSearchString();
        if (empty($str)) {
            return;
        }

        $filter = ['string' => $str, 'force' => ''];

        $permissions = $this->security->isGranted(['form:forms:viewown', 'form:forms:viewother'], 'RETURN_ARRAY');
        if ($permissions['form:forms:viewown'] || $permissions['form:forms:viewother']) {
            // only show own forms if the user does not have permission to view others
            if (!$permissions['form:forms:viewother']) {
                $filter['force'] = [
                    ['column' => 'f.createdBy', 'expr' => 'eq', 'value' => $this->userHelper->getUser()->getId()],
                ];
            }

            $forms = $this->formModel->getEntities(
                [
                    'filter' => $filter,
                    'start'            => 0,
                    'limit'            => MauticEvents\GlobalSearchEvent::RESULTS_LIMIT,
                    'ignore_paginator' => true,
                    'with_total_count' => true,
                ]);

            $this->addGlobalSearchResults(
                $this->twig,
                $event,
                $forms,
                'mautic.form.forms',
                '@MauticForm/SubscribedEvents/Search/global.html.twig'
            );
        }
    }

    public function onBuildCommandList(MauticEvents\CommandListEvent $event): void
    {
        if ($this->security->isGranted(['form:forms:viewown', 'form:forms:viewother'], 'MATCH_ONE')) {
            $event->addCommands(
                'mautic.form.forms',
                $this->formModel->getCommandList()
            );
        }
    }
}
