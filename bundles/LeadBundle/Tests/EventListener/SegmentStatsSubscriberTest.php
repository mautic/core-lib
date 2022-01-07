<?php

namespace Mautic\LeadBundle\Tests\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Event\GetStatDataEvent;
use Mautic\LeadBundle\EventListener\SegmentStatsSubscriber;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\MauticNetworkBundle\Serializer\EventListener\GlobalTokenSubscriber;
use MauticPlugin\MauticNetworkBundle\Serializer\SerializerEvents;
use PHPUnit\Framework\Assert;

class SegmentStatsSubscriberTest extends MauticMysqlTestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $entityManager;
    /**
     * @var SegmentStatsSubscriber
     */
    private $subscriber;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->subscriber       = new SegmentStatsSubscriber($this->em);
    }

    /**
     * Test SubscribedEvents.
     */
    public function testGetSubscribedEvents(): void
    {
        Assert::assertArrayHasKey(LeadEvents::LEAD_STAT, SegmentStatsSubscriber::getSubscribedEvents());
    }

    public function testGetCampaignEntryPoints(): void
    {
        $segment = $this->createCampaignWithLeadList();
        $event   = new GetStatDataEvent();

        $this->subscriber->getStatsLeadEvents($event);

        $this->assertSame($segment->getId(), (int) $event->getResults()['segments'][0]['item_id']);
    }

    private function createCampaignWithLeadList(): LeadList
    {
        $segmentName = 'Segment1';
        $segment     = new LeadList();
        $segment->setName($segmentName);
        $segment->setAlias(mb_strtolower($segmentName));
        $segment->setIsPublished(true);
        $this->em->persist($segment);
        $this->em->flush();

        $campaign = new Campaign();
        $campaign->setName('Test Campaign');
        $campaign->addList($segment);

        $this->em->persist($campaign);
        $this->em->flush();

        return $segment;
    }
}
