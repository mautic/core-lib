<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\Tests\Executioner\Scheduler;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignLead;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CoreBundle\Entity\AuditLog;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;

final class EventSchedulerExtendTriggerDateFunctionalTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configParams['campaign_republish_behavior'] = 'restart_on_publish';
    }

    private function createPublishAuditLog(Campaign $campaign, \DateTime $dateAdded, bool $isPublished): void
    {
        $auditLog = new AuditLog();
        $auditLog->setBundle('campaign');
        $auditLog->setObject('campaign');
        $auditLog->setObjectId((int) $campaign->getId());
        $auditLog->setAction('update');
        $auditLog->setUserName('admin');
        $auditLog->setUserId(1);
        $auditLog->setIpAddress('127.0.0.1');
        $auditLog->setDateAdded($dateAdded);
        $auditLog->setDetails([
            'isPublished' => [
                '0' => !$isPublished,
                '1' => $isPublished,
            ],
        ]);

        $this->em->persist($auditLog);
    }

    public function testCampaignTriggerCommandWithNegativeSecondsDoesNotCrash(): void
    {
        $contact = new Lead();
        $this->em->persist($contact);

        $campaign = new Campaign();
        $campaign->setName('Test Campaign Negative Interval');
        $campaign->setRepublishBehavior('count_only_while_published');
        $campaign->setIsPublished(true);
        $this->em->persist($campaign);
        $this->em->flush();

        // Campaign was republished most recently NOW (this becomes lastPublishDate)
        $this->createPublishAuditLog($campaign, new \DateTime('now'), true);

        $event = new Event();
        $event->setName('Test Event');
        $event->setType('email.send');
        $event->setEventType('action');
        $event->setCampaign($campaign);
        $event->setTriggerMode(Event::TRIGGER_MODE_INTERVAL);
        $event->setTriggerInterval(5); // 5 days interval
        $event->setTriggerIntervalUnit('d');
        $this->em->persist($event);

        // Add contact to campaign
        $campaignLead = new CampaignLead();
        $campaignLead->setCampaign($campaign);
        $campaignLead->setLead($contact);
        $campaignLead->setDateAdded(new \DateTime('-30 days'));
        $campaignLead->setManuallyAdded(false);
        $this->em->persist($campaignLead);

        // Log was created 30 days ago, was supposed to trigger 25 days ago (30 - 5)
        // lastPublishDate = now, dateTriggered = 30 days ago
        // secondsToAdd = 5 days - 30 days = NEGATIVE!
        // Without fix: tries to build "PT-2160000S" interval -> Exception
        $log = new LeadEventLog();
        $log->setEvent($event);
        $log->setLead($contact);
        $log->setCampaign($campaign);
        $log->setDateTriggered(new \DateTime('-30 days'));
        $log->setRotation(1);
        $log->setIsScheduled(true);
        $log->setTriggerDate(new \DateTime('-25 days')); // Was supposed to trigger 25 days ago (overdue!)
        $this->em->persist($log);

        $this->em->flush();

        $logId = $log->getId();

        $this->em->clear();

        // Without the fix, this throws: Exception "Unknown or bad format (PT-XXXXS)"
        // With the fix, this executes successfully
        $output = $this->testSymfonyCommand('mautic:campaigns:trigger', ['--campaign-id' => $campaign->getId()])->getDisplay();

        $this->assertStringNotContainsString('Exception', $output, 'Command should execute without errors');

        // Verify the event was processed
        $updatedLog = $this->em->getRepository(LeadEventLog::class)->find($logId);

        $this->assertNotNull($updatedLog, 'Event log should exist');
        $this->assertNotNull($updatedLog->getTriggerDate(), 'Trigger date should be set');
    }

    public function testCampaignTriggerCommandWithPositiveSecondsSchedulesCorrectly(): void
    {
        $contact = new Lead();
        $this->em->persist($contact);

        $campaign = new Campaign();
        $campaign->setName('Test Campaign Positive Interval');
        $campaign->setRepublishBehavior('count_only_while_published');
        $campaign->setIsPublished(true);
        $this->em->persist($campaign);
        $this->em->flush();

        // Campaign was published 3 days ago
        $this->createPublishAuditLog($campaign, new \DateTime('-3 days'), true);

        $event = new Event();
        $event->setName('Test Event');
        $event->setType('email.send');
        $event->setEventType('action');
        $event->setCampaign($campaign);
        $event->setTriggerMode(Event::TRIGGER_MODE_INTERVAL);
        $event->setTriggerInterval(10); // 10 days interval
        $event->setTriggerIntervalUnit('d');
        $this->em->persist($event);

        // Add contact to campaign
        $campaignLead = new CampaignLead();
        $campaignLead->setCampaign($campaign);
        $campaignLead->setLead($contact);
        $campaignLead->setDateAdded(new \DateTime('-3 days'));
        $campaignLead->setManuallyAdded(false);
        $this->em->persist($campaignLead);

        // Log was created 3 days ago, should trigger 7 days from now (3 + 10 - 3 = 7)
        $log = new LeadEventLog();
        $log->setEvent($event);
        $log->setLead($contact);
        $log->setCampaign($campaign);
        $log->setDateTriggered(new \DateTime('-3 days'));
        $log->setRotation(1);
        $log->setIsScheduled(true);
        $log->setTriggerDate(new \DateTime('+7 days')); // Should trigger in the future
        $this->em->persist($log);

        $this->em->flush();

        $logId = $log->getId();

        $this->em->clear();

        $output = $this->testSymfonyCommand('mautic:campaigns:trigger', ['--campaign-id' => $campaign->getId()])->getDisplay();

        $this->assertStringNotContainsString('Exception', $output, 'Command should execute without errors');

        // The event should be rescheduled to approximately 7 days from now (10 day interval - 3 days elapsed)
        $updatedLog = $this->em->getRepository(LeadEventLog::class)->find($logId);

        $this->assertNotNull($updatedLog, 'Event log should exist');
        $this->assertNotNull($updatedLog->getTriggerDate(), 'Trigger date should be set');

        $expectedTriggerDate = new \DateTime('+7 days');
        $lowerBound          = (clone $expectedTriggerDate)->modify('-10 seconds');
        $upperBound          = (clone $expectedTriggerDate)->modify('+10 seconds');

        $this->assertGreaterThan($lowerBound, $updatedLog->getTriggerDate(), 'Event should be scheduled around 7 days from now');
        $this->assertLessThan($upperBound, $updatedLog->getTriggerDate(), 'Event should be scheduled around 7 days from now');
    }

    public function testCampaignDoesNotResendImmediateEmailAfterContactExitsAndRejoinsWhenRestartIsDisabled(): void
    {
        // TDD test for forum issue: Campaign never finishes, resends email every 5 minutes
        // https://forum.mautic.org/t/campaign-never-finish/38207
        //
        // User disabled "Allow contacts to restart campaign" but immediate action
        // emails were still resent every trigger run (5-minute cron interval).
        //
        // Root cause: Adder::updateExistingMembership() bypasses allowRestart check
        // when wasManuallyRemoved=false, allowing rotation increment on natural exits.
        //
        // TDD PHASE: This test FAILS until fix is applied.
        // It reproduces the exact bug: extra log created, proving re-execution.

        $contact = new Lead();
        $contact->setEmail('test-natural-exit@example.com');
        $this->em->persist($contact);

        $campaign = new Campaign();
        $campaign->setName('Test Restart Disabled Campaign');
        $campaign->setIsPublished(true);
        $campaign->setAllowRestart(false); // User explicitly disabled
        $this->em->persist($campaign);

        $event = new Event();
        $event->setName('Immediate Email');
        $event->setType('email.send');
        $event->setEventType('action');
        $event->setCampaign($campaign);
        $event->setTriggerMode(Event::TRIGGER_MODE_IMMEDIATE);
        $this->em->persist($event);

        $campaignLead = new CampaignLead();
        $campaignLead->setCampaign($campaign);
        $campaignLead->setLead($contact);
        $campaignLead->setDateAdded(new \DateTime('-1 day'));
        $campaignLead->setManuallyAdded(false);
        $campaignLead->setManuallyRemoved(false); // Natural exit, NOT manual removal
        $campaignLead->setRotation(1);
        $this->em->persist($campaignLead);

        $this->em->flush();

        $campaignId = $campaign->getId();
        $eventId    = $event->getId();
        $contactId  = $contact->getId();
        $db         = $this->em->getConnection();
        $prefix     = static::getContainer()->getParameter('mautic.db_table_prefix');

        $this->em->clear();

        // Phase 1: Initial trigger - action executes at rotation=1
        $this->testSymfonyCommand('mautic:campaigns:trigger', ['--campaign-id' => $campaignId]);
        $this->em->clear();

        $logsPhase1 = $db->createQueryBuilder()
            ->select('rotation, is_scheduled')
            ->from($prefix.'campaign_lead_event_log', 'log')
            ->where('log.event_id = :eventId AND log.lead_id = :leadId')
            ->setParameters(['eventId' => $eventId, 'leadId' => $contactId])
            ->executeQuery()->fetchAllAssociative();

        $this->assertCount(1, $logsPhase1, 'Phase 1: First trigger creates exactly 1 log');
        $this->assertEquals(1, $logsPhase1[0]['rotation'], 'Phase 1: Log at rotation=1');

        // Phase 2: Contact naturally exits (segment condition, segment move, etc.)
        // date_last_exited is set but manually_removed stays false
        $db->executeStatement(
            'UPDATE '.MAUTIC_TABLE_PREFIX.'campaign_leads SET date_last_exited = NOW() WHERE campaign_id = ? AND lead_id = ?',
            [$campaignId, $contactId]
        );
        $this->em->clear();

        // Phase 3: campaigns:rebuild auto-re-adds (isManualAction=false)
        // FIX: allowRestart check now includes dateLastExited check
        // ContactCannotBeAddedToCampaignException should be thrown when:
        // - allowRestart=false AND
        // - NOT (manually removed && being manually re-added)
        $adder              = static::getContainer()->get(\Mautic\CampaignBundle\Membership\Action\Adder::class);
        $campaignLeadEntity = $this->em->getRepository(CampaignLead::class)->findOneBy([
            'lead' => $contactId, 'campaign' => $campaignId,
        ]);

        try {
            $adder->updateExistingMembership($campaignLeadEntity, false); // false = automatic re-add
            // If no exception, verify rotation didn't increment (shouldn't happen with fix)
            $rotationAfterAttempt = $campaignLeadEntity->getRotation();
            $this->assertEquals(
                1,
                $rotationAfterAttempt,
                'Rotation should not increment when fix prevents re-entry'
            );
        } catch (\Mautic\CampaignBundle\Membership\Exception\ContactCannotBeAddedToCampaignException $e) {
            // FIX IS WORKING: Exception thrown as expected
            // Natural exit cannot be auto re-added when allowRestart=false
            $this->assertStringContainsString(
                'cannot restart',
                $e->getMessage()
            );
        }

        $this->em->clear();

        // Phase 4: Second trigger run verifies rotation was protected
        // Since Phase 3 exception prevented rotation increment, trigger should not re-execute
        $this->testSymfonyCommand('mautic:campaigns:trigger', ['--campaign-id' => $campaignId]);
        $this->em->clear();

        $logsFinal = $db->createQueryBuilder()
            ->select('rotation, is_scheduled')
            ->from($prefix.'campaign_lead_event_log', 'log')
            ->where('log.event_id = :eventId AND log.lead_id = :leadId')
            ->orderBy('log.id', 'ASC')
            ->setParameters(['eventId' => $eventId, 'leadId' => $contactId])
            ->executeQuery()->fetchAllAssociative();

        // Verify no new log created (rotation protection worked)
        $this->assertCount(
            1,
            $logsFinal,
            'Forum issue #16133 fixed: Natural exit with allowRestart=false blocks automatic re-entry. '.
            'Action does not re-execute on subsequent trigger runs.'
        );

        $this->assertEquals(1, $logsFinal[0]['rotation'], 'Single log remains at rotation=1');
    }
}
