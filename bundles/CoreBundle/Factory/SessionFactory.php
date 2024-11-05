<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Factory;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @see https://symfony.com/blog/new-in-symfony-5-3-session-service-deprecation
 */
class SessionFactory
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function __invoke(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
}
