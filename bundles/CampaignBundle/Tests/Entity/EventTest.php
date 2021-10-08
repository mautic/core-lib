<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\Tests\Entity;

use Mautic\CampaignBundle\Entity\Event;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    public function testSetTriggerHourWhenEmpty(): void
    {
        $event = new Event();
        $event->setName('Test Name');
        $event->setTriggerHour('');
        $this->assertEquals('', $event->getTriggerHour());
    }

    public function testSetTriggerHourWhenArray(): void
    {
        $event = new Event();
        $event->setName('Test Name');
        $event->setTriggerHour(['date' => '2021-10-08 08:00:00']);
        $this->assertEquals(new \DateTime('2021-10-08 08:00:00'), $event->getTriggerHour());
    }

    public function testSetTriggerHourWhenString(): void
    {
        $event = new Event();
        $event->setName('Test Name');
        $event->setTriggerHour('2021-10-08 08:00:00');
        $this->assertEquals(new \DateTime('2021-10-08 08:00:00'), $event->getTriggerHour());
    }
}
