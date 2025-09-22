<?php

declare(strict_types=1);

namespace Mautic\UserBundle\Security\SAML;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Symfony\Component\HttpFoundation\RequestStack;

class Helper
{
    private RequestStack $request;

    private CoreParametersHelper $coreParametersHelper;

    public function __construct(CoreParametersHelper $coreParametersHelper, RequestStack $request)
    {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->request              = $request;
    }

    public function isSamlSession(): bool
    {
        return $this->isSamlEnabled() && $this->request->getSession()->has('samlsso');
    }

    public function isSamlEnabled(): bool
    {
        return (bool) $this->coreParametersHelper->get('saml_idp_metadata');
    }
}
