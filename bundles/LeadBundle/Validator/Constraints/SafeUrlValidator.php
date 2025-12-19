<?php

namespace Mautic\LeadBundle\Validator\Constraints;

use Mautic\LeadBundle\Exception\ImportRowFailedException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SafeUrlValidator extends ConstraintValidator
{
    private ?TranslatorInterface $translator;

    public function __construct(?TranslatorInterface $translator = null)
    {
        $this->translator = $translator;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$value) {
            return;
        }

        if (!$constraint instanceof SafeUrl) {
            throw new UnexpectedTypeException($constraint, SafeUrl::class);
        }

        $trimmedValue = trim((string) $value);

        if (str_starts_with(strtolower($trimmedValue), 'data:')) {
            if (null !== $this->context) {
                $this->context->buildViolation($constraint->dataProtocolMessage)
                  ->addViolation();

                return;
            }

            $message = $this->translator->trans(
                $constraint->dataProtocolMessage,
                ['{{ value }}' => $value],
                'validators'
            );

            throw new ImportRowFailedException($message);
        }
    }
}
