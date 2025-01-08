<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Service;

use Mautic\ChannelBundle\Model\MessageModel;
use Mautic\CoreBundle\Event\GlobalSearchEvent;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Twig\Environment;

class GlobalSearch
{
    public function __construct(
        private CorePermissions $security,
        private UserHelper $userHelper,
        private Environment $twig,
    ) {
    }

    /**
     * @param object|MessageModel $model
     *
     * @return array<int|string, int|string>
     */
    public function performSearch(
        string $searchString,
        mixed $model,
        string $template,
        bool $skipPermissions = false,
    ): array {
        $permissions = $this->getPermissions($model->getPermissionBase(), $skipPermissions);

        if (!$permissions['viewown'] && !$permissions['viewother']) {
            return [];
        }

        $filter = ['string' => $searchString, 'force' => []];

        if (!$permissions['viewother']) {
            $filter['force'][] = [
                'column' => 'createdBy',
                'expr'   => 'eq',
                'value'  => $this->userHelper->getUser()->getId(),
            ];
        }

        $results = $model->getEntities([
            'filter'           => $filter,
            'start'            => 0,
            'limit'            => GlobalSearchEvent::RESULTS_LIMIT,
            'ignore_paginator' => true,
            'with_total_count' => true,
        ]);

        return $this->processResults($results, $searchString, $template, $permissions);
    }

    /**
     * Process search results and render templates.
     *
     * @param array<mixed>         $results
     * @param array<string, bool> $permissions
     *
     * @return array<int|string, mixed>
     */
    private function processResults(
        array $results,
        string $searchString,
        string $template,
        array $permissions,
    ): array {
        $count = $results['count'] ? (int) $results['count'] : 0;

        if ($count === 0) {
            return [];
        }

        $renderedResults = array_map(
            fn($item) => $this->twig->render($template, [
                'item'    => $item,
                'canEdit' => $this->canEditItem($item, $permissions),
            ]),
            $results['results']
        );

        if ($count > GlobalSearchEvent::RESULTS_LIMIT) {
            $renderedResults[] = $this->twig->render($template, [
                'searchString' => $searchString,
                'showMore'     => true,
                'remaining'    => $count - GlobalSearchEvent::RESULTS_LIMIT,
            ]);
        }

        $renderedResults['count'] = $count;

        return $renderedResults;
    }

    /**
     * Determine if the current user can edit an item.
     *
     * @param object              $item
     * @param array<string, bool> $permissions
     *
     * @return bool
     */
    private function canEditItem(object $item, array $permissions): bool
    {
        return $item->getCreatedBy() === $this->userHelper->getUser()->getId()
            ? $permissions['editown']
            : $permissions['editother'];
    }

    /**
     * Retrieve permissions based on the given base and skip flag.
     *
     * @param string $permissionsBase
     * @param bool   $skipPermissions
     *
     * @return array<string, bool>
     */
    private function getPermissions(string $permissionsBase, bool $skipPermissions): array
    {
        return $skipPermissions
            ? array_fill_keys(['viewown', 'viewother', 'editown', 'editother'], true)
            : [
                'viewown'   => $this->security->isGranted("$permissionsBase:viewown"),
                'viewother' => $this->security->isGranted("$permissionsBase:viewother"),
                'editown'   => $this->security->isGranted("$permissionsBase:editown"),
                'editother' => $this->security->isGranted("$permissionsBase:editother"),
            ];
    }
}
