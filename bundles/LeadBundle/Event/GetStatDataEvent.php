<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Event;

use Mautic\LeadBundle\Exception\KeyAlreadyExistsException;
use Symfony\Component\EventDispatcher\Event;

class GetStatDataEvent extends Event
{
    /**
     * @var array<string,mixed[]>
     */
    private $results = [];

    /**
     * @param mixed[] $data
     *
     * @throws KeyAlreadyExistsException
     */
    public function addResult(string $key, array $data): void
    {
        if (isset($this->results[$key])) {
            throw new KeyAlreadyExistsException('Key "'.$key.'" already exists in results.');
        }
        $this->results[$key] = $data;
    }

    /**
     * @return mixed[]
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
