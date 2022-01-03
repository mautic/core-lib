<?php

namespace Mautic\LeadBundle\Event;

use MauticPlugin\MauticNetworkBundle\Exception\KeyAlreadyExistsException;
use Symfony\Component\EventDispatcher\Event;

class GetStatDataEvent extends Event
{
    /**
     * @var array
     */
    private $results = [];

    /**
     * @throws KeyAlreadyExistsException
     */
    public function addResult(string $key, array $data): void
    {
        if (isset($this->results[$key])) {
            throw new KeyAlreadyExistsException('Key "'.$key.'" already exists in results.');
        }
        $this->results[$key] = $data;
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
