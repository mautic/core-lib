<?php

declare(strict_types=1);

namespace Mautic\PluginBundle\Tests\Helper;

use Mautic\PluginBundle\Helper\oAuthHelper;
use PHPUnit\Framework\TestCase;

final class oAuthHelperTest extends TestCase
{
    public function testHashSensitiveHeaderData()
    {
        $headers = [
            'Authorization: Bearer SME-ASA',
        ];

        $hashedHeaders = oAuthHelper::hashSensitiveHeaderData($headers);

        $this->assertStringContainsString('Authorization: Bearer ', $hashedHeaders[0]);
        $this->assertMatchesRegularExpression('/Authorization: Bearer [a-f0-9]{64}/', $hashedHeaders[0]);
    }
}
