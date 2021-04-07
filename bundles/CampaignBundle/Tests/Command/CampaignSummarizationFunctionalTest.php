<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Tests\Command;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignLead;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Entity\Summary;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use PHPUnit\Framework\Assert;

class CampaignSummarizationFunctionalTest extends MauticMysqlTestCase
{
    protected function setUp()
    {
        $this->configParams['campaign_use_summary'] = 'testExecuteCampaignEventWithSummarization' === $this->getName();
        parent::setUp();
    }

    public function testExecuteCampaignEventWithoutSummarization(): void
    {
        $this->createDataAndExecuteCommand();
        $campaignSummary = $this->em->getRepository(Summary::class)->findAll();
        Assert::assertCount(0, $campaignSummary);
    }

    public function testExecuteCampaignEventWithSummarization(): void
    {
        $this->createDataAndExecuteCommand();
        $campaignSummary = $this->em->getRepository(Summary::class)->findAll();
        Assert::assertCount(1, $campaignSummary);
    }

    private function createDataAndExecuteCommand(): void
    {
        $lead              = $this->createLead();
        $campaign          = $this->createCampaign();
        $event             = $this->createEvent('Event 1', $campaign);
        $this->createCampaignLead($campaign, $lead);
        $this->createEventLog($lead, $event, $campaign);
        $this->em->flush();
        $this->em->clear();

        $this->runCommand('mautic:campaigns:trigger', ['--campaign-id' => $campaign->getId(), '--kickoff-only' => true]);
    }

    private function createLead(): Lead
    {
        $lead = new Lead();
        $lead->setFirstname('Test');
        $this->em->persist($lead);

        return $lead;
    }

    private function createCampaign(): Campaign
    {
        $campaign = new Campaign();
        $campaign->setName('My campaign');
        $campaign->setIsPublished(true);
        $this->em->persist($campaign);

        return $campaign;
    }

    private function createCampaignLead(Campaign $campaign, Lead $lead): CampaignLead
    {
        $campaignLead = new CampaignLead();
        $campaignLead->setCampaign($campaign);
        $campaignLead->setLead($lead);
        $campaignLead->setDateAdded(new \DateTime());
        $this->em->persist($campaignLead);

        return $campaignLead;
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

        return $event;
    }

    private function createEventLog(Lead $lead, Event $event, Campaign $campaign): LeadEventLog
    {
        $leadEventLog = new LeadEventLog();
        $leadEventLog->setLead($lead);
        $leadEventLog->setEvent($event);
        $leadEventLog->setCampaign($campaign);
        $leadEventLog->setRotation(0);
        $this->em->persist($leadEventLog);

        return $leadEventLog;
    }
}
