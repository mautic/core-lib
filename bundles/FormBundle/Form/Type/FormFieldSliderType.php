<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<FormFieldSliderType>
 */
final class FormFieldSliderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('min', IntegerType::class, [
            'label'      => 'mautic.form.field.form.slider_min',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => ['class' => 'form-control'],
            'data'       => $options['data']['min'] ?? 0,
        ]);

        $builder->add('max', IntegerType::class, [
            'label'      => 'mautic.form.field.form.slider_max',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => ['class' => 'form-control'],
            'data'       => $options['data']['max'] ?? 100,
        ]);

        $builder->add('step', IntegerType::class, [
            'label'      => 'mautic.form.field.form.slider_step',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => ['class' => 'form-control'],
            'data'       => $options['data']['step'] ?? 1,
            'constraints' => [
                new \Symfony\Component\Validator\Constraints\Range([
                    'min' => 1,
                    'minMessage' => 'mautic.form.field.form.slider_step_min_error'
                ])
            ]
        ]);
    }
}
