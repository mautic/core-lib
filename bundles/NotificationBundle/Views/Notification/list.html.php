<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

if ($tmpl == 'index')
    $view->extend('MauticNotificationBundle:Notification:index.html.php');

if (count($items)):

?>
<div class="table-responsive">
    <table class="table table-hover table-striped table-bordered notification-list">
        <thead>
        <tr>
            <?php
            echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', array(
                'checkall' => 'true'
            ));

            echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', array(
                'sessionVar' => 'notification',
                'orderBy'    => 'e.name',
                'text'       => 'mautic.core.name',
                'class'      => 'col-notification-name',
                'default'    => true
            ));

            echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', array(
                'sessionVar' => 'notification',
                'orderBy'    => 'c.title',
                'text'       => 'mautic.core.category',
                'class'      => 'visible-md visible-lg col-notification-category'
            ));
            ?>

            <th class="visible-sm visible-md visible-lg col-notification-stats"><?php echo $view['translator']->trans('mautic.notification.thead.stats'); ?></th>

            <?php
            echo $view->render('MauticCoreBundle:Helper:tableheader.html.php', array(
                'sessionVar' => 'notification',
                'orderBy'    => 'e.id',
                'text'       => 'mautic.core.id',
                'class'      => 'visible-md visible-lg col-notification-id'
            ));
            ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <?php
                    $edit          = $view['security']->hasEntityAccess($permissions['notification:notifications:editown'], $permissions['notification:notifications:editother'], $item->getCreatedBy());
                    $customButtons = ($type == 'list') ? array(
                        array(
                            'attr' => array(
                                'data-toggle' => 'ajax',
                                'href'        => $view['router']->generate('mautic_notification_action', array('objectAction' => 'send', 'objectId' => $item->getId())),
                            ),
                            'iconClass' => 'fa fa-send-o',
                            'btnText'   => 'mautic.notification.send'
                        )
                    ) : array();
                    echo $view->render('MauticCoreBundle:Helper:list_actions.html.php', array(
                        'item'            => $item,
                        'templateButtons' => array(
                            'edit'       => $edit,
                            'clone'      => $permissions['notification:notifications:create'],
                            'delete'     => $view['security']->hasEntityAccess($permissions['notification:notifications:deleteown'], $permissions['notification:notifications:deleteother'], $item->getCreatedBy())
                        ),
                        'routeBase'       => 'notification',
                        'customButtons'   => $customButtons
                    ));
                    ?>
                </td>
                <td>
                    <div>
                        <?php if ($type == 'template'): ?>
                        <?php echo $view->render('MauticCoreBundle:Helper:publishstatus_icon.html.php',array('item' => $item, 'model' => 'notification')); ?>
                        <?php else: ?>
                        <i class="fa fa-fw fa-lg fa-toggle-on text-muted disabled"></i>
                        <?php endif; ?>
                        <a href="<?php echo $view['router']->generate('mautic_notification_action', array("objectAction" => "view", "objectId" => $item->getId())); ?>" data-toggle="ajax">
                            <?php echo $item->getName(); ?>
                            <?php if ($type == 'list'): ?>
                            <span data-toggle="tooltip" title="<?php echo $view['translator']->trans('mautic.notification.icon_tooltip.list_notification'); ?>"><i class="fa fa-fw fa-list"></i></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php if ($description = $item->getDescription()): ?>
                        <div class="text-muted mt-4"><small><?php echo $description; ?></small></div>
                    <?php endif; ?>
                </td>
                <td class="visible-md visible-lg">
                    <?php $category = $item->getCategory(); ?>
                    <?php $catName  = ($category) ? $category->getTitle() : $view['translator']->trans('mautic.core.form.uncategorized'); ?>
                    <?php $color    = ($category) ? '#' . $category->getColor() : 'inherit'; ?>
                    <span style="white-space: nowrap;"><span class="label label-default pa-4" style="border: 1px solid #d5d5d5; background: <?php echo $color; ?>;"> </span> <span><?php echo $catName; ?></span></span>
                </td>
                <td class="visible-sm visible-md visible-lg col-stats">
                    <?php if ($type == 'list'): ?>
                    <span class="mt-xs label label-info"><?php echo $view['translator']->trans('mautic.notification.stat.leadcount', array('%count%' => $model->getPendingLeads($item, null, true))); ?></span>
                    <?php endif; ?>
                    <span class="mt-xs label label-warning"><?php echo $view['translator']->trans('mautic.notification.stat.sentcount', array('%count%' => $item->getSentCount(true))); ?></span>
                    <span class="mt-xs label label-success"><?php echo $view['translator']->trans('mautic.notification.stat.readcount', array('%count%' => $item->getReadCount(true))); ?></span>
                </td>
                <td class="visible-md visible-lg"><?php echo $item->getId(); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="panel-footer">
    <?php echo $view->render('MauticCoreBundle:Helper:pagination.html.php', array(
        'totalItems'      => $totalItems,
        'page'            => $page,
        'limit'           => $limit,
        'baseUrl'         => $view['router']->generate('mautic_notification_index'),
        'sessionVar'      => 'notification'
    )); ?>
</div>
<?php else: ?>
    <?php echo $view->render('MauticCoreBundle:Helper:noresults.html.php'); ?>
<?php endif; ?>
