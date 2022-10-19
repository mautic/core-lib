<?php

declare(strict_types=1);

namespace Mautic\UserBundle\Security\SAML;

use LightSaml\Builder\EntityDescriptor\SimpleEntityDescriptorBuilder;
use LightSaml\Credential\X509Credential;
use LightSaml\Store\Credential\CredentialStoreInterface;
use Symfony\Component\Routing\RouterInterface;

class EntityDescriptorProviderFactory
{
    public static function build(
        string $ownEntityId,
        RouterInterface $router,
        ?string $acsRouteName,
        CredentialStoreInterface $ownCredentialStore
    ): SimpleEntityDescriptorBuilder {
        /** @var X509Credential[] $arrOwnCredentials */
        $arrOwnCredentials = $ownCredentialStore->getByEntityId($ownEntityId);
        $route             = $acsRouteName ? $router->generate($acsRouteName, [], RouterInterface::ABSOLUTE_PATH) : '';

        return new SimpleEntityDescriptorBuilder(
            $ownEntityId,
            empty($route) ? '' : sprintf('%s%s', $ownEntityId, $route),
            '',
            $arrOwnCredentials[0]->getCertificate()
        );
    }
}
