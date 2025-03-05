<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Twig\Extension;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ThemesExtension extends AbstractExtension
{
    public function __construct(private CoreParametersHelper $coreParametersHelper)
    {
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('theme_text_on_color', [$this, 'theme_text_on_color']),
            new TwigFunction('theme_text_on_color__helper', [$this, 'theme_text_on_color__helper']),
            new TwigFunction('getBrandPrimaryColor', [$this, 'getBrandPrimaryColor']),
        ];
    }

    public function getBrandPrimaryColor(): string
    {
        return $this->coreParametersHelper->get('primary_brand_color', '000000');
    }

    public function theme_text_on_color(): string
    {
        $primaryColor = $this->getBrandPrimaryColor();

        $r = hexdec(substr($primaryColor, 0, 2));
        $g = hexdec(substr($primaryColor, 2, 2));
        $b = hexdec(substr($primaryColor, 4, 2));

        // Calculate perceived brightness
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;

        // Determine text color based on brightness threshold
        return $brightness > 125 ? '000000' : 'ffffff';
    }

    public function theme_text_on_color__helper(): string
    {
        return '000000' === $this->theme_text_on_color() ? '6d6d6d' : 'b3b3b3';
    }
}
