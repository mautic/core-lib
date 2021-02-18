<?php

namespace Mautic\LeadBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\LeadBundle\Entity\Lead;

class LeadEvent extends CommonEvent
{
    /**
     * @var bool;
     */
    protected $alreadyProcessedInBatch = false;

    /**
     * @param bool $isNew
     */
    public function __construct(Lead &$lead, $isNew = false)
    {
        $this->entity = &$lead;
        $this->isNew  = $isNew;
    }

    /**
     * Returns the Lead entity.
     *
     * @return Lead
     */
    public function getLead()
    {
        return $this->entity;
    }

    /**
     * Sets the Lead entity.
     */
    public function setLead(Lead $lead): void
    {
        $this->entity = $lead;
    }

    public function isAlreadyProcessedInBatch(): bool
    {
        return $this->alreadyProcessedInBatch;
    }

    public function setAlreadyProcessedInBatch(bool $alreadyProcessedInBatch): void
    {
        $this->alreadyProcessedInBatch = $alreadyProcessedInBatch;
    }
}
