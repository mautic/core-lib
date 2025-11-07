<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RepublishBehaviorType extends AbstractType
{
    private const BASE_CHOICES = [
        'mautic.campaignconfig.campaign_republish_behavior.restart_on_publish'         => 'restart_on_publish',
        'mautic.campaignconfig.campaign_republish_behavior.count_only_while_published' => 'count_only_while_published',
        'mautic.campaignconfig.campaign_republish_behavior.count_all_time'             => 'count_all_time',
    ];

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label'                 => 'mautic.campaignconfig.campaign_republish_behavior',
            'label_attr'            => ['class' => 'control-label'],
            'required'              => false,
            'include_global_option' => false,
            'attr'                  => [
                'class'   => 'form-control',
                'tooltip' => 'mautic.campaignconfig.campaign_republish_behavior_tooltip',
            ],
        ]);

        $resolver->setNormalizer('choices', fn ($options) => $options['include_global_option']
                ? ['mautic.campaignconfig.campaign_republish_behavior.use_global' => null] + self::BASE_CHOICES
                : self::BASE_CHOICES
        );

        $resolver->setAllowedTypes('include_global_option', 'bool');
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
