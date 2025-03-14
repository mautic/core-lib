<?php
<?php

declare(strict_types=1);

namespace Mautic\PointBundle\Tests\Unit\Model;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\PointBundle\Entity\Point;
use Mautic\PointBundle\Entity\PointRepository;
use Mautic\PointBundle\Model\PointGroupModel;
use Mautic\PointBundle\Model\PointModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PointModelTest extends TestCase
{
    private PointModel $pointModel;
    private PointRepository $pointRepository;
    private IpLookupHelper $ipLookupHelper;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock for the repository
        $this->pointRepository = $this->createMock(PointRepository::class);
        
        // Create mock for IP lookup helper
        $this->ipLookupHelper = $this->createMock(IpLookupHelper::class);
        
        // Create all other required mocks
        $requestStack = $this->createMock(RequestStack::class);
        $leadModel = $this->createMock(LeadModel::class);
        $mauticFactory = $this->createMock(MauticFactory::class);
        $contactTracker = $this->createMock(ContactTracker::class);
        $em = $this->createMock(EntityManager::class);
        $security = $this->createMock(CorePermissions::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $translator = $this->createMock(Translator::class);
        $userHelper = $this->createMock(UserHelper::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $coreParametersHelper = $this->createMock(CoreParametersHelper::class);
        $pointGroupModel = $this->createMock(PointGroupModel::class);

        // Create a partial mock of the PointModel class
        $this->pointModel = $this->getMockBuilder(PointModel::class)
            ->setConstructorArgs([
                $requestStack,
                $this->ipLookupHelper,
                $leadModel,
                $mauticFactory,
                $contactTracker,
                $em,
                $security,
                $dispatcher,
                $router,
                $translator,
                $userHelper,
                $logger,
                $coreParametersHelper,
                $pointGroupModel
            ])
            ->onlyMethods(['getRepository'])
            ->getMock();
        
        // Configure the getRepository method to return our repository mock
        $this->pointModel->method('getRepository')
            ->willReturn($this->pointRepository);
    }

    public function testTriggerActionReturnsEarlyWhenNoAvailablePoints(): void
    {
        // Arrange
        $type = 'email.send';
        $lead = new Lead();
        
        // Configure repository to return empty array for getPublishedByType
        $this->pointRepository->expects($this->once())
            ->method('getPublishedByType')
            ->with($type)
            ->willReturn([]);
            
        // IP lookup helper should not be called if we return early
        $this->ipLookupHelper->expects($this->never())
            ->method('getIpAddress');
            
        // Act
        $this->pointModel->triggerAction($type, null, null, $lead);
        
        // Assert is handled by expectations on mocks - test passes if ipLookupHelper is never called
    }

    public function testTriggerActionContinuesWhenPointsAreAvailable(): void
    {
        // Arrange
        $type = 'email.send';
        $lead = new Lead();
        $point = $this->createMock(Point::class);
        
        // Configure repository to return a point for getPublishedByType
        $this->pointRepository->expects($this->once())
            ->method('getPublishedByType')
            ->with($type)
            ->willReturn([$point]);
            
        // IP lookup helper should be called when points are available
        $this->ipLookupHelper->expects($this->once())
            ->method('getIpAddress');
            
        // Act - wrap in try/catch since we're not mocking all dependencies
        try {
            $this->pointModel->triggerAction($type, null, null, $lead);
        } catch (\Exception $e) {
            // We're only testing that getPublishedByType is called and execution continues to getIpAddress
            // Other errors in the method execution are not relevant for this test
        }
        
        // Assert is handled by mock expectations
    }
}
