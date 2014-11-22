<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

//for defining the jsPlumb anchors
$class = ($event['eventType'] == 'decision') ? 'list-campaign-decision' : 'list-campaign-nondecision list-campaign-' . $event['eventType'];

if (empty($route))
    $route = 'mautic_campaignevent_action';

//generate style if applicable
$cs    = $event['canvasSettings'];
$style = (!empty($cs['droppedX'])) ? ' style="' . "position: absolute; top: {$cs['droppedY']}px; left: {$cs['droppedX']}px;" . '"' : '';
?>
<?php if (empty($update)): ?>
<div <?php echo $style; ?> id="CampaignEvent_<?php echo $event['id'] ?>" class="draggable list-campaign-event <?php echo $class; ?>">
<?php endif; ?>
    <div class="campaign-event-content">
        <div><span class="campaign-event-name"><?php echo $event['name']; ?></span></div>
    </div>
<?php if (empty($update)): ?>
    <div class="campaign-event-buttons hide">
        <a data-toggle="ajaxmodal" data-ignore-removemodal="true" data-target="#CampaignEventModal" href="<?php echo $view['router']->generate($route, array('objectAction' => 'edit', 'objectId' => $event['id'], 'campaignId' => $campaignId)); ?>" class="btn btn-primary btn-xs btn-edit">
            <i class="fa fa-pencil"></i>
        </a>
        <a data-toggle="ajax" data-target="CampaignEvent_<?php echo $event['id'] ?>" data-ignore-formexit="true" data-method="POST" data-hide-loadingbar="true" href="<?php echo $view['router']->generate($route, array('objectAction' => 'delete', 'objectId' => $event['id'], 'campaignId' => $campaignId)); ?>"  class="btn btn-delete btn-danger btn-xs">
            <i class="fa fa-times"></i>
        </a>
    </div>
</div>
<?php endif; ?>