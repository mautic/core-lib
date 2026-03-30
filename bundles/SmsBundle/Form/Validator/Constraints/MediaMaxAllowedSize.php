<?php

declare(strict_types=1);

namespace Mautic\SmsBundle\Form\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

final class MediaMaxAllowedSize extends Constraint
{
    public string $message = 'mautic.sms.form.max.size.media.error';

    public function validatedBy(): string
    {
        return MediaMaxAllowedSizeValidator::class;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
