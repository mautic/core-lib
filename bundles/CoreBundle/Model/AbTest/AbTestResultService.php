<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Model\AbTest;

use Mautic\CoreBundle\Entity\VariantEntityInterface;
use Mautic\CoreBundle\Event\DetermineWinnerEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AbTestResultService
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @param array<mixed>|null $criteria
     *
     * @return array|mixed
     */
    public function getAbTestResult(VariantEntityInterface $parentVariant, ?array $criteria = null)
    {
        // get A/B test information
        [$parent, $children] = $parentVariant->getVariants();

        $abTestResults = [];
        if (isset($criteria)) {
            $testSettings = $criteria;
            $args         = [
                'email'    => $parentVariant,
                'parent'   => $parent,
                'children' => $children,
            ];

            if (isset($testSettings['event'])) {
                $determineWinnerEvent = new DetermineWinnerEvent($args);
                $this->dispatcher->dispatch($determineWinnerEvent, $testSettings['event']);
                $abTestResults = $determineWinnerEvent->getAbTestResults();
            }
        }

        return $abTestResults;
    }
}
