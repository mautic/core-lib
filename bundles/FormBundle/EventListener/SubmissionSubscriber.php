<?php

declare(strict_types=1);

namespace Mautic\FormBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Mautic\FormBundle\Entity\FormRepository;
use Mautic\FormBundle\Entity\Submission;

#[AsDoctrineListener(Events::postRemove)]
final class SubmissionSubscriber
{
    public function __construct(
        private FormRepository $formRepository,
    ) {
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Submission) {
            return;
        }

        $form = $entity->getForm();
        if ($form) {
            $this->formRepository->decrementSubmissionCount($form->getId());
        }
    }
}
