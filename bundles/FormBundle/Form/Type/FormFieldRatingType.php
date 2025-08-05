<?php

namespace Mautic\FormBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Rating field properties form.
 *
 * @extends AbstractType<FormFieldRatingType>
 */
class FormFieldRatingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'star_count',
            IntegerType::class,
            [
                'label'      => 'mautic.form.field.form.rating_star_count',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.form.field.help.rating_star_count',
                    'min'     => 1,
                ],
                'data'     => $options['data']['star_count'] ?? 5,
                'required' => false,
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return 'formfield_rating';
    }
}
