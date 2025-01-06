<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\EventListener;

use Mautic\CoreBundle\Event\GlobalSearchEvent;
use Twig\Environment;

trait GlobalSearchTrait
{
    /**
     * @param array<string, int|string|array<int, object>> $results
     */
    private function addGlobalSearchResults(
        Environment $twig,
        GlobalSearchEvent $event,
        array $results,
        string $template,
        string $resultKey
    ): void {
        $count = $results['count'] ? (int) $results['count'] : 0;

        if (0 === $count) {
            return;
        }

        $renderedResults = array_map(
            fn ($item) => $twig->render($template, ['item' => $item]),
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
