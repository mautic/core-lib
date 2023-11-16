<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\Tests\Functional\Controller;

use function GuzzleHttp\json_decode;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignLead;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;

class CampaignEventStatsTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {
        $this->configParams['campaign_use_summary']     = false;
        $this->configParams['campaign_event_cache_ttl'] = 3;

        parent::setUp();
    }

    public function testCountsProcessedCampaignsMethodCountsProcessedCampaignsCorrectly(): void
    {
        $campaign = new Campaign();
        $campaign->setName('Test Campaign');
        $this->em->persist($campaign);

        $lead = new Lead();
        $lead->setFirstname('Test Lead');
        $this->em->persist($lead);

        $campaignEvent1 = new Event();
        $campaignEvent1->setCampaign($campaign);
        $campaignEvent1->setName('Send Email 1');
        $campaignEvent1->setType('email.send');
        $campaignEvent1->setEventType('action');
        $campaignEvent1->setProperties([]);
        $this->em->persist($campaignEvent1);

        $campaignEvent2 = new Event();
        $campaignEvent2->setCampaign($campaign);
        $campaignEvent2->setName('Jump to send email 1');
        $campaignEvent2->setType('campaign.jump_to_event');
        $campaignEvent2->setEventType('action');
        $campaignEvent2->setProperties([]);
        $this->em->persist($campaignEvent2);

        $campaignLead = new CampaignLead();
        $campaignLead->setCampaign($campaign);
        $campaignLead->setLead($lead);
        $campaignLead->setDateAdded(new \DateTime());
        $this->em->persist($campaignLead);

        $leadEventLog1 = new LeadEventLog();
        $leadEventLog1->setLead($lead);
        $leadEventLog1->setEvent($campaignEvent1);
        $leadEventLog1->setIsScheduled(true);
        $leadEventLog1->setRotation(1);
        $this->em->persist($leadEventLog1);

        $leadEventLog3 = new LeadEventLog();
        $leadEventLog3->setLead($lead);
        $leadEventLog3->setEvent($campaignEvent1);
        $leadEventLog1->setRotation(2);
        $this->em->persist($leadEventLog3);

        $this->em->flush();

        $eventsStatistics         = $this->getEventsStatistics($campaign);

        $expectedEventsStatistics = [
            0 => [
                'successPercent' => '100%',
                'completed'      => '1',
                'pending'        => '1',
            ],
            1 => [
                'successPercent' => '0%',
                'completed'      => '0',
                'pending'        => '0',
            ],
        ];

        Assert::assertSame($expectedEventsStatistics, $eventsStatistics, 'Events statistics doesn\'t match the actual events in the database.');

        $leadEventLog2 = new LeadEventLog();
        $leadEventLog2->setLead($lead);
        $leadEventLog2->setEvent($campaignEvent2);
        $leadEventLog1->setRotation(1);
        $this->em->persist($leadEventLog2);
        $this->em->flush();

        $eventsStatistics         = $this->getEventsStatistics($campaign);
        Assert::assertSame($expectedEventsStatistics, $eventsStatistics, 'Events statistics doesn\'t match the actual events in the database.');

        sleep(5);

        $eventsStatistics         = $this->getEventsStatistics($campaign);

        $expectedEventsStatistics = [
            0 => [
                'successPercent' => '100%',
                'completed'      => '1',
                'pending'        => '1',
            ],
            1 => [
                'successPercent' => '100%',
                'completed'      => '1',
                'pending'        => '0',
            ],
        ];

        Assert::assertSame($expectedEventsStatistics, $eventsStatistics, 'Events statistics doesn\'t match the actual events in the database.');
    }

    private function getTestCrawler(Campaign $campaign): Crawler
    {
        $now    = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $before = $now->modify('-1 month');
        $after  = $now->modify('+1 month');
        $url    = sprintf('s/campaigns/event/stats/%d/%s/%s', $campaign->getId(), $before->format('Y-m-d'), $after->format('Y-m-d'));
        $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $body     = json_decode($response->getContent(), true);
        $this->client->restart();

        return new Crawler($body['actions']);
    }

    private function getEventsStatistics(Campaign $campaign): array
    {
        $crawler = $this->getTestCrawler($campaign);
        $events  = [];
        for ($eventIndex = 0;; ++$eventIndex) {
            $node = $crawler->filter('.campaign-event-list')->filter('span')->eq($eventIndex * 3);
            if (1 > $node->count()) {
                break;
            }
            $events[] = [
                'successPercent' => trim($crawler->filter('.campaign-event-list')->filter('span')->eq($eventIndex * 3)->html()),
                'completed'      => trim($crawler->filter('.campaign-event-list')->filter('span')->eq($eventIndex * 3 + 1)->html()),
                'pending'        => trim($crawler->filter('.campaign-event-list')->filter('span')->eq($eventIndex * 3 + 2)->html()),
            ];
        }

        return $events;
    }
}
