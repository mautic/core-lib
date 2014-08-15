<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AssetBundle\Form\Type;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class AssetType
 *
 * @package Mautic\AssetBundle\Form\Type
 */
class AssetType extends AbstractType
{

    private $translator;
    private $themes;

    /**
     * @param MauticFactory $factory
     */
    public function __construct(MauticFactory $factory) {
        $this->translator = $factory->getTranslator();
        $this->themes     = $factory->getInstalledThemes('asset');
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm (FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new CleanFormSubscriber(array('content' => 'html')));
        $builder->addEventSubscriber(new FormExitSubscriber('asset.asset', $options));

        $variantParent = $options['data']->getVariantParent();
        $isVariant     = !empty($variantParent);

        if ($isVariant) {

            $builder->add("assetvariant-panel-wrapper-start", 'panel_wrapper_start', array(
                'attr' => array(
                    'id' => "asset-panel"
                )
            ));

            //details
            $builder->add("details-panel-start", 'panel_start', array(
                'label'      => 'mautic.asset.asset.panel.variantdetails',
                'dataParent' => '#asset-panel',
                'bodyId'     => 'details-panel',
                'bodyAttr'   => array('class' => 'in')
            ));
        }

        $builder->add('file', 'file', array(
            'label'      => 'mautic.asset.asset.form.file.upload',
            'label_attr' => array('class' => 'control-label'),
            'attr'       => array('class' => 'form-control')
        ));

        $builder->add('title', 'text', array(
            'label'      => 'mautic.asset.asset.form.title',
            'label_attr' => array('class' => 'control-label'),
            'attr'       => array('class' => 'form-control')
        ));

        if (!$isVariant) {
            $builder->add('alias', 'text', array(
                'label'      => 'mautic.asset.asset.form.alias',
                'label_attr' => array('class' => 'control-label'),
                'attr'       => array(
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.asset.asset.help.alias',
                ),
                'required'   => false
            ));
        }

        if (!$isVariant) {
            $builder->add('category_lookup', 'text', array(
                'label'      => 'mautic.asset.asset.form.category',
                'label_attr' => array('class' => 'control-label'),
                'attr'       => array(
                    'class'       => 'form-control',
                    'tooltip'     => 'mautic.core.help.autocomplete',
                    'placeholder' => $this->translator->trans('mautic.core.form.uncategorized')
                ),
                'mapped'     => false,
                'required'   => false
            ));

            $builder->add('category', 'hidden_entity', array(
                'required'       => false,
                'repository'     => 'MauticAssetBundle:Category',
                'error_bubbling' => false,
                'read_only'      => ($isVariant) ? true : false
            ));
        }

        if (!$isVariant) {
            $builder->add('language', 'locale', array(
                'label'      => 'mautic.asset.asset.form.language',
                'label_attr' => array('class' => 'control-label'),
                'attr'       => array(
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.asset.asset.form.language.help',
                ),
                'required'   => false
            ));

            $builder->add('translationParent_lookup', 'text', array(
                'label'      => 'mautic.asset.asset.form.translationparent',
                'label_attr' => array('class' => 'control-label'),
                'attr'       => array(
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.asset.asset.form.translationparent.help'
                ),
                'mapped'     => false,
                'required'   => false
            ));

            $builder->add('translationParent', 'hidden_entity', array(
                'required'       => false,
                'repository'     => 'MauticAssetBundle:Asset',
                'error_bubbling' => false
            ));
        }

        $builder->add('isPublished', 'button_group', array(
            'choice_list' => new ChoiceList(
                array(false, true),
                array('mautic.core.form.no', 'mautic.core.form.yes')
            ),
            'expanded'      => true,
            'multiple'      => false,
            'label'         => 'mautic.core.form.ispublished',
            'empty_value'   => false,
            'required'      => false
        ));

        if (!$isVariant) {
            $builder->add('publishUp', 'datetime', array(
                'widget'     => 'single_text',
                'label'      => 'mautic.core.form.publishup',
                'label_attr' => array('class' => 'control-label'),
                'attr'       => array(
                    'class'       => 'form-control',
                    'data-toggle' => 'datetime'
                ),
                'format'     => 'yyyy-MM-dd HH:mm',
                'required'   => false
            ));

            $builder->add('publishDown', 'datetime', array(
                'widget'     => 'single_text',
                'label'      => 'mautic.core.form.publishdown',
                'label_attr' => array('class' => 'control-label'),
                'attr'       => array(
                    'class'       => 'form-control',
                    'data-toggle' => 'datetime'
                ),
                'format'     => 'yyyy-MM-dd HH:mm',
                'required'   => false
            ));
        }

        $builder->add('sessionId', 'hidden');

        if ($isVariant) {

            $builder->add("details-panel-end", 'panel_end');

            $builder->add('publishUp', 'datetime', array(
                'widget'     => 'single_text',
                'label'      => 'mautic.core.form.publishup',
                'label_attr' => array('class' => 'control-label'),
                'attr'       => array(
                    'class'       => 'form-control',
                    'data-toggle' => 'datetime'
                ),
                'format'     => 'yyyy-MM-dd HH:mm',
                'required'   => false
            ));

            $builder->add('publishDown', 'datetime', array(
                'widget'     => 'single_text',
                'label'      => 'mautic.core.form.publishdown',
                'label_attr' => array('class' => 'control-label'),
                'attr'       => array(
                    'class'       => 'form-control',
                    'data-toggle' => 'datetime'
                ),
                'format'     => 'yyyy-MM-dd HH:mm',
                'required'   => false
            ));

            $builder->add('variant_settings', 'assetvariant', array(
                'label'       => false,
                'asset_entity' => $options['data']
            ));

            $builder->add("assetvariant-panel-wrapper-end", 'panel_wrapper_end');
        }

        $builder->add('buttons', 'form_buttons', array());

        if (!empty($options["action"])) {
            $builder->setAction($options["action"]);
        }
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Mautic\AssetBundle\Entity\Asset'
        ));
    }

    /**
     * @return string
     */
    public function getName() {
        return "asset";
    }
}