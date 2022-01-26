<?php

declare(strict_types=1);

namespace Mautic\SmsBundle\Tests\EventListener;

use Mautic\ChannelBundle\Model\MessageQueueModel;
use Mautic\LeadBundle\Entity\DoNotContactRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\SmsBundle\Event\DncEvent;
use Mautic\SmsBundle\EventListener\SendSmsSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SendSmsSubscriberTest extends TestCase
{
    /**
     * @var SendSmsSubscriber
     */
    private $subscriber;

    /**
     * @var DoNotContactRepository|MockObject
     */
    private $dncRepoMock;

    public function setUp(): void
    {
        $this->subscriber = new SendSmsSubscriber(
            $this->dncRepoMock = $this->createMock(DoNotContactRepository::class),
            $this->createMock(MessageQueueModel::class)
        );
    }

    public function testDncFilterNoEntriesDoesNotRemoveContacts(): void
    {
        $this->dncRepoMock->method('getChannelList')
            ->willReturn([]);

        $event = new DncEvent($contacts = [
            1 => new Lead(),
            2 => new Lead(),
        ]);

        $this->subscriber->dncFilter($event);

        $this->assertSame($contacts, $event->getContacts());
    }

    public function testDncFilterContactWithDncIsRemoved(): void
    {
        $this->dncRepoMock->method('getChannelList')
            ->willReturn($contactToRemove = [
                1 => new Lead(),
            ]);

        $event = new DncEvent(array_merge($contacts = [
            2 => new Lead(),
        ], $contactToRemove));

        $this->subscriber->dncFilter($event);

        $this->assertSame($contacts, $event->getContacts());
        $this->assertSame($contactToRemove, $event->getRemovedContacts());
    }
}
