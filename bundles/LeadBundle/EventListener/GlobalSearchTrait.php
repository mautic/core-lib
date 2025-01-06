<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\EventListener;

use Mautic\CoreBundle\Doctrine\Paginator\SimplePaginator;
use Mautic\CoreBundle\Event\GlobalSearchEvent;
use Twig\Environment;

trait GlobalSearchTrait
{
    /**
     * @param array<string, int|string|array<int, object>>|iterable<object>|SimplePaginator<mixed> $results
     * @param array<string, mixed>                                                                 $templateParameters
     */
    private function addGlobalSearchResults(
        Environment $twig,
        GlobalSearchEvent $event,
        array $results,
        string $resultKey,
        string $template,
        array $templateParameters = []
    ): void {
        $count = $results['count'] ? (int) $results['count'] : 0;

        if (0 === $count) {
            return;
        }

        $renderedResults = array_map(
            fn ($item) => $twig->render($template, array_merge(['item' => $item], $templateParameters)),
            $results['results']
        );

        if ($count > GlobalSearchEvent::RESULTS_LIMIT) {
            $renderedResults[] = $twig->render($template, [
                'showMore'     => true,
                'searchString' => $event->getSearchString(),
                'remaining'    => $count - GlobalSearchEvent::RESULTS_LIMIT,
            ]);
        }

        $renderedResults['count'] = $count;
        $event->addResults($resultKey, $renderedResults);
    }
}
