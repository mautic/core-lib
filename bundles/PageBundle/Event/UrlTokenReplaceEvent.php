<?php

declare(strict_types=1);

namespace Mautic\PageBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when replacing tokens in a URL, allowing modifications before the final redirect.
 */
class UrlTokenReplaceEvent extends Event
{
    public function __construct(
        private string $content,
        private $lead,
        private ?int $emailId = null,
    ) {
    }

    /**
     * Get the URL content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set the URL content.
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * Get the lead (can be Lead entity or lead ID).
     *
     * @return mixed
     */
    public function getLead()
    {
        return $this->lead;
    }

    /**
     * Get the email ID.
     */
    public function getEmailId(): ?int
    {
        return $this->emailId;
    }
}
