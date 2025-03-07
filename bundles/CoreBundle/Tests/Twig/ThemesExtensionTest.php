<?php

namespace Mautic\CoreBundle\Tests\Twig;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Twig\Extension\ThemesExtension;
use PHPUnit\Framework\TestCase;

class ThemesExtensionTest extends TestCase
{
    private CoreParametersHelper $coreParametersHelper;
    private ThemesExtension $themesExtension;

    protected function setUp(): void
    {
        $this->coreParametersHelper = $this->createMock(CoreParametersHelper::class);
        $this->themesExtension      = new ThemesExtension($this->coreParametersHelper);
    }

    public function testGetBrandPrimaryColor(): void
    {
        $this->coreParametersHelper->method('get')
            ->with('primary_brand_color', '000000')
            ->willReturn('123456');

        $this->assertEquals('123456', $this->themesExtension->getBrandPrimaryColor());
    }

    public function testGetTextOnBrandColor(): void
    {
        $this->coreParametersHelper->method('get')
            ->with('primary_brand_color', '000000')
            ->willReturn('000000');

        $this->assertEquals('ffffff', $this->themesExtension->getTextOnBrandColor());

        $this->coreParametersHelper->method('get')
            ->with('primary_brand_color', '000000')
            ->willReturn('ffffff');

        $this->assertEquals('000000', $this->themesExtension->getTextOnBrandColor());
    }

    public function testGetTextOnBrandHelperColor(): void
    {
        // First call: primary color is '000000'
        $this->coreParametersHelper->method('get')
            ->with('primary_brand_color', '000000')
            ->willReturnOnConsecutiveCalls('000000', 'ffffff');

        // First assertion: text color should be 'ffffff', helper color should be 'b3b3b3'
        $this->assertEquals('b3b3b3', $this->themesExtension->getTextOnBrandHelperColor());

        // Second assertion: text color should be '000000', helper color should be '6d6d6d'
        $this->assertEquals('6d6d6d', $this->themesExtension->getTextOnBrandHelperColor());
    }

    public function testGetRoundedCorners(): void
    {
        $this->coreParametersHelper->method('get')
            ->with('rounded_corners', 0)
            ->willReturn(16);

        $this->assertEquals(16, $this->themesExtension->getRoundedCorners('lg'));
        $this->assertEquals(6, $this->themesExtension->getRoundedCorners('md'));
        $this->assertEquals(4, $this->themesExtension->getRoundedCorners('sm'));

        $this->coreParametersHelper->method('get')
            ->with('rounded_corners', 0)
            ->willReturn(8);

        $this->assertEquals(8, $this->themesExtension->getRoundedCorners('lg'));
        $this->assertEquals(4, $this->themesExtension->getRoundedCorners('md'));
        $this->assertEquals(3, $this->themesExtension->getRoundedCorners('sm'));

        $this->coreParametersHelper->method('get')
            ->with('rounded_corners', 0)
            ->willReturn(32);

        $this->assertEquals(32, $this->themesExtension->getRoundedCorners('lg'));
        $this->assertEquals(8, $this->themesExtension->getRoundedCorners('md'));
        $this->assertEquals(5, $this->themesExtension->getRoundedCorners('sm'));
    }
}
