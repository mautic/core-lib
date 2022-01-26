<?php

declare(strict_types=1);

namespace Mautic\SmsBundle\Event;

use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\Event;

class QueueEvent extends Event
{
    /**
     * @var array<Lead>
     */
    private $contacts;

    /**
     * @var array
     */
    private $options;

    /**
     * @var array<int, string>
     */
    private $queued  = [];

    public function __construct(array $contacts, array $options)
    {
        $this->contacts = $contacts;
        $this->options  = $options;
    }

    public function getContacts(): array
    {
        return $this->contacts;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getQueuedContacts(): array
    {
        return $this->queued;
    }

    public function queueContact(int $id): void
    {
        array_push($this->queued, $id);
        unset($this->contacts[$id]);
    }

    public function queueContacts(array $contacts): void
    {
        foreach ($contacts as $contact) {
            $this->queueContact((int) $contact);
        }
    }
}
