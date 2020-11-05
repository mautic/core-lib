<?php

/*
 * @package     Mautic
 * @copyright   2020 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AllydeBundle\Tests\Beanstalk\Worker;

use Mautic\AllydeBundle\Beanstalk\SimpleInstance;
use Mautic\AllydeBundle\Beanstalk\Worker\CustomFieldWorker;
use Mautic\AllydeBundle\Entity\Job;
use Mautic\AllydeBundle\Helper\CommandStatus;
use Mautic\AllydeBundle\Helper\MysqlErrorHelper;
use Pheanstalk\Job as BeanJob;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CustomFieldWorkerTest extends TestCase
{
    /**
     * @var MockObject|SimpleInstance
     */
    private $simpleInstanceMock;

    /**
     * @var MockObject|LoggerInterface
     */
    private $loggerMock;

    /**
     * @var MockObject|Job
     */
    private $mauticJobMock;

    /**
     * @var MockObject|BeanJob
     */
    private $beanJobMock;

    /**
     * @var MockObject|MysqlErrorHelper
     */
    private $mysqlErrorHelperMock;

    /**
     * @var CustomFieldWorker
     */
    private $customFieldWorker;

    protected function setUp()
    {
        $this->simpleInstanceMock   = $this->createMock(SimpleInstance::class);
        $this->loggerMock           = $this->createMock(LoggerInterface::class);
        $this->mauticJobMock        = $this->createMock(Job::class);
        $this->beanJobMock          = $this->createMock(BeanJob::class);
        $this->mysqlErrorHelperMock = $this->createMock(MysqlErrorHelper::class);
        // Anonymous class that extends CustomFieldWorker and override the executeCommand method
        $this->customFieldWorker    = new class($this->simpleInstanceMock, $this->loggerMock, $this->mauticJobMock, $this->beanJobMock, $this->mysqlErrorHelperMock, 'test') extends CustomFieldWorker {
            public function executeCommand(string $command, string $arguments, $timeLimit = null, $env = []): CommandStatus
            {
                $commandStatus = new CommandStatus($command);
                $commandStatus->setExitCode(0);
                $commandStatus->setNote($command.$arguments);

                return $commandStatus;
            }
        };
    }

    public function testDeleteLeadColumn()
    {
        $data = [
            'leadFieldId' => 0,
            'userId'      => 0,
        ];
        $this->mauticJobMock
            ->expects($this->exactly(1))
            ->method('getData')
            ->willReturn($data);
        $this->loggerMock
            ->expects($this->exactly(1))
            ->method('info')
            ->with('CustomFieldWorker::deleteLeadColumn (0) success');
        $commandStatus = $this->customFieldWorker->deleteLeadColumn();
        $this->assertSame('mautic:custom-field:delete-column--id=0 --user=0', $commandStatus->getNote());
    }
}
