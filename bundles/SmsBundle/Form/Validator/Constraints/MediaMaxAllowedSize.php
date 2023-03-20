<?php

declare(strict_types=1);

namespace Mautic\SmsBundle\Form\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class MediaMaxAllowedSize extends Constraint
{
    /**
     * @var string
     */
    public $message = 'mautic.sms.form.max.size.media.error';

    public function validatedBy(): string
    {
        return 'mms_max_allowed_media_size';
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
