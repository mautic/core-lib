<?php

namespace Mautic\LeadBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SafeUrlValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!$constraint instanceof SafeUrl) {
            throw new UnexpectedTypeException($constraint, SafeUrl::class);
        }

        $trimmedValue = trim((string) $value);

        if (str_starts_with(strtolower($trimmedValue), 'data:')) {
            $this->context
                ->buildViolation($constraint->dataProtocolMessage)
                ->addViolation();
        }
    }
}
