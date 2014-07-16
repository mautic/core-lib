<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
if ($tmpl == 'index')
    $view->extend('MauticLeadBundle:Lead:index.html.php');
?>

<div class="grid row scrollable bundle-list">
    <?php if (count($items)): ?>
    <?php foreach ($items as $item): ?>
    <?php $fields = $model->organizeFieldsByAlias($item->getFields()); ?>
    <div class="grid margin-md-bottom col-sm-6 col-md-4">
        <div class="body-white table-layout">
            <div class="col-xs-4 padding-none">
                <img class="img img-responsive"
                     src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($fields['email']))); ?>?&s=250" />
            </div>
            <div class="col-xs-8 valign-middle">
                <h5>
                    <?php echo $view['translator']->trans($item->getPrimaryIdentifier(true)); ?>
                    <span class="badge"><?php echo $item->getScore(); ?></span>
                </h5>
                <div class="text-muted">
                    <i class="fa fa-fw fa-building"></i><span class="padding-sm-left"><?php echo @$fields['company']; ?></span>
                </div>
                <div class="text-muted">
                    <i class="fa fa-fw fa-envelope"></i><span class="padding-sm-left"><?php echo @$fields['email']; ?></span>
                </div>
                <div class="text-muted">
                    <i class="fa fa-fw fa-map-marker"></i><span class="padding-sm-left"><?php
                    if (!empty($fields['city']) && !empty($fields['state']))
                        echo $fields['city'] . ', ' . $fields['state'];
                    elseif (!empty($fields['city']))
                        echo $fields['city'];
                    elseif (!empty($fields['state']))
                        echo $fields['state'];
                    ?></span>
                </div>
                <div class="text-muted">
                    <i class="fa fa-fw fa-globe"></i><span class="padding-sm-left"><?php echo @$fields['country']; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
        <div class="col-xs-12">
            <h4><?php echo $view['translator']->trans('mautic.core.noresults'); ?></h4>
        </div>
    <?php endif; ?>

    <?php echo $view->render('MauticCoreBundle:Helper:pagination.html.php', array(
        "items"           => $items,
        "page"            => $page,
        "limit"           => $limit,
        "menuLinkId"      => 'mautic_lead_index',
        "baseUrl"         => $view['router']->generate('mautic_lead_index'),
        "tmpl"            => $indexMode,
        'sessionVar'      => 'lead'
    )); ?>
    <div class="footer-margin"></div>
</div>
