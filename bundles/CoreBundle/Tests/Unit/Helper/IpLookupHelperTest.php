<?php

namespace Mautic\CoreBundle\Tests\Unit\Helper;

use DeviceDetector\DeviceDetector;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Entity\IpAddressRepository;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\LeadBundle\Tracker\Factory\DeviceDetectorFactory\DeviceDetectorFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[\PHPUnit\Framework\Attributes\CoversClass(IpLookupHelper::class)]
class IpLookupHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DeviceDetector|(DeviceDetector&object&\PHPUnit\Framework\MockObject\MockObject)|(DeviceDetector&\PHPUnit\Framework\MockObject\MockObject)|(object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject
     */
    private DeviceDetector|\PHPUnit\Framework\MockObject\MockObject $deviceDetector;

    private DeviceDetectorFactoryInterface|\PHPUnit\Framework\MockObject\MockObject $deviceDetectorFactory;

    protected function setUp(): void
    {
        $this->deviceDetectorFactory = $this->createMock(DeviceDetectorFactoryInterface::class);
        $this->deviceDetector        = $this->createMock(DeviceDetector::class);

        defined('MAUTIC_ENV') or define('MAUTIC_ENV', 'test');
    }

    public function testDeviceDetectorBotsDetectionTrue(): void
    {
        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '73.77.245.52']);

        $this->deviceDetector
            ->method('isBot')
            ->willReturn(true);

        $ip = $this->getIpHelper($request);
        $this->assertFalse($ip->getIpAddress()->isTrackable());
    }

    public function testDeviceDetectorBotsDetectionFalse(): void
    {
        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '73.77.245.53']);

        $this->deviceDetector
            ->method('isBot')
            ->willReturn(false);

        $ip = $this->getIpHelper($request);
        $this->assertTrue($ip->getIpAddress()->isTrackable());
    }

    #[\PHPUnit\Framework\Attributes\TestDox('Check if IP outside a request that local IP is returned')]
    public function testLocalIpIsReturnedWhenNotInRequestScope(): void
    {
        $ip = $this->getIpHelper()->getIpAddress();

        $this->assertEquals('127.0.0.1', $ip->getIpAddress());
    }

    #[\PHPUnit\Framework\Attributes\TestDox('Check that the first IP is returned when the request is a proxy')]
    public function testClientIpIsReturnedFromProxy(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_X_FORWARDED_FOR' => '73.77.245.52,10.8.0.2,192.168.0.1']);
        $ip      = $this->getIpHelper($request)->getIpAddress();

        $this->assertEquals('73.77.245.52', $ip->getIpAddress());
    }

    #[\PHPUnit\Framework\Attributes\TestDox('Check that the first IP is returned with a web proxy')]
    public function testClientIpIsReturnedFromRequest(): void
    {
        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '73.77.245.53']);
        $ip      = $this->getIpHelper($request)->getIpAddress();

        $this->assertEquals('73.77.245.53', $ip->getIpAddress());
    }

    #[\PHPUnit\Framework\Attributes\TestDox('Check that a local IP is returned for internal IPs')]
    public function testLocalIpIsReturnedForInternalNetworkIp(): void
    {
        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '192.168.0.1']);
        $ip      = $this->getIpHelper($request)->getIpAddress();

        $this->assertEquals('127.0.0.1', $ip->getIpAddress());
    }

    #[\PHPUnit\Framework\Attributes\TestDox('Check that internal IP is returned if track_private_ip_ranges is set to true')]
    public function testInternalNetworkIpIsReturnedIfSetToTrack(): void
    {
        $request                  = new Request([], [], [], [], [], ['REMOTE_ADDR' => '192.168.0.1']);
        $mockCoreParametersHelper = $this->createMock(CoreParametersHelper::class);
        $mockCoreParametersHelper->expects($this->any())
            ->method('get')
            ->willReturnCallback(
                fn ($param, $defaultValue) => 'track_private_ip_ranges' === $param ? true : $defaultValue
            );
        $ip = $this->getIpHelper($request, $mockCoreParametersHelper)->getIpAddress();

        $this->assertEquals('192.168.0.1', $ip->getIpAddress());
    }

    /**
     * @return IpLookupHelper
     */
    private function getIpHelper($request = null, $mockCoreParametersHelper = null)
    {
        $requestStack = new RequestStack();

        if ($request) {
            $requestStack->push($request);
        }

        $mockRepository = $this->createMock(IpAddressRepository::class);
        $mockRepository->expects($this->any())
            ->method('__call')
            ->with($this->equalTo('findOneByIpAddress'))
            ->willReturn(null);

        $mockEm = $this->createMock(EntityManager::class);
        $mockEm->expects($this->any())
            ->method('getRepository')
            ->willReturn($mockRepository);

        if (is_null($mockCoreParametersHelper)) {
            $mockCoreParametersHelper = $this->createMock(CoreParametersHelper::class);
            $mockCoreParametersHelper->expects($this->any())
                ->method('get')
                ->willReturn(null);
        }

        $this->deviceDetectorFactory->expects($this->any())
            ->method('create')
            ->willReturnCallback(
                function () {
                    return $this->deviceDetector;
                }
            );

        return new IpLookupHelper($requestStack, $mockEm, $mockCoreParametersHelper, null, $this->deviceDetectorFactory);
    }
}
