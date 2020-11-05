<?php

/*
 * @package     Mautic
 * @copyright   2020 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Tests\Field\Dispatcher;

use Doctrine\ORM\EntityManager;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Event\LeadFieldEvent;
use Mautic\LeadBundle\Field\Dispatcher\FieldDeleteDispatcher;
use Mautic\LeadBundle\Field\Exception\AbortColumnUpdateException;
use Mautic\LeadBundle\Field\Settings\BackgroundSettings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FieldDeleteDispatcherTest extends TestCase
{
    /**
     * @var MockObject|EventDispatcherInterface
     */
    private $dispatcherMock;

    /**
     * @var MockObject|EntityManager
     */
    private $entityManagerMock;

    /**
     * @var MockObject|BackgroundSettings
     */
    private $backgroundSettingsMock;

    /**
     * @var FieldDeleteDispatcher
     */
    private $fieldDeleteDispatcher;

    protected function setUp()
    {
        $this->dispatcherMock         = $this->createMock(EventDispatcherInterface::class);
        $this->entityManagerMock      = $this->createMock(EntityManager::class);
        $this->backgroundSettingsMock = $this->createMock(BackgroundSettings::class);
        $this->fieldDeleteDispatcher  = new FieldDeleteDispatcher(
            $this->dispatcherMock,
            $this->entityManagerMock,
            $this->backgroundSettingsMock
        );
    }

    public function testDispatchPreDeleteEventInBackground()
    {
        $this->backgroundSettingsMock
            ->expects($this->once())
            ->method('shouldProcessColumnChangeInBackground')
            ->willReturn(true);
        $leadField = new LeadField();

        $this->expectException(AbortColumnUpdateException::class);
        $this->expectExceptionMessage('Column change will be processed in background job');

        $this->fieldDeleteDispatcher->dispatchPreDeleteEvent($leadField);
    }

    public function testDispatchPreDeleteEventNow()
    {
        $this->backgroundSettingsMock
            ->expects($this->once())
            ->method('shouldProcessColumnChangeInBackground')
            ->willReturn(false);
        $leadField = new LeadField();
        $this->dispatcherMock
            ->expects($this->once())
            ->method('hasListeners')
            ->willReturn(true);
        $this->dispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                'mautic.lead_field_pre_delete',
                $this->callback(function ($event) {
                    return $event instanceof LeadFieldEvent;
                })
            );
        $this->fieldDeleteDispatcher->dispatchPreDeleteEvent($leadField);
    }
}
