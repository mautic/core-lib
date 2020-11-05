<?php

/*
 * @package     Mautic
 * @copyright   2020 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Tests\Field;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Exception\NoListenerException;
use Mautic\LeadBundle\Field\Dispatcher\FieldDeleteDispatcher;
use Mautic\LeadBundle\Field\Exception\AbortColumnUpdateException;
use Mautic\LeadBundle\Field\LeadFieldDeleter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LeadFieldDeleterTest extends TestCase
{
    /**
     * @var MockObject|LeadFieldRepository
     */
    private $leadFieldRepositoryMock;

    /**
     * @var MockObject|FieldDeleteDispatcher
     */
    private $fieldDeleteDispatcherMock;

    /**
     * @var LeadFieldDeleter
     */
    private $leadFieldDeleter;

    protected function setUp()
    {
        $this->leadFieldRepositoryMock   = $this->createMock(LeadFieldRepository::class);
        $this->fieldDeleteDispatcherMock = $this->createMock(FieldDeleteDispatcher::class);
        $this->leadFieldDeleter          = new LeadFieldDeleter(
            $this->leadFieldRepositoryMock,
            $this->fieldDeleteDispatcherMock
        );
    }

    public function testDeleteLeadFieldEntityNoBackground()
    {
        $leadField = new LeadField();
        $this->fieldDeleteDispatcherMock
            ->expects($this->once())
            ->method('dispatchPreDeleteEvent')
            ->with($leadField)
            ->willThrowException(new AbortColumnUpdateException());
        $this->leadFieldRepositoryMock
            ->expects($this->never())
            ->method('deleteEntity');
        $this->leadFieldDeleter->deleteLeadFieldEntity($leadField, false);
    }

    public function testDeleteLeadFieldEntityInBackground()
    {
        $leadField = new LeadField();
        $this->fieldDeleteDispatcherMock
            ->expects($this->once())
            ->method('dispatchPreDeleteEvent')
            ->with($leadField)
            ->willThrowException(new AbortColumnUpdateException());
        $this->leadFieldRepositoryMock
            ->expects($this->once())
            ->method('deleteEntity')
            ->with($leadField);
        $this->fieldDeleteDispatcherMock
            ->expects($this->once())
            ->method('dispatchPostDeleteEvent')
            ->with($leadField)
            ->willThrowException(new NoListenerException());
        $this->leadFieldDeleter->deleteLeadFieldEntity($leadField, true);
    }
}
