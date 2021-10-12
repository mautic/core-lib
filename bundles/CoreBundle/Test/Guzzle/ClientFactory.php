<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Test\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Http\Adapter\Guzzle6\Client as ClientAdapter;
use Mautic\EmailBundle\Swiftmailer\Guzzle\ClientFactoryInterface;

class ClientFactory
{
    public static function stub(MockHandler $handler): ClientInterface
    {
        return self::createClient($handler);
    }

    public static function stubFactory(MockHandler $handler): ClientFactoryInterface
    {
        return new class(self::createClient($handler)) implements ClientFactoryInterface {
            /**
             * @var Client
             */
            private $client;

            public function __construct(Client $client)
            {
                $this->client = $client;
            }

            public function create(ClientInterface $client = null): ClientAdapter
            {
                return new ClientAdapter($this->client);
            }
        };
    }

    private static function createClient(MockHandler $handler): Client
    {
        return new Client(['handler' => HandlerStack::create($handler)]);
    }
}
