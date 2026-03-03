<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Model\AbTest;

use Mautic\CoreBundle\Entity\VariantEntityInterface;
use Mautic\CoreBundle\Event\DetermineWinnerEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class AbTestResultService.
 */
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
     *
     * @throws \ReflectionException
     */
    public function getAbTestResult(VariantEntityInterface $parentVariant, $criteria)
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

            // execute the callback
            if (isset($testSettings['callback']) && is_callable($testSettings['callback'])) {
                if (is_array($testSettings['callback'])) {
                    $reflection = new \ReflectionMethod($testSettings['callback'][0], $testSettings['callback'][1]);
                    $instance   = is_object($testSettings['callback'][0]) ? $testSettings['callback'][0] : null;
                } elseif (str_contains($testSettings['callback'], '::')) {
                    $parts      = explode('::', $testSettings['callback']);
                    $reflection = new \ReflectionMethod($parts[0], $parts[1]);
                    $instance   = null;
                } else {
                    $reflection = new \ReflectionMethod('', $testSettings['callback']);
                    $instance   = null;
                }

                $pass = [];
                foreach ($reflection->getParameters() as $param) {
                    if (isset($args[$param->getName()])) {
                        $pass[] = $args[$param->getName()];
                    } else {
                        $pass[] = null;
                    }
                }
                $abTestResults = $reflection->invokeArgs($instance, $pass);
            }
        }

        return $abTestResults;
    }
}
