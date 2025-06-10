<?php

namespace Mautic\CampaignBundle\Event;

use Mautic\CampaignBundle\Entity\Event;

class EventPreview
{
    /** @var array<string, mixed> */
    public array $eventStats = [];

    public function __construct(public Event $event)
    {
    }

    public function isType(string $type): bool
    {
        return $this->event->getType() === $type;
    }

    public function addEventStat(string $key, mixed $value, string $tooltip = null): void
    {
        $stat = ['value' => $value];
        if ($tooltip) {
            $stat['tooltip'] = $tooltip;
        }
        $this->eventStats[$key] = $stat;
    }
}
