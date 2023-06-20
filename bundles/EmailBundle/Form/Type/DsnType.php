<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\SortableListType;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\Dsn\Dsn;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * @extends AbstractType<array>
 */
class DsnType extends AbstractType
{
    public function __construct(private CoreParametersHelper $coreParametersHelper)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'scheme',
            TextType::class,
            [
                'label' => 'mautic.email.config.mailer.dsn.scheme',
                'attr'  => [
                    'class'    => 'form-control',
                    'onchange' => 'Mautic.disableSendTestEmailButton()',
                ],
            ]
        );

        $builder->add(
            'host',
            TextType::class,
            [
                'label' => 'mautic.email.config.mailer.dsn.host',
                'attr'  => [
                    'class'    => 'form-control',
                    'onchange' => 'Mautic.disableSendTestEmailButton()',
                ],
            ]
        );

        $builder->add(
            'port',
            TextType::class,
            [
                'label'    => 'mautic.email.config.mailer.dsn.port',
                'required' => false,
                'attr'     => [
                    'class'    => 'form-control',
                    'onchange' => 'Mautic.disableSendTestEmailButton()',
                ],
            ]
        );

        $builder->add(
            'user',
            TextType::class,
            [
                'label'    => 'mautic.email.config.mailer.dsn.user',
                'required' => false,
                'attr'     => [
                    'class'    => 'form-control',
                    'onchange' => 'Mautic.disableSendTestEmailButton()',
                ],
            ]
        );

        $builder->add(
            'password',
            TextType::class,
            [
                'label'    => 'mautic.email.config.mailer.dsn.password',
                'required' => false,
                'attr'     => [
                    'class'    => 'form-control',
                    'onchange' => 'Mautic.disableSendTestEmailButton()',
                ],
            ]
        );

        $builder->add(
            'path',
            TextType::class,
            [
                'label'    => 'mautic.email.config.mailer.dsn.path',
                'required' => false,
                'attr'     => [
                    'class'    => 'form-control',
                    'onchange' => 'Mautic.disableSendTestEmailButton()',
                ],
            ]
        );

        $builder->add(
            'options',
            SortableListType::class,
            [
                'required'        => false,
                'label'           => 'mautic.email.config.mailer.dsn.options',
                'attr'            => [
                    'onchange' => 'Mautic.disableSendTestEmailButton()',
                ],
                'option_required' => false,
                'with_labels'     => true,
                'key_value_pairs' => true,
            ]
        );
    }

    /**
     * @phpstan-ignore-next-line
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $dsn = Dsn::fromString($this->coreParametersHelper->get('mailer_dsn'));

        if ($dsn->getPassword()) {
            $dsn = $dsn->setPassword('SECRET');
        }

        $view->vars['currentDns'] = $dsn;
    }
}