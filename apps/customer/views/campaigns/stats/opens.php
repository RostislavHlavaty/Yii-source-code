<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This file is part of the MailWizz EMA application.
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.3
 */
 
?>

<div class="col-lg-4">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><?php echo Yii::t('campaign_reports', 'Open rate');?></h3>
        </div>
        <div class="panel-body" style="height:250px;">
            <h3 class="tracking-heading"><?php echo $campaign->stats->getUniqueOpensRate(true);?>%</h3>
            <ul class="list-group">
                <li class="list-group-item"><span class="badge"><?php echo $campaign->stats->getProcessedCount(true);?></span> <?php echo Yii::t('campaign_reports', 'Processed');?></li>
                <li class="list-group-item"><span class="badge"><?php echo $campaign->stats->getUniqueOpensCount(true);?></span> <?php echo Yii::t('campaign_reports', 'Unique opens');?></li>
                <li class="list-group-item active"><span class="badge"><?php echo $campaign->stats->getUniqueOpensRate(true);?>%</span> <?php echo Yii::t('campaign_reports', 'Unique opens rate');?></li>
                <li class="list-group-item"><span class="badge"><?php echo $campaign->stats->getOpensCount(true);?></span> <?php echo Yii::t('campaign_reports', 'All opens');?></li>
                <li class="list-group-item active"><span class="badge"><?php echo $campaign->stats->getOpensRate(true);?>%</span> <?php echo Yii::t('campaign_reports', 'All opens rate');?></li>
            </ul>
            <div class="clearfix"><!-- --></div>
        </div>
        <div class="panel-footer">
            <div class="pull-right">
                <a href="<?php echo $this->createUrl('campaign_reports/open', array('campaign_uid' => $campaign->campaign_uid));?>" class="btn btn-primary btn-xs"><?php echo Yii::t('campaign_reports', 'View details');?></a>
            </div>
            <div class="clearfix"><!-- --></div>
        </div>
    </div>
</div>