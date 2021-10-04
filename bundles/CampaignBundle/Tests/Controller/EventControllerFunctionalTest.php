<?php

declare(strict_types=1);
/**
 * @copyright   2021 Mautic, Inc. All rights reserved
 * @author      Mautic
 *
 * @see         https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Tests\Controller;

use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

final class EventControllerFunctionalTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {
        $this->configParams['site_url'] = 'https://localhost';
        parent::setUp();
    }

    public function testEventsAreNotAccessibleWithXhr(): void
    {
        $campaign = $this->createCampaign();
        $event1   = $this->createEvent('Event1', $campaign);

        $this->client->request(
            Request::METHOD_POST,
            '/s/campaigns/events/edit/'.$event1->getId().'?campaignId='.$campaign->getId(),
            [],
            [],
            [],
            '{}'
        );

        $response = $this->client->getResponse();
        $response = json_decode($response->getContent(), true);
        Assert::assertSame(
            'You do not have access to the requested area/action.',
            $response['error']
        );
    }

    public function testEventsAreAccessible(): void
    {
        $campaign = $this->createCampaign();
        $event1   = $this->createEvent('Event1', $campaign);

        $this->client->request(
            Request::METHOD_POST,
            '/s/campaigns/events/edit/'.$event1->getId().'?campaignId='.$campaign->getId(),
            [],
            [],
            $this->createAjaxHeaders(),
            '{}'
        );

        $response = $this->client->getResponse();
        $response = json_decode($response->getContent(), true);
        Assert::assertSame(
            $event1->getId(),
            $response['eventId']
        );
        Assert::assertSame(
            $event1->getName(),
            $response['event']['name']
        );
    }

    public function testEventsAreDeleted(): void
    {
        $campaign = $this->createCampaign();
        $event1   = $this->createEvent('Event1', $campaign);

        $this->client->request(
            Request::METHOD_POST,
            '/s/campaigns/events/delete/'.$event1->getId(),
            [
                'modifiedEvents' => json_encode([$event1->getId() => $event1]),
            ],
            [],
            $this->createAjaxHeaders(),
            '{}'
        );

        $response = $this->client->getResponse();
        $response = json_decode($response->getContent(), true);
        Assert::assertSame(
            1,
            $response['success']
        );
        Assert::assertContains(
            $event1->getId(),
            $response['deletedEvents']
        );
    }

    private function createCampaign(): Campaign
    {
        $campaign = new Campaign();
        $campaign->setName('My campaign');
        $campaign->setIsPublished(true);
        $this->em->persist($campaign);
        $this->em->flush();

        return $campaign;
    }

    private function createEvent(string $name, Campaign $campaign): Event
    {
        $event = new Event();
        $event->setName($name);
        $event->setCampaign($campaign);
        $event->setType('email.send');
        $event->setEventType('action');
        $event->setTriggerInterval(1);
        $event->setTriggerMode('immediate');
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }
}
