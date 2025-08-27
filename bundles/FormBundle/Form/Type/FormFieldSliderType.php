<?php

declare(strict_types=1);

namespace Mautic\FormBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<FormFieldSliderType>
 */
final class FormFieldSliderType extends AbstractType
{
    public function __construct(
        private ?TranslatorInterface $translator = null
    ) {
    }

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
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            if (!$data) {
                return;
            }

            $min = $data['min'] ?? 0;
            $max = $data['max'] ?? 100;
            $step = $data['step'] ?? 1;

            if ($max <= $min) {
                $message = $this->translator 
                    ? $this->translator->trans('mautic.form.field.form.slider_max_gt_min_error', [], 'validators')
                    : 'mautic.form.field.form.slider_max_gt_min_error';
                $form->get('max')->addError(new \Symfony\Component\Form\FormError($message));
            }

            if ($step < 1) {
                $message = $this->translator 
                    ? $this->translator->trans('mautic.form.field.form.slider_step_min_error', [], 'validators')
                    : 'mautic.form.field.form.slider_step_min_error';
                $form->get('step')->addError(new \Symfony\Component\Form\FormError($message));
            }

            if ($step >= $max) {
                $message = $this->translator 
                    ? $this->translator->trans('mautic.form.field.form.slider_step_lt_max_error', [], 'validators')
                    : 'mautic.form.field.form.slider_step_lt_max_error';
                $form->get('step')->addError(new \Symfony\Component\Form\FormError($message));
            }
        });
    }
}
