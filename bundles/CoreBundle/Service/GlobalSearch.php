<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Service;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Event\GlobalSearchEvent;
use Mautic\CoreBundle\Model\GlobalSearchModalInterface;
use Twig\Environment;

class GlobalSearch
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function performSearch(
        string $searchString,
        GlobalSearchModalInterface $model,
        string $template,
    ): array {
        if (empty($searchString) || (!$model->canViewOwnEntity() && !$model->canViewOthersEntity())) {
            return [];
        }

        $entities = $model->getEntitiesForGlobalSearch($searchString);

        return $this->processResults($entities, $searchString, $template);
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
