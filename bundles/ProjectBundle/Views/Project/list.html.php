<?php
if ('index' === $tmpl):
    $view->extend('MauticProjectBundle:Project:index.html.php');
endif;

$nameGetter ??= 'getName';
?>

<?php if (count($items)): ?>
    <div class="table-responsive">
        <table class="table table-hover table-striped table-bordered" id="projectsTable">
            <thead>
            <tr>
                <?php
                echo $view->render(
                    'MauticCoreBundle:Helper:tableheader.html.php',
                    [
                        'checkall'        => 'true',
                        'target'          => '#projectsTable',
                        'langVar'         => 'project.project',
                        'routeBase'       => 'project',
                        'templateButtons' => [
                            'delete' => $permissions['project:project:delete'],
                        ],
                    ]
                );

    echo $view->render(
        'MauticCoreBundle:Helper:tableheader.html.php',
        [
            'sessionVar' => 'projects',
            'orderBy'    => 'p.name',
            'text'       => 'mautic.core.name',
            'class'      => 'col-project-name',
        ]
    );

    echo $view->render(
        'MauticCoreBundle:Helper:tableheader.html.php',
        [
            'sessionVar' => 'projects',
            'orderBy'    => 'p.dateAdded',
            'text'       => 'mautic.core.date.added',
            'class'      => 'col-project-date-added',
        ]
    );

    echo $view->render(
        'MauticCoreBundle:Helper:tableheader.html.php',
        [
            'sessionVar' => 'projects',
            'orderBy'    => 'p.dateAdded',
            'text'       => 'mautic.core.date.modified',
            'class'      => 'col-project-date-modified',
        ]
    );

    echo $view->render(
        'MauticCoreBundle:Helper:tableheader.html.php',
        [
            'sessionVar' => 'projects',
            'orderBy'    => 'p.id',
            'text'       => 'mautic.core.id',
            'class'      => 'visible-md visible-lg col-project-id',
        ]
    );
    ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $mauticTemplateVars['item'] = $item; ?>
                <tr>
                    <td>
                        <?php
            echo $view->render(
                'MauticCoreBundle:Helper:list_actions.html.php',
                [
                    'item'            => $item,
                    'templateButtons' => [
                        'edit'   => $permissions['project:project:edit'],
                        'delete' => $permissions['project:project:delete'],
                    ],
                    'routeBase'  => 'project',
                    'langVar'    => 'project',
                    'nameGetter' => $nameGetter,
                ]
            );
                ?>
                    </td>
                    <td>
                        <div>
                            <?php if ($permissions['project:project:edit']) : ?>
                                <a href="<?php echo $view['router']->path(
                                    'mautic_project_action',
                                    ['objectAction' => 'view', 'objectId' => $item->getId()]
                                ); ?>" data-toggle="ajax">
                                    <?php echo $this->escape($item->getName()); ?>
                                </a>
                            <?php else : ?>
                                <?php echo $item->getName(); ?>
                            <?php endif; ?>
                            <?php echo $view['content']->getCustomContent('project.name', $mauticTemplateVars); ?>
                        </div>
                        <?php if ($description = $item->getDescription()): ?>
                            <div class="text-muted mt-4">
                                <small><?php echo $description; ?></small>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td><?php echo $view['date']->toFull($item->getDateAdded()); ?></td>
                    <td><?php echo $view['date']->toFull($item->getDateModified()); ?></td>

                    <td class="visible-md visible-lg"><?php echo $item->getId(); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="panel-footer">
            <?php echo $view->render(
                'MauticCoreBundle:Helper:pagination.html.php',
                [
                    'totalItems' => count($items),
                    'page'       => $page,
                    'limit'      => $limit,
                    'baseUrl'    => $view['router']->path('mautic_project_index'),
                    'sessionVar' => 'project',
                ]
            ); ?>
        </div>
    </div>
<?php else: ?>
    <?php echo $view->render('MauticCoreBundle:Helper:noresults.html.php'); ?>
<?php endif; ?>
