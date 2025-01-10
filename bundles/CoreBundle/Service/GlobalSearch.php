<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Service;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\DTO\GlobalSearchFilterDTO;
use Mautic\CoreBundle\Event\GlobalSearchEvent;
use Mautic\CoreBundle\Model\GlobalSearchInterface;
use Twig\Environment;

class GlobalSearch
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * @param array<mixed> $additionalSearchFilters
     *
     * @return array<int, string>
     */
    public function performSearch(
        GlobalSearchFilterDTO $filterDTO,
        GlobalSearchInterface $model,
        string                $template,
    ): array {
        if (empty($filterDTO->getSearchString()) || (!$model->canViewOwnEntity() && !$model->canViewOthersEntity())) {
            return [];
        }

        $entities = $model->getEntitiesForGlobalSearch($filterDTO);

        if (empty($entities)) {
            return [];
        }

        return $this->processResults($entities, $filterDTO->getSearchString(), $template);
    }

    /**
     * @return array<int, string>
     */
    private function processResults(Paginator $entities, string $searchString, string $template): array
    {
        $count = $entities->count();
        if (0 === $count) {
            return [];
        }

        $renderedResults = [];
        foreach ($entities as $entity) {
            $renderedResults[] = $this->twig->render($template, ['item' => $entity]);
        }

        if ($count > GlobalSearchEvent::RESULTS_LIMIT) {
            $renderedResults[] = $this->twig->render($template, [
                'searchString' => $searchString,
                'showMore'     => true,
                'remaining'    => $count - GlobalSearchEvent::RESULTS_LIMIT,
            ]);
        }

        return $renderedResults;
    }
}
