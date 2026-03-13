<?php

declare(strict_types=1);

namespace Mautic\ReportBundle\Tests\Scheduler\Command;

use Mautic\CoreBundle\Helper\ExitCode;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\ReportBundle\Entity\Report;
use Mautic\ReportBundle\Entity\Scheduler;
use Mautic\ReportBundle\Scheduler\Enum\SchedulerEnum;
use PHPUnit\Framework\Assert;

final class ExportSchedulerCommandLockingTest extends MauticMysqlTestCase
{
    /**
     * Test that scheduler command executes normally without lock contention.
     */
    public function testCommandExecutesNormally(): void
    {
        $report = $this->createScheduledReport('Test Report');

        // Create a scheduler entry with past date to ensure it processes
        $scheduler = new Scheduler($report, new \DateTime('-1 minute'));
        $this->em->persist($scheduler);
        $this->em->flush();

        $schedulersBeforeCommand = $this->em->getRepository(Scheduler::class)->findBy(['report' => $report]);
        Assert::assertCount(1, $schedulersBeforeCommand, 'Scheduler should exist before command execution');

        // Execute command normally
        $commandTester = $this->testSymfonyCommand('mautic:reports:scheduler', ['--report' => $report->getId()]);
        Assert::assertEquals(ExitCode::SUCCESS, $commandTester->getStatusCode());
    }

    /**
     * Test that lock is properly released after execution.
     */
    public function testLockIsReleasedAfterExecution(): void
    {
        $report = $this->createScheduledReport('Lock Release Test');

        // Create scheduler entry
        $scheduler = new Scheduler($report, new \DateTime('-1 minute'));
        $this->em->persist($scheduler);
        $this->em->flush();

        // First execution acquires and releases lock
        $commandTester1 = $this->testSymfonyCommand('mautic:reports:scheduler', ['--report' => $report->getId()]);
        Assert::assertEquals(ExitCode::SUCCESS, $commandTester1->getStatusCode());

        // Create another due scheduler entry after first execution.
        $scheduler = new Scheduler($report, new \DateTime('-1 minute'));
        $this->em->persist($scheduler);
        $this->em->flush();
        $secondSchedulerId = $scheduler->getId();

        // Second execution should also succeed (lock was properly released),
        // and process the newly due scheduler.
        $commandTester2 = $this->testSymfonyCommand('mautic:reports:scheduler', ['--report' => $report->getId()]);
        Assert::assertEquals(ExitCode::SUCCESS, $commandTester2->getStatusCode(), 'Second command execution should succeed when lock is properly released');
        Assert::assertNull(
            $this->em->getRepository(Scheduler::class)->find($secondSchedulerId),
            'Second due scheduler should be processed on the second run.'
        );
    }

    /**
     * Test that different report IDs use independent locks.
     */
    public function testDifferentReportIDsAreProcessedIndependently(): void
    {
        $report1 = $this->createScheduledReport('Report One');
        $report2 = $this->createScheduledReport('Report Two');

        // Create schedulers for both reports
        $scheduler1 = new Scheduler($report1, new \DateTime('-1 minute'));
        $scheduler2 = new Scheduler($report2, new \DateTime('-1 minute'));
        $this->em->persist($scheduler1);
        $this->em->persist($scheduler2);
        $this->em->flush();

        $scheduler1Id = $scheduler1->getId();
        $scheduler2Id = $scheduler2->getId();

        $commandTester1 = $this->testSymfonyCommand('mautic:reports:scheduler', ['--report' => $report1->getId()]);
        $commandTester2 = $this->testSymfonyCommand('mautic:reports:scheduler', ['--report' => $report2->getId()]);

        Assert::assertSame(ExitCode::SUCCESS, $commandTester1->getStatusCode(), 'First report should be processed.');
        Assert::assertSame(ExitCode::SUCCESS, $commandTester2->getStatusCode(), 'Second report should be processed.');

        Assert::assertNull($this->em->getRepository(Scheduler::class)->find($scheduler1Id), 'First report scheduler should be processed.');
        Assert::assertNull($this->em->getRepository(Scheduler::class)->find($scheduler2Id), 'Second report scheduler should be processed.');
    }

    /**
     * Test that command processes scheduler and reschedules for recurring reports.
     */
    public function testRecurringReportIsRescheduled(): void
    {
        $report = $this->createScheduledReport('Recurring Report');

        // Create scheduler entry with past date
        $scheduler = new Scheduler($report, new \DateTime('-1 minute'));
        $this->em->persist($scheduler);
        $this->em->flush();

        $schedulerId = $scheduler->getId();

        // Execute command
        $commandTester = $this->testSymfonyCommand('mautic:reports:scheduler', ['--report' => $report->getId()]);
        Assert::assertEquals(ExitCode::SUCCESS, $commandTester->getStatusCode());

        // Verify original scheduler was deleted/rescheduled
        $processedScheduler = $this->em->getRepository(Scheduler::class)->find($schedulerId);
        Assert::assertNull($processedScheduler, 'Original scheduler should be deleted after processing');

        // Verify new scheduler was created for next occurrence
        $newSchedulers = $this->em->getRepository(Scheduler::class)->findBy(['report' => $report]);
        Assert::assertCount(1, $newSchedulers, 'New scheduler should be created for next occurrence');
    }

    /**
     * Test full cleanup for all reports.
     */
    public function testFullCleanupWithoutReportParameter(): void
    {
        $report = $this->createScheduledReport('Full Cleanup Test');

        $scheduler = new Scheduler($report, new \DateTime('-1 minute'));
        $this->em->persist($scheduler);
        $this->em->flush();
        $schedulerId = $scheduler->getId();

        // Execute cleanup for all reports (no --report parameter).
        $commandTester = $this->testSymfonyCommand('mautic:reports:scheduler');
        Assert::assertEquals(ExitCode::SUCCESS, $commandTester->getStatusCode(), 'Full cleanup should succeed');
        Assert::assertNull(
            $this->em->getRepository(Scheduler::class)->find($schedulerId),
            'Due scheduler should be processed when running without --report.'
        );
    }

    /**
     * Create a published scheduled report for testing.
     */
    private function createScheduledReport(string $name): Report
    {
        $report = new Report();
        $report->setName($name);
        $report->setDescription('Test report for locking');
        $report->setSource('audit.log');
        $report->setColumns(['al.action', 'al.date_added']);
        $report->setIsPublished(true);
        $report->setIsScheduled(true);
        $report->setScheduleUnit(SchedulerEnum::UNIT_DAILY);

        $this->em->persist($report);
        $this->em->flush();

        return $report;
    }
}
