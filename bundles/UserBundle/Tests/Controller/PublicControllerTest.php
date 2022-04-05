<?php

declare(strict_types=1);

namespace Mautic\UserBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\UserBundle\Tests\Traits\CreateEntityTrait;
use Symfony\Component\HttpFoundation\Request;

class PublicControllerTest extends MauticMysqlTestCase
{
    use CreateEntityTrait;

    protected function setUp(): void
    {
        if (strpos($this->getName(false), 'WithSaml') > 0) {
            $this->configParams['saml_idp_metadata'] = 'any_string';
        }
        parent::setUp();
    }

    public function testPasswordResetActionWithoutUserWithSaml(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, 'passwordreset');

        // Get the form
        $form = $crawler->filter('form')->form();
        $form->setValues([
            'passwordreset[identifier]' => 'test2@example.com',
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();
        $this->assertEquals(200, $clientResponse->getStatusCode());
        $validationError = self::$container->get('translator')->trans('mautic.user.user.passwordreset.nouserfound', [], 'validators');
        $this->assertStringContainsString($validationError, $clientResponse->getContent());
    }

    public function testPasswordResetActionWithoutUserWithoutSaml(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, 'passwordreset');

        // Get the form
        $form = $crawler->filter('form')->form();
        $form->setValues([
            'passwordreset[identifier]' => 'test@example.com',
        ]);
        $this->client->submit($form);

        $clientResponse = $this->client->getResponse();
        $this->assertEquals(200, $clientResponse->getStatusCode());
        $validationError = self::$container->get('translator')->trans('mautic.user.user.passwordreset.nouserfound', [], 'validators');
        $this->assertStringContainsString($validationError, $clientResponse->getContent());
    }
}
