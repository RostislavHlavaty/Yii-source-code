<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This file is part of the MailWizz EMA application.
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4
 */
 
 ?>
<ul class="nav nav-tabs" style="border-bottom: 0px;">
    <li class="active">
        <a href="#tab-servers" data-toggle="tab"><?php echo Yii::t('settings', 'Servers');?></a>
    </li>
    <li>
        <a href="#tab-lists" data-toggle="tab"><?php echo Yii::t('settings', 'Lists');?></a>
    </li>
    <li>
        <a href="#tab-campaigns" data-toggle="tab"><?php echo Yii::t('settings', 'Campaigns');?></a>
    </li>
    <li>
        <a href="#tab-qq" data-toggle="tab"><?php echo Yii::t('settings', 'Quota counters');?></a>
    </li>
    <li>
        <a href="#tab-sending" data-toggle="tab"><?php echo Yii::t('settings', 'Sending');?></a>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane active" id="tab-servers">
        <?php $this->renderPartial('option-views/_servers', array('model' => $servers, 'form' => $form));?>
    </div>
    <div class="tab-pane" id="tab-lists">
        <?php $this->renderPartial('option-views/_lists', array('model' => $lists, 'form' => $form));?>
    </div>
    <div class="tab-pane" id="tab-campaigns">
        <?php $this->renderPartial('option-views/_campaigns', array('model' => $campaigns, 'form' => $form));?>
    </div>
    <div class="tab-pane" id="tab-qq">
        <?php $this->renderPartial('option-views/_quota', array('model' => $quotaCounters, 'form' => $form));?>
    </div>
    <div class="tab-pane" id="tab-sending">
        <?php $this->renderPartial('option-views/_sending', array('model' => $sending, 'form' => $form));?>
    </div>
</div>