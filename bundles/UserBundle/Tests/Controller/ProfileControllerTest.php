<?php

declare(strict_types=1);

namespace Mautic\UserBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\UserBundle\Tests\Traits\CreateEntityTrait;
use Symfony\Component\HttpFoundation\Request;

class ProfileControllerTest extends MauticMysqlTestCase
{
    use CreateEntityTrait;
    use LoginUserWithSamlTrait;

    protected function setUp(): void
    {
        if (strpos($this->name(), 'WithSaml') > 0) {
            $this->configParams['saml_idp_metadata'] = 'PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48bWQ6RW50aXR5RGVzY3JpcHRvciB4bWxuczptZD0idXJuOm9hc2lzOm5hbWVzOnRjOlNBTUw6Mi4wOm1ldGFkYXRhIiBlbnRpdHlJRD0iaHR0cHM6Ly9tYXV0aWMtZGV2LWVkLm15LnNhbGVzZm9yY2UuY29tIiB2YWxpZFVudGlsPSIyMDI5LTEyLTI4VDE0OjUyOjA2LjIyMFoiIHhtbG5zOmRzPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwLzA5L3htbGRzaWcjIj4KICAgPG1kOklEUFNTT0Rlc2NyaXB0b3IgcHJvdG9jb2xTdXBwb3J0RW51bWVyYXRpb249InVybjpvYXNpczpuYW1lczp0YzpTQU1MOjIuMDpwcm90b2NvbCI';
        }
        parent::setUp();
    }

    public function testPasswordNotOnAccountPageWithSaml(): void
    {
        $user = $this->createUser($this->createRole(), 'test@example.com');
        $this->em->flush();
        $this->em->clear();

        $this->loginUserWithSaml($user);

        $this->client->request(Request::METHOD_GET, 's/account');

        $clientResponse = $this->client->getResponse();
        $this->assertEquals(200, $clientResponse->getStatusCode());
        $this->assertStringNotContainsString('user[plainPassword][password]', $clientResponse->getContent());
        $this->assertStringNotContainsString('user[plainPassword][confirm]', $clientResponse->getContent());
    }

    public function testPasswordOnAccountPageWithoutSaml(): void
    {
        $user = $this->createUser($this->createRole(), 'test@example.com');
        $this->em->flush();
        $this->em->clear();
        $this->loginUser($user);

        $this->client->request(Request::METHOD_GET, 's/account');

        $clientResponse = $this->client->getResponse();
        $this->assertEquals(200, $clientResponse->getStatusCode());
        $this->assertStringContainsString('user[plainPassword][password]', $clientResponse->getContent());
        $this->assertStringContainsString('user[plainPassword][confirm]', $clientResponse->getContent());
    }
}
