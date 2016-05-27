<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>


<?php

foreach ($chartItems as $key => $chartData) {
    ?>
    <div style="float: left; width: <?php echo $width; ?>%; ">

<div class="chart-wrapper" >
    <div class="pt-sd pr-md pb-md pl-md">
        <div class="chart-legend pull-left-lg"><h4><?php echo $columnName[$key]; ?></h4></div> <div class="clearfix"></div>
        <div class="pull-left"> <a href="<?php echo $link[$key]; ?>">  <?php echo $chartData[0]['value'] ?> Contacts</div></a>
        <div class="clearfix"></div>
        <div style="height:<?php echo $chartHeight-60; ?>px;">

            <canvas class="chart <?php echo $chartType; ?>-chart"
                    style="font-size: 9px!important;"><?php echo json_encode($chartData); ?></canvas>

        </div>
        <div class="legend" style="font-size: 9px;"></div>
    </div>
</div>
    <?php if ($stages[$key]) { ?>
        <hr>
        <div class="chart-wrapper">
                <div class="pt-sd pr-md pb-md pl-md">
                    <div class="chart-legend"><h5><?php echo $view['translator']->trans('mautic.lead.lifecycle.graph.stage.cycle'); ?></h5></div>
                    <div class="clearfix"></div>
                    <div style="height:<?php echo $chartHeight-80; ?>px;">
                     <canvas class="chart simple-bar-chart" style="font-size: 9px!important;"><?php echo json_encode($stages[$key]); ?></canvas>
                    </div>
                    <div class="legend" style="font-size: 9px;"></div>
                </div>
        </div>
    <?php } ?>
    </div>
<?php } ?>