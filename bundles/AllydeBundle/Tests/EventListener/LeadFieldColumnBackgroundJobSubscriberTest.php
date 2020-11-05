<?php

/*
 * @package     Mautic
 * @copyright   2020 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AllydeBundle\Tests\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManager;
use Mautic\AllydeBundle\Beanstalk\JobManager;
use Mautic\AllydeBundle\EventListener\LeadFieldColumnBackgroundJobSubscriber;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Field\Event\DeleteColumnBackgroundEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LeadFieldColumnBackgroundJobSubscriberTest extends TestCase
{
    /**
     * @var MockObject|JobManager
     */
    private $jobManagerMock;

    /**
     * @var MockObject|EntityManager
     */
    private $entityManagerMock;

    /**
     * @var LeadFieldColumnBackgroundJobSubscriber
     */
    private $leadFieldColumnBackgroundJobSubscriber;

    protected function setUp()
    {
        $this->jobManagerMock                         = $this->createMock(JobManager::class);
        $this->entityManagerMock                      = $this->createMock(EntityManager::class);
        $this->leadFieldColumnBackgroundJobSubscriber = new LeadFieldColumnBackgroundJobSubscriber(
            $this->jobManagerMock,
            $this->entityManagerMock);
    }

    public function testDeleteColumnWithLongRunningQuery()
    {
        $connectionMock = $this->createMock(Connection::class);
        $this->entityManagerMock
            ->expects($this->exactly(2))
            ->method('getConnection')
            ->willReturn($connectionMock);
        $connectionMock
            ->expects($this->exactly(1))
            ->method('getDatabase')
            ->willReturn('db');
        $statementMock = $this->createMock(Statement::class);
        $statementMock
            ->expects($this->exactly(1))
            ->method('execute');
        $statementMock
            ->expects($this->exactly(1))
            ->method('fetchAll')
            ->willReturn([1, 2]); // Running queries
        $connectionMock
            ->expects($this->exactly(1))
            ->method('prepare')
            ->with("SELECT * FROM INFORMATION_SCHEMA.PROCESSLIST WHERE db='db' and command != 'Sleep' and time>3")
            ->willReturn($statementMock);
        $this->jobManagerMock
            ->expects($this->once())
            ->method('queueJob');
        $leadField = new LeadField();
        $event     = new DeleteColumnBackgroundEvent($leadField);
        $this->leadFieldColumnBackgroundJobSubscriber->deleteColumn($event);
    }

    public function testDeleteColumnWithNoLongRunningQuery()
    {
        $connectionMock = $this->createMock(Connection::class);
        $this->entityManagerMock
            ->expects($this->exactly(2))
            ->method('getConnection')
            ->willReturn($connectionMock);
        $connectionMock
            ->expects($this->exactly(1))
            ->method('getDatabase')
            ->willReturn('db');
        $statementMock = $this->createMock(Statement::class);
        $statementMock
            ->expects($this->exactly(1))
            ->method('execute');
        $statementMock
            ->expects($this->exactly(1))
            ->method('fetchAll')
            ->willReturn([]); // 0 running queries
        $connectionMock
            ->expects($this->exactly(1))
            ->method('prepare')
            ->with("SELECT * FROM INFORMATION_SCHEMA.PROCESSLIST WHERE db='db' and command != 'Sleep' and time>3")
            ->willReturn($statementMock);
        $this->jobManagerMock
            ->expects($this->never())
            ->method('queueJob');
        $leadField = new LeadField();
        $event     = new DeleteColumnBackgroundEvent($leadField);
        $this->leadFieldColumnBackgroundJobSubscriber->deleteColumn($event);
    }
}
