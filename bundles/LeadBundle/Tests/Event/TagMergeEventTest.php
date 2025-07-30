<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Event;

use Mautic\LeadBundle\Entity\Tag;
use Mautic\LeadBundle\Event\TagMergeEvent;
use PHPUnit\Framework\TestCase;

class TagMergeEventTest extends TestCase
{
    public function testConstructGettersSetters(): void
    {
        $victor = new Tag();
        $loser  = new Tag();
        $event  = new TagMergeEvent($victor, $loser);

        $this->assertEquals($victor, $event->getVictor());
        $this->assertEquals($loser, $event->getLoser());
    }
}
