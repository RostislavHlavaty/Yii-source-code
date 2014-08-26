<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This file is part of the MailWizz EMA application.
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
?>

<div class="col-lg-6">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><?php echo Yii::t('campaign_reports', 'Top clicked links');?></h3>
        </div>
        <div class="panel-body" style="min-height:100px;">
            <ul class="list-group">
                <?php foreach ($models as $model) { ?>
                <li class="list-group-item">
                    <div class="pull-left">
                        <?php echo $model->getDisplayGridDestination(40);?>
                    </div>
                    <div class="pull-right">
                        <span class="badge"><?php echo $model->counter;?></span>
                    </div>
                    <div class="clearfix"><!-- --></div>
                </li>
                <?php } ?>
            </ul>
        </div>
        <div class="panel-footer">
            <div class="pull-right">
                <a href="<?php echo $this->controller->createUrl('campaign_reports/click', array('campaign_uid' => $campaign->campaign_uid));?>" class="btn btn-primary btn-xs"><?php echo Yii::t('campaign_reports', 'View details');?></a>
            </div>
            <div class="clearfix"><!-- --></div>
        </div>
    </div>
</div>