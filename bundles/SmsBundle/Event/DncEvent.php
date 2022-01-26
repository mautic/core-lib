<?php

declare(strict_types=1);

namespace Mautic\SmsBundle\Event;

use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\Event;

class DncEvent extends Event
{
    /**
     * @var array<Lead>
     */
    private $contacts;

    /**
     * @var array<int, string>
     */
    private $removed  = [];

    public function __construct(array $contacts)
    {
        $this->contacts = $contacts;
    }

    public function getContacts(): array
    {
        return $this->contacts;
    }

    public function getRemovedContacts(): array
    {
        return $this->removed;
    }

    public function removeContact(int $id): void
    {
        array_push($this->removed, $id);
        unset($this->contacts[$id]);
    }

    public function removeContacts(array $contacts): void
    {
        foreach ($contacts as $contact) {
            $this->removeContact((int) $contact);
        }
    }
}
