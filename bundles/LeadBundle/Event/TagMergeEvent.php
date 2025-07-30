<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Event;

use Mautic\LeadBundle\Entity\Tag;
use Symfony\Contracts\EventDispatcher\Event;

final class TagMergeEvent extends Event
{
    public function __construct(
        private Tag $victor,
        private Tag $loser,
    ) {
    }

    public function getVictor(): Tag
    {
        return $this->victor;
    }

    public function getLoser(): Tag
    {
        return $this->loser;
    }
}
