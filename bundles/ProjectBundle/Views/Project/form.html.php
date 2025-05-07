<?php

$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'project');
$id     = $form->vars['data']->getId();

if (!empty($id)) {
    $header = $view['translator']->trans('mautic.project.menu.edit', ['%name%' => $view->escape($entity->getName())]);
} else {
    $header = $view['translator']->trans('mautic.project.menu.new');
}

$view['slots']->set('headerTitle', $header);

echo $view['form']->start($form);
?>

<div class="box-layout">
    <div class="col-md-9 bg-white height-auto">
        <div class="row">
            <div class="col-xs-12">
                <!-- start: tab-content -->
                <div class="tab-content pa-md">
                    <div class="tab-pane fade in active bdr-w-0" id="details">
                        <div class="row">
                            <div class="col-xs-12">
                                <?php echo $view['form']->row($form['name']); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-12">
                                <?php echo $view['form']->row($form['description']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php echo $view['form']->end($form); ?>
