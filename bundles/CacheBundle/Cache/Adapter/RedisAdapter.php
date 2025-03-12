<?php

declare(strict_types=1);

namespace Mautic\CacheBundle\Cache\Adapter;

use Symfony\Component\Cache\Adapter\RedisAdapter as SymfonyRedisAdapter;

class RedisAdapter extends SymfonyRedisAdapter
{
    use RedisAdapterTrait;

    /**
     * @param mixed[] $servers
     */
    public function __construct(array $servers, string $namespace, int $lifetime, bool $primaryOnly)
    {
        parent::__construct($this->createClient($servers, $primaryOnly), $namespace, $lifetime);
    }
}
