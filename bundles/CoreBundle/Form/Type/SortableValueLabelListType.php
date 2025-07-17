<?php

namespace Mautic\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * @extends AbstractType<mixed>
 */
class SortableValueLabelListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'label',
            TextType::class,
            [
                'label'          => 'mautic.core.label',
                'error_bubbling' => true,
                'attr'           => ['class' => 'form-control'],
            ]
        );

        $builder->add(
            'value',
            TextType::class,
            [
                'label'          => 'mautic.core.value',
                'error_bubbling' => true,
                'required'       => false,
                'attr'           => ['class' => 'form-control'],
            ]
        );

        // Auto-generate value from label if value is empty
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event): void {
                $data = $event->getData();

                if (is_array($data)
                    && empty($data['value'])
                    && !empty($data['label'])
                ) {
                    $data['value'] = $this->slugify((string) $data['label']);
                    $event->setData($data);
                }
            }
        );
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['preaddonAttr']  = $options['attr']['preaddon_attr'] ?? [];
        $view->vars['postaddonAttr'] = $options['attr']['postaddon_attr'] ?? [];
        $view->vars['preaddon']      = $options['attr']['preaddon'] ?? [];
        $view->vars['postaddon']     = $options['attr']['postaddon'] ?? [];
    }

    /**
     * Convert a string to a URL-safe slug.
     */
    private function slugify(string $text): string
    {
        // Replace non-letter or digits with underscores
        $text = preg_replace('~[^\pL\d]+~u', '_', $text);

        // Transliterate to ASCII
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;

        // Remove unwanted characters
        $text = preg_replace('~[^_\w]+~', '', $text);

        // Trim underscores from start and end
        $text = trim($text, '_');

        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Remove duplicate underscores
        $text = preg_replace('~_+~', '_', $text);

        return $text;
    }
}
