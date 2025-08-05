<?php

namespace Mautic\FormBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Rating field properties form.
 *
 * @extends AbstractType<FormFieldRatingType>
 */
class FormFieldRatingType extends AbstractType
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

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

        $builder->add(
            'symbol',
            ChoiceType::class,
            [
                'label'      => 'mautic.form.field.form.rating_symbol',
                'label_attr' => ['class' => 'control-label'],
                'choices'    => [
                    '★ '.$this->translator->trans('mautic.form.field.form.rating_symbol.star_filled')     => '★',
                    '✪ '.$this->translator->trans('mautic.form.field.form.rating_symbol.star_filled_alt') => '✪',
                    '⍟ '.$this->translator->trans('mautic.form.field.form.rating_symbol.asterisk')        => '⍟',
                    '🞵 '.$this->translator->trans('mautic.form.field.form.rating_symbol.square')          => '🞵',
                    '✦ '.$this->translator->trans('mautic.form.field.form.rating_symbol.sparkle')         => '✦',
                    '♡ '.$this->translator->trans('mautic.form.field.form.rating_symbol.heart')           => '♡',
                    '● '.$this->translator->trans('mautic.form.field.form.rating_symbol.circle_filled')   => '●',
                    '◆ '.$this->translator->trans('mautic.form.field.form.rating_symbol.diamond_filled')  => '◆',
                ],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.form.field.help.rating_symbol',
                ],
                'data'     => $options['data']['symbol'] ?? '★',
                'required' => false,
            ]
        );

        $builder->add(
            'star_color',
            TextType::class,
            [
                'label'      => 'mautic.form.field.form.rating_star_color',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'        => 'form-control minicolors-input',
                    'tooltip'      => 'mautic.form.field.help.rating_star_color',
                    'data-toggle'  => 'color',
                    'autocomplete' => 'false',
                    'size'         => '7',
                ],
                'data'     => $options['data']['star_color'] ?? '#f5b301',
                'required' => false,
            ]
        );

        $builder->add(
            'base_color',
            TextType::class,
            [
                'label'      => 'mautic.form.field.form.rating_base_color',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'        => 'form-control minicolors-input',
                    'tooltip'      => 'mautic.form.field.help.rating_base_color',
                    'data-toggle'  => 'color',
                    'autocomplete' => 'false',
                    'size'         => '7',
                ],
                'data'     => $options['data']['base_color'] ?? '#cccccc',
                'required' => false,
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return 'formfield_rating';
    }
}
