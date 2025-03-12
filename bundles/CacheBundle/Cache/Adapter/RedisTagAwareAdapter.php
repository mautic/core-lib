<?php

declare(strict_types=1);

namespace Mautic\CacheBundle\Cache\Adapter;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class RedisTagAwareAdapter extends TagAwareAdapter
{
    use RedisAdapterTrait;

    /**
     * @param mixed[] $servers
     */
    public function __construct(array $servers, string $namespace, int $lifetime, bool $primaryOnly)
    {
        $client = $this->createClient($servers, $primaryOnly);

        parent::__construct(
            new RedisAdapter($client, $namespace, $lifetime),
            new RedisAdapter($client, $namespace.'.tags.', $lifetime)
        );
    }
}
