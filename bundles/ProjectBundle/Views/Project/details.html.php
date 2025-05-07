<?php

$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'project');
$view['slots']->set('headerTitle', $project->getName());
$customButtons = [];

$view['slots']->set(
    'actions',
    $view->render(
        'MauticCoreBundle:Helper:page_actions.html.php',
        [
            'item'            => $project,
            'customButtons'   => (isset($customButtons)) ? $customButtons : [],
            'nameGetter'      => 'getName',
            'templateButtons' => [
                'edit'   => $view['security']->isGranted('project:project:edit'),
                'delete' => $view['security']->isGranted('project:project:delete'),
                'close'  => $view['security']->isGranted('project:project:edit'),
            ],
            'routeBase' => 'project',
        ]
    )
);

?>

<!-- start: box layout -->
<div class="box-layout">
    <!-- left section -->
    <div class="col-md-12 bg-white height-auto">
        <div class="bg-auto">
            <!-- page detail header -->
            <!-- sms detail collapseable toggler -->
            <div class="pr-md pl-md pt-lg pb-lg">
                <div class="box-layout">
                    <div class="col-xs-10">
                        <div class="text-white dark-sm mb-0"><?php echo $project->getDescription(); ?></div>
                    </div>
                </div>
            </div>
            <div class="collapse" id="sms-details">
                <div class="pr-md pl-md pb-md">
                    <div class="panel shd-none mb-0">
                        <table class="table table-bordered table-striped mb-0">
                            <tbody>
                            <tr>
                                <td width="20%"><span class="fw-b textTitle"><?php echo $view['translator']->trans('mautic.core.id'); ?></span></td>
                                <td><?php echo $project->getId(); ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!--/ sms detail collapseable toggler -->
        <div class="bg-auto bg-dark-xs">
            <div class="hr-expand nm">
                <span data-toggle="tooltip" title="Detail">
                    <a href="javascript:void(0)" class="arrow text-muted collapsed" data-toggle="collapse"
                       data-target="#sms-details">
                        <span class="caret"></span> <?php echo $view['translator']->trans('mautic.core.details'); ?>
                    </a>
                </span>
            </div>
        </div>
    </div>

    <!--/ right section -->
    <input name="entityId" id="entityId" type="hidden" value="<?php echo $view->escape($project->getId()); ?>" />
</div>
<!--/ end: box layout -->
