<?php

declare(strict_types=1);

namespace Mautic\ApiBundle\Tests\ApiPlatform\EventListener;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use Mautic\ApiBundle\ApiPlatform\EventListener\MauticWriteSubscriber;
use Mautic\CoreBundle\Entity\FormEntity;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\UserBundle\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class MauticWriteSubscriberTest extends TestCase
{
    /**
     * @var MauticWriteSubscriber
     */
    private $mauticWriteSubscriber;

    /**
     * @var MockObject|ViewEvent
     */
    private $eventMock;

    /**
     * @var MockObject|FormEntity
     */
    private $formEntityMock;

    /**
     * @var MockObject|Request
     */
    private $requestMock;

    /**
     * @var MockObject|UserHelper
     */
    private $userHelperMock;

    protected function setUp(): void
    {
        $this->userHelperMock        = $this->createMock(UserHelper::class);
        $this->mauticWriteSubscriber = new MauticWriteSubscriber($this->userHelperMock);
        $this->requestMock           = $this->createMock(Request::class);
        $this->formEntityMock        = $this->createMock(FormEntity::class);
        $kernelMock                  = $this->createMock(HttpKernelInterface::class);
        $this->eventMock             = $this->getMockBuilder(ViewEvent::class)
            ->setConstructorArgs([
                $kernelMock,
                $this->requestMock,
                HttpKernelInterface::MAIN_REQUEST,
                'controllerResult',
            ])
            ->getMock();
    }

    private function setMocks(): void
    {
        $this->eventMock
            ->expects($this->exactly(1))
            ->method('getRequest')
            ->willReturn($this->requestMock);
        $this->eventMock
            ->expects($this->exactly(1))
            ->method('getControllerResult')
            ->willReturn($this->formEntityMock);
    }

    public function testGetSubscribedEvents(): void
    {
        $expected = [
            'kernel.view'=> ['addData', EventPriorities::PRE_WRITE],
        ];
        $this->assertEquals($expected, MauticWriteSubscriber::getSubscribedEvents());
    }

    public function testAddDataWithWrongMethod(): void
    {
        $this->setMocks();
        $this->requestMock
            ->expects($this->exactly(1))
            ->method('getMethod')
            ->willReturn('GET');
        $this->formEntityMock
            ->expects($this->never())
            ->method('isNew');
        $this->mauticWriteSubscriber->addData($this->eventMock);
    }

    public function testAddData(): void
    {
        $this->setMocks();
        $this->requestMock
            ->expects($this->exactly(1))
            ->method('getMethod')
            ->willReturn('POST');
        $this->formEntityMock
            ->expects($this->exactly(1))
            ->method('isNew')
            ->willReturn(false);
        $userMock = $this->createMock(User::class);
        $userMock
            ->expects($this->exactly(1))
            ->method('getName')
            ->willReturn('Pepa');
        $this->userHelperMock
            ->expects($this->exactly(1))
            ->method('getUser')
            ->willReturn($userMock);
        $this->formEntityMock
            ->expects($this->exactly(1))
            ->method('setModifiedBy')
            ->with($userMock);
        $this->formEntityMock
            ->expects($this->exactly(1))
            ->method('setModifiedByUser')
            ->with('Pepa');
        $this->formEntityMock
            ->expects($this->exactly(1))
            ->method('setDateModified')
            ->withAnyParameters();
        $this->mauticWriteSubscriber->addData($this->eventMock);
    }
}
