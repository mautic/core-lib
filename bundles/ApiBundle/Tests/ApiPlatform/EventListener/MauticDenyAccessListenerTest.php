<?php

declare(strict_types=1);

namespace Mautic\ApiBundle\Tests\ApiPlatform\EventListener;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Mautic\ApiBundle\ApiPlatform\EventListener\MauticDenyAccessListener;
use Mautic\CoreBundle\Entity\FormEntity;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class MauticDenyAccessListenerTest extends TestCase
{
    /**
     * @var MockObject|CorePermissions
     */
    private $corePermissionsMock;

    /**
     * @var ResourceMetadata
     */
    private $resourceMetadata;

    /**
     * @var MockObject|ResourceMetadataFactoryInterface
     */
    private $resourceMetadataFactoryMock;

    /**
     * @var MockObject|ResourceMetadataFactoryInterface
     */
    private $eventMock;

    /**
     * @var MauticDenyAccessListener
     */
    private $mauticDenyAccessListener;

    protected function setUp(): void
    {
        $attributes = [
            '_api_resource_class'      => 'TestClass',
            '_api_item_operation_name' => 'Test',
            'item_operation_name'      => 'Test',
        ];
        $parameterBagMock = $this->createMock(ParameterBag::class);
        $parameterBagMock
            ->expects($this->exactly(1))
            ->method('all')
            ->willReturn($attributes);
        $formEntityMock = $this->createMock(FormEntity::class);
        $formEntityMock
            ->expects($this->atMost(1))
            ->method('getCreatedBy')
            ->willReturn(0);
        $parameterBagMock
            ->expects($this->exactly(1))
            ->method('get')
            ->with('data')
            ->willReturn($formEntityMock);
        $requestMock                       = $this->createMock(Request::class);
        $requestMock->attributes           = $parameterBagMock;
        $this->corePermissionsMock         = $this->createMock(CorePermissions::class);
        $this->resourceMetadataFactoryMock = $this->createMock(ResourceMetadataFactoryInterface::class);
        $this->eventMock                   = $this->createMock(GetResponseEvent::class);
        $this->eventMock
            ->expects($this->exactly(1))
            ->method('getRequest')
            ->willReturn($requestMock);
        $this->mauticDenyAccessListener = new MauticDenyAccessListener($this->corePermissionsMock, $this->resourceMetadataFactoryMock);
    }

    public function testOnSecurityEntityAccessAllowed(): void
    {
        $operations = [
            'Test' => [
                'security' => '"test_item:edit"',
            ],
        ];
        $this->resourceMetadata = new ResourceMetadata(null, null, null, $operations);
        $this->resourceMetadataFactoryMock
            ->expects($this->exactly(1))
            ->method('create')
            ->with('TestClass')
            ->willReturn($this->resourceMetadata);
        $this->corePermissionsMock
            ->expects($this->exactly(1))
            ->method('hasEntityAccess')
            ->with('test_item:editown', 'test_item:editother', 0)
            ->willReturn(true);
        $this->mauticDenyAccessListener->onSecurity($this->eventMock);
    }

    public function testOnSecurityIsGranted(): void
    {
        $operations = [
            'Test' => [
                'security' => '"test_item:write"',
            ],
        ];
        $this->resourceMetadata = new ResourceMetadata(null, null, null, $operations);
        $this->resourceMetadataFactoryMock
            ->expects($this->exactly(1))
            ->method('create')
            ->with('TestClass')
            ->willReturn($this->resourceMetadata);
        $this->corePermissionsMock
            ->expects($this->exactly(1))
            ->method('isGranted')
            ->with('test_item:write')
            ->willReturn(true);
        $this->mauticDenyAccessListener->onSecurity($this->eventMock);
    }

    public function testOnSecurityAccessDenied(): void
    {
        $operations = [
            'Test' => [
                'security' => '"test_item:write"',
            ],
        ];
        $this->resourceMetadata = new ResourceMetadata(null, null, null, $operations);
        $this->resourceMetadataFactoryMock
            ->expects($this->exactly(1))
            ->method('create')
            ->with('TestClass')
            ->willReturn($this->resourceMetadata);
        $this->corePermissionsMock
            ->expects($this->exactly(1))
            ->method('isGranted')
            ->with('test_item:write')
            ->willReturn(false);
        $this->expectException(AccessDeniedException::class);
        $this->mauticDenyAccessListener->onSecurity($this->eventMock);
    }
}
