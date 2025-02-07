<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class GetStatDataEvent extends Event
{
    /**
     * @var array<string,mixed[]>
     */
    private $results = [];

    /**
     * @param mixed[] $data
     */
    public function addResult(array $data): void
    {
        $this->results = $data;
    }

    /**
     * @return mixed[]
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
