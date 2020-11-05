<?php

/*
 * @package     Mautic
 * @copyright   2020 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AllydeBundle\Tests\EventListener;

use Mautic\AllydeBundle\Beanstalk\Job\JobBuilder;
use Mautic\AllydeBundle\Beanstalk\JobManager;
use Mautic\AllydeBundle\Beanstalk\Tube\CustomFieldTube;
use Mautic\AllydeBundle\EventListener\LeadFieldColumnSubscriber;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Field\Event\DeleteColumnEvent;
use Mautic\LeadBundle\Field\Event\UpdateColumnEvent;
use Mautic\LeadBundle\Field\LeadFieldDeleter;
use Mautic\LeadBundle\Field\LeadFieldSaver;
use Mautic\LeadBundle\LeadEvents;
use Mautic\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LeadFieldColumnSubscriberTest extends TestCase
{
    /**
     * @var MockObject|LeadFieldSaver
     */
    private $leadFieldSaverMock;

    /**
     * @var MockObject|LeadFieldDeleter
     */
    private $leadFieldDeleterMock;

    /**
     * @var MockObject|JobManager
     */
    private $jobManagerMock;

    /**
     * @var MockObject|UserHelper
     */
    private $userHelperMock;

    /**
     * @var LeadFieldColumnSubscriber
     */
    private $leadFieldColumnSubscriber;

    protected function setUp()
    {
        $this->leadFieldSaverMock        = $this->createMock(LeadFieldSaver::class);
        $this->leadFieldDeleterMock      = $this->createMock(LeadFieldDeleter::class);
        $this->jobManagerMock            = $this->createMock(JobManager::class);
        $this->userHelperMock            = $this->createMock(UserHelper::class);
        $this->leadFieldColumnSubscriber = new LeadFieldColumnSubscriber(
            $this->leadFieldSaverMock,
            $this->leadFieldDeleterMock,
            $this->jobManagerMock,
            $this->userHelperMock
        );
    }

    public function testGetSubscribedEvents()
    {
        $subscribedEvents = LeadFieldColumnSubscriber::getSubscribedEvents();
        $this->assertIsArray($subscribedEvents);
        $this->assertArrayHasKey(LeadEvents::LEAD_FIELD_PRE_ADD_COLUMN, $subscribedEvents);
        $this->assertArrayHasKey(LeadEvents::LEAD_FIELD_PRE_UPDATE_COLUMN, $subscribedEvents);
        $this->assertArrayHasKey(LeadEvents::LEAD_FIELD_PRE_DELETE_COLUMN, $subscribedEvents);
    }

    public function testDeleteColumnNotInBackground()
    {
        $leadField      = new LeadField();
        $isInBackground = false;
        $event          = new DeleteColumnEvent($leadField, $isInBackground);
        $this->leadFieldDeleterMock
            ->expects($this->never())
            ->method('deleteLeadFieldEntityWithoutColumnRemoved');
        $this->leadFieldColumnSubscriber->deleteColumn($event);
    }

    public function testDeleteColumnInBackground()
    {
        $leadField      = new LeadField();
        $leadField->setId(42);
        $isInBackground = true;
        $event          = new DeleteColumnEvent($leadField, $isInBackground);
        $this->leadFieldDeleterMock
            ->expects($this->once())
            ->method('deleteLeadFieldEntityWithoutColumnRemoved')
            ->with($leadField);
        $userMock = $this->createMock(User::class);
        $userMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn(84);
        $this->userHelperMock
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($userMock);
        $job = JobBuilder::generateJob(
            CustomFieldTube::DELETE_LEAD_COLUMN,
            [
                'leadFieldId' => 42,
                'userId'      => 84,
                ]
        );
        $this->jobManagerMock
            ->expects($this->once())
            ->method('queueJob')
            ->with($job);
        $this->leadFieldColumnSubscriber->deleteColumn($event);
    }

    public function testUpdateColumnNotInBackground()
    {
        $leadField      = new LeadField();
        $isInBackground = false;
        $event          = new UpdateColumnEvent($leadField, $isInBackground);
        $this->userHelperMock
            ->expects($this->never())
            ->method('getUser');
        $this->leadFieldColumnSubscriber->updateColumn($event);
    }

    public function testUpdateColumnInBackground()
    {
        $leadField      = new LeadField();
        $leadField->setId(43);
        $isInBackground = true;
        $event          = new UpdateColumnEvent($leadField, $isInBackground);
        $userMock       = $this->createMock(User::class);
        $userMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn(86);
        $this->userHelperMock
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($userMock);
        $job = JobBuilder::generateJob(
            CustomFieldTube::UPDATE_LEAD_COLUMN,
            [
                'leadFieldId' => 43,
                'userId'      => 86,
            ]
        );
        $this->jobManagerMock
            ->expects($this->once())
            ->method('queueJob')
            ->with($job);
        $this->leadFieldColumnSubscriber->updateColumn($event);
    }
}
