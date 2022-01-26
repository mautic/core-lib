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
     * @var array<string, mixed>
     */
    private $options;

    /**
     * @var array<int>
     */
    private $queued  = [];

    /**
     * @param array<int, Lead>     $contacts
     * @param array<string, mixed> $options
     */
    public function __construct(array $contacts, array $options)
    {
        $this->contacts = $contacts;
        $this->options  = $options;
    }

    /**
     * @return array<int, Lead>
     */
    public function getContacts(): array
    {
        return $this->contacts;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array<int>
     */
    public function getQueuedContacts(): array
    {
        return $this->queued;
    }

    public function queueContact(int $id): void
    {
        array_push($this->queued, $id);
        unset($this->contacts[$id]);
    }

    /**
     * @param array<int> $contacts
     */
    public function queueContacts(array $contacts): void
    {
        foreach ($contacts as $contact) {
            $this->queueContact((int) $contact);
        }
    }
}
