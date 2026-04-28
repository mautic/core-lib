<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[HasNamedArguments]
final class EmailOrEmailTokenList extends Constraint
{
    public bool $allowMultiple = true;
}
