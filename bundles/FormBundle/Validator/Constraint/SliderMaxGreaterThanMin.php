<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

final class SliderMaxGreaterThanMin extends Constraint
{
    /**
     * @var string
     */
    public $message = 'mautic.form.field.form.slider_max_gt_min_error';
}
