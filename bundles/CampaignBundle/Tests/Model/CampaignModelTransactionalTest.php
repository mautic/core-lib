<?php

namespace Mautic\CampaignBundle\Tests\Model;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\CampaignRepository;
use Mautic\CampaignBundle\EventCollector\EventCollector;
use Mautic\CampaignBundle\Membership\MembershipBuilder;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\FormBundle\Model\FormModel;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\LeadBundle\Tracker\ContactTracker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CampaignModelTransactionalTest extends TestCase
{
    private MockObject $entityManagerMock;
    private MockObject $connectionMock;
    private MockObject $userHelperMock;
    private MockObject $campaignRepositoryMock;
    private MockObject $campaignModel;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);

        // Create repository mock
        $this->campaignRepositoryMock = $this->createMock(CampaignRepository::class);
        $this->campaignRepositoryMock->method('setCurrentUser')
            ->willReturnSelf();

        $this->entityManagerMock = $this->createMock(EntityManager::class);
        $this->entityManagerMock->method('getConnection')
            ->willReturn($this->connectionMock);

        $this->entityManagerMock->method('getRepository')
            ->with(Campaign::class)
            ->willReturn($this->campaignRepositoryMock);

        // Mock user helper
        $this->userHelperMock = $this->createMock(UserHelper::class);

        // Create all the required dependencies as mocks
        $leadListModel        = $this->createMock(ListModel::class);
        $formModel            = $this->createMock(FormModel::class);
        $eventCollector       = $this->createMock(EventCollector::class);
        $membershipBuilder    = $this->createMock(MembershipBuilder::class);
        $contactTracker       = $this->createMock(ContactTracker::class);
        $security             = $this->createMock(CorePermissions::class);
        $dispatcher           = $this->createMock(EventDispatcherInterface::class);
        $router               = $this->createMock(UrlGeneratorInterface::class);
        $translator           = $this->createMock(Translator::class);
        $logger               = $this->createMock(LoggerInterface::class);
        $coreParametersHelper = $this->createMock(CoreParametersHelper::class);

        // Create the campaign model mock
        $this->campaignModel = $this->getMockBuilder(CampaignModel::class)
            ->setConstructorArgs([
                $leadListModel,
                $formModel,
                $eventCollector,
                $membershipBuilder,
                $contactTracker,
                $this->entityManagerMock,
                $security,
                $dispatcher,
                $router,
                $translator,
                $this->userHelperMock,
                $logger,
                $coreParametersHelper,
            ])
            ->onlyMethods(['saveEntity'])
            ->getMock();
    }

    public function testTransactionalCampaignUnPublish(): void
    {
        $campaignMock = $this->createMock(Campaign::class);
        $campaignId   = 5;
        $campaignMock->expects($this->once())
            ->method('getId')
            ->willReturn($campaignId);

        // Mock version data from repository
        $this->campaignRepositoryMock->expects($this->once())
            ->method('getCampaignPublishAndVersionData')
            ->with($campaignId)
            ->willReturn([
                'is_published' => 1,
                'version'      => 1,
            ]);

        $campaignMock->expects($this->once())
            ->method('getVersion')
            ->willReturn(1);

        // Setting published flag
        $campaignMock->expects($this->once())
            ->method('setIsPublished')
            ->with(false);

        $campaignMock->expects($this->once())
            ->method('markForVersionIncrement');

        // Saving the entity
        $this->campaignModel->expects($this->once())
            ->method('saveEntity')
            ->with($campaignMock);

        $this->campaignModel->transactionalCampaignUnPublish($campaignMock);
    }

    public function testTransactionalCampaignUnPublishWithException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $campaignMock = $this->createMock(Campaign::class);
        $campaignId   = 5;
        $campaignMock->expects($this->once())
            ->method('getId')
            ->willReturn($campaignId);

        // Mock version data from repository
        $this->campaignRepositoryMock->expects($this->once())
            ->method('getCampaignPublishAndVersionData')
            ->with($campaignId)
            ->willReturn([
                'is_published' => 1,
                'version'      => 1,
            ]);

        $campaignMock->expects($this->once())
            ->method('getVersion')
            ->willReturn(1);

        // Setting published flag
        $campaignMock->expects($this->once())
            ->method('setIsPublished')
            ->with(false);

        $campaignMock->expects($this->once())
            ->method('markForVersionIncrement');

        // Saving the entity throws an exception
        $this->campaignModel->expects($this->once())
            ->method('saveEntity')
            ->with($campaignMock)
            ->willThrowException(new \Exception('Database error'));

        $this->campaignModel->transactionalCampaignUnPublish($campaignMock);
    }
}
