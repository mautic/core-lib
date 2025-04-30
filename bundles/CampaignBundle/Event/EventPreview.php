<?php

namespace Mautic\CampaignBundle\Event;

use Mautic\CampaignBundle\Entity\Event;

class EventPreview
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public Event $event, public array $data)
    {
    }

    public function isType(string $type): bool
    {
        return $this->event->getType() === $type;
    }
}
