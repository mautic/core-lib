<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Model;

use Mautic\LeadBundle\Entity\Tag;
use Mautic\LeadBundle\Event\TagMergeEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\TagModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TagModelTest extends TestCase
{
    private TagModel $tagModel;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->tagModel        = $this->createMock(TagModel::class);
    }

    public function testTagMergeWithSameTags(): void
    {
        $tag = new Tag();
        $tag->setTag('Test Tag');

        $tagModel = $this->createMock(TagModel::class);
        $tagModel->expects($this->once())
            ->method('tagMerge')
            ->with($tag, $tag)
            ->willReturn($tag);

        $result = $tagModel->tagMerge($tag, $tag);

        $this->assertSame($tag, $result);
    }

    public function testTagMergeEvent(): void
    {
        $mainTag = new Tag();
        $mainTag->setTag('Main Tag');

        $secTag = new Tag();
        $secTag->setTag('Secondary Tag');

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            LeadEvents::TAG_PRE_MERGE,
            function (TagMergeEvent $event) use (&$eventDispatched, $mainTag, $secTag) {
                $eventDispatched = true;
                $this->assertSame($mainTag, $event->getVictor());
                $this->assertSame($secTag, $event->getLoser());
            }
        );

        $event = new TagMergeEvent($mainTag, $secTag);
        $this->eventDispatcher->dispatch($event, LeadEvents::TAG_PRE_MERGE);

        $this->assertTrue($eventDispatched);
    }
}
