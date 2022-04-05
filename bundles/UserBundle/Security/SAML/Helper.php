<?php

declare(strict_types=1);

namespace Mautic\UserBundle\Security\SAML;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Helper
{
    private SessionInterface $session;

    private CoreParametersHelper $coreParametersHelper;

    public function __construct(CoreParametersHelper $coreParametersHelper, SessionInterface $session)
    {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->session              = $session;
    }

    public function isSamlSession(): bool
    {
        return $this->isSamlEnabled() && $this->session->has('samlsso');
    }

    public function isSamlEnabled(): bool
    {
        return (bool) $this->coreParametersHelper->get('saml_idp_metadata');
    }
}
