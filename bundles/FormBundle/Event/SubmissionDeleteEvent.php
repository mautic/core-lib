<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;

final class SubmissionDeleteEvent extends CommonEvent
{
    public function __construct(
        Submission $submission,
        private Form $form,
    ) {
        $this->entity = $submission;
    }

    public function getSubmission(): Submission
    {
        return $this->entity;
    }

    public function getForm(): Form
    {
        return $this->form;
    }
}
