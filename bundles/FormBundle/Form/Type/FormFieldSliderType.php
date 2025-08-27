<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;

/**
 * Custom IntegerType that supports constraints
 */
final class ConstrainedIntegerType extends IntegerType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        
        $resolver->setDefined('constraints');
        $resolver->setAllowedTypes('constraints', ['array']);
        $resolver->setAllowedValues('constraints', function ($constraints) {
            if (!is_array($constraints)) {
                return false;
            }
            
            foreach ($constraints as $constraint) {
                if (!$constraint instanceof Constraint) {
                    return false;
                }
            }
            
            return true;
        });
    }
}

/**
 * @extends AbstractType<mixed>
 */
final class FormFieldSliderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('min', ConstrainedIntegerType::class, [
            'label'      => 'mautic.form.field.form.slider_min',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => ['class' => 'form-control'],
            'data'       => $options['data']['min'] ?? 0,
        ]);

        $builder->add('max', ConstrainedIntegerType::class, [
            'label'      => 'mautic.form.field.form.slider_max',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => ['class' => 'form-control'],
            'data'       => $options['data']['max'] ?? 100,
            'constraints' => [
                new \Symfony\Component\Validator\Constraints\Expression([
                    'expression' => 'this.getParent()["min"].getData() < value',
                    'message' => 'mautic.form.field.form.slider_max_gt_min_error'
                ])
            ]
        ]);

        $builder->add('step', ConstrainedIntegerType::class, [
            'label'      => 'mautic.form.field.form.slider_step',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => ['class' => 'form-control'],
            'data'       => $options['data']['step'] ?? 1,
            'constraints' => [
                new \Symfony\Component\Validator\Constraints\Range([
                    'min' => 1,
                    'minMessage' => 'mautic.form.field.form.slider_step_min_error'
                ]),
                new \Symfony\Component\Validator\Constraints\Expression([
                    'expression' => 'value < this.getParent()["max"].getData()',
                    'message' => 'mautic.form.field.form.slider_step_lt_max_error'
                ])
            ]
        ]);
    }
}
