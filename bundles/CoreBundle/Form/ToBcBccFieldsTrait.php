<?php

namespace Mautic\CoreBundle\Form;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

trait ToBcBccFieldsTrait
{
    protected function addToBcBccFields(FormBuilderInterface $builder): void
    {
        $multipleEmailConstraint = new Callback(function (?string $value, ExecutionContextInterface $context): void {
            if (empty($value)) {
                return;
            }

            $emailValidator = new Email(['message' => 'mautic.core.email.required']);
            $validator      = $context->getValidator();

            foreach (array_map('trim', explode(',', $value)) as $email) {
                if ('' === $email) {
                    continue;
                }

                $violations = $validator->validate($email, $emailValidator);

                if (count($violations) > 0) {
                    $context->buildViolation('mautic.core.email.required')
                        ->addViolation();

                    return;
                }
            }
        });

        $builder->add(
            'to',
            TextType::class,
            [
                'label'      => 'mautic.core.send.email.to',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'placeholder' => 'mautic.core.optional',
                    'tooltip'     => 'mautic.core.send.email.to.multiple.addresses',
                ],
                'required'    => false,
                'constraints' => $multipleEmailConstraint,
            ]
        );

        $builder->add(
            'cc',
            TextType::class,
            [
                'label'      => 'mautic.core.send.email.cc',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'placeholder' => 'mautic.core.optional',
                    'tooltip'     => 'mautic.core.send.email.to.multiple.addresses',
                ],
                'required'    => false,
                'constraints' => $multipleEmailConstraint,
            ]
        );

        $builder->add(
            'bcc',
            TextType::class,
            [
                'label'      => 'mautic.core.send.email.bcc',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'placeholder' => 'mautic.core.optional',
                    'tooltip'     => 'mautic.core.send.email.to.multiple.addresses',
                ],
                'required'    => false,
                'constraints' => $multipleEmailConstraint,
            ]
        );
    }
}
