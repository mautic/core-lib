<?php

namespace Mautic\CampaignBundle\Tests\Model;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Model\CampaignModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CampaignModelTransactionalTest extends TestCase
{
    private MockObject $entityManagerMock;
    private MockObject $connectionMock;
    private CampaignModel $model;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);

        $this->entityManagerMock = $this->createMock(EntityManager::class);
        $this->entityManagerMock->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->connectionMock);
    }

    public function testTransactionalCampaignUnPublish(): void
    {
        $campaignMock = $this->createMock(Campaign::class);
        $campaignId   = 5;
        $campaignMock->expects($this->once())
            ->method('getId')
            ->willReturn($campaignId);

        $campaignEntityMock = $this->createMock(Campaign::class);

        // Setup the entity manager mock to find the campaign
        $this->entityManagerMock->expects($this->once())
            ->method('find')
            ->with(Campaign::class, $campaignId)
            ->willReturn($campaignEntityMock);

        // Transaction handling
        $this->connectionMock->expects($this->once())
            ->method('beginTransaction');

        $this->connectionMock->expects($this->once())
            ->method('commit');

        $this->connectionMock->expects($this->never())
            ->method('rollBack');

        $this->connectionMock->expects($this->never())
            ->method('close');

        // Locking the entity
        $this->entityManagerMock->expects($this->once())
            ->method('lock')
            ->with($campaignEntityMock, LockMode::PESSIMISTIC_WRITE);

        // Setting published flag
        $campaignEntityMock->expects($this->once())
            ->method('setIsPublished')
            ->with(false);

        // Set up expectation for saveEntity
        $this->model = $this->getMockBuilder(CampaignModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['saveEntity'])
            ->getMock();

        $reflection = new \ReflectionClass(CampaignModel::class);
        $property   = $reflection->getProperty('em');
        $property->setValue($this->model, $this->entityManagerMock);

        // Saving the entity
        $this->model->expects($this->once())
            ->method('saveEntity')
            ->with($campaignEntityMock);

        $this->model->transactionalCampaignUnPublish($campaignMock);
    }

    public function testTransactionalCampaignUnPublishWithException(): void
    {
        $this->expectException(\Exception::class);

        $campaignMock = $this->createMock(Campaign::class);
        $campaignId   = 5;
        $campaignMock->expects($this->once())
            ->method('getId')
            ->willReturn($campaignId);

        $campaignEntityMock = $this->createMock(Campaign::class);

        // Setup the entity manager mock to find the campaign
        $this->entityManagerMock->expects($this->once())
            ->method('find')
            ->with(Campaign::class, $campaignId)
            ->willReturn($campaignEntityMock);

        // Transaction handling
        $this->connectionMock->expects($this->once())
            ->method('beginTransaction');

        $this->connectionMock->expects($this->never())
            ->method('commit');

        $this->connectionMock->expects($this->once())
            ->method('rollBack');

        $this->connectionMock->expects($this->once())
            ->method('close');

        // Locking the entity
        $this->entityManagerMock->expects($this->once())
            ->method('lock')
            ->with($campaignEntityMock, LockMode::PESSIMISTIC_WRITE);

        // Setting published flag
        $campaignEntityMock->expects($this->once())
            ->method('setIsPublished')
            ->with(false);

        // Set up expectation for saveEntity
        $this->model = $this->getMockBuilder(CampaignModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['saveEntity'])
            ->getMock();

        $reflection = new \ReflectionClass(CampaignModel::class);
        $property   = $reflection->getProperty('em');
        $property->setValue($this->model, $this->entityManagerMock);

        // Saving the entity throws an exception
        $this->model->expects($this->once())
            ->method('saveEntity')
            ->with($campaignEntityMock)
            ->willThrowException(new \Exception('Database error'));

        $this->model->transactionalCampaignUnPublish($campaignMock);
    }
}
