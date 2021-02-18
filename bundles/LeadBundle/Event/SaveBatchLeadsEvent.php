<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Event;

use Symfony\Component\EventDispatcher\Event;

final class SaveBatchLeadsEvent extends Event
{
    /**
     * @var LeadEvent[]
     */
    protected $leadsEvents;

    public function __construct(array $leadsEvents)
    {
        $this->leadsEvents = $leadsEvents;
    }

    /**
     * @return LeadEvent[]
     */
    public function getLeadsEvents(): array
    {
        return $this->leadsEvents;
    }
}
