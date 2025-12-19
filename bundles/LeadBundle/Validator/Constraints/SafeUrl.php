<?php

namespace Mautic\LeadBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class SafeUrl extends Constraint
{
    public string $dataProtocolMessage = 'mautic.lead.dataProtocolMessage';
}
