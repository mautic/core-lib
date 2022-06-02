<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Test\Guzzle;

use function assert;
use GuzzleHttp\Handler\MockHandler;

trait ClientMockTrait
{
    private function getClientMockHandler(): MockHandler
    {
        $clientMockHandler = self::$container->get('mautic.http.client.mock_handler');
        assert($clientMockHandler instanceof MockHandler);

        return $clientMockHandler;
    }
}
