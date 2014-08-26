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
<div class="box box-primary">
    <div class="box-header">
        <h3 class="box-title"><?php echo Yii::t('settings', 'Delivery settings')?></h3>
    </div>
    <div class="box-body">
        <?php 
        /**
         * This hook gives a chance to prepend content before the active form fields.
         * Please note that from inside the action callback you can access all the controller view variables 
         * via {@CAttributeCollection $collection->controller->data}
         * @since 1.3.3.1
         */
        $hooks->doAction('before_active_form_fields', new CAttributeCollection(array(
            'controller'        => $this,
            'form'              => $form    
        )));
        ?>
        <div class="clearfix"><!-- --></div>
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($cronDeliveryModel, 'memory_limit');?>
            <?php echo $form->dropDownList($cronDeliveryModel, 'memory_limit', $cronDeliveryModel->getMemoryLimitOptions(), $cronDeliveryModel->getHtmlOptions('memory_limit', array('data-placement' => 'right'))); ?>
            <?php echo $form->error($cronDeliveryModel, 'memory_limit');?>
        </div>    
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($cronDeliveryModel, 'campaigns_at_once');?>
            <?php echo $form->textField($cronDeliveryModel, 'campaigns_at_once', $cronDeliveryModel->getHtmlOptions('campaigns_at_once')); ?>
            <?php echo $form->error($cronDeliveryModel, 'campaigns_at_once');?>
        </div>
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($cronDeliveryModel, 'subscribers_at_once');?>
            <?php echo $form->textField($cronDeliveryModel, 'subscribers_at_once', $cronDeliveryModel->getHtmlOptions('subscribers_at_once')); ?>
            <?php echo $form->error($cronDeliveryModel, 'subscribers_at_once');?>
        </div>
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($cronDeliveryModel, 'parallel_processes_per_campaign');?>
            <?php echo $form->textField($cronDeliveryModel, 'parallel_processes_per_campaign', $cronDeliveryModel->getHtmlOptions('parallel_processes_per_campaign')); ?>
            <?php echo $form->error($cronDeliveryModel, 'parallel_processes_per_campaign');?>
        </div>
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($cronDeliveryModel, 'send_at_once');?>
            <?php echo $form->textField($cronDeliveryModel, 'send_at_once', $cronDeliveryModel->getHtmlOptions('send_at_once')); ?>
            <?php echo $form->error($cronDeliveryModel, 'send_at_once');?>
        </div>
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($cronDeliveryModel, 'pause');?>
            <?php echo $form->textField($cronDeliveryModel, 'pause', $cronDeliveryModel->getHtmlOptions('pause')); ?>
            <?php echo $form->error($cronDeliveryModel, 'pause');?>
        </div>
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($cronDeliveryModel, 'emails_per_minute');?>
            <?php echo $form->textField($cronDeliveryModel, 'emails_per_minute', $cronDeliveryModel->getHtmlOptions('emails_per_minute')); ?>
            <?php echo $form->error($cronDeliveryModel, 'emails_per_minute');?>
        </div>    
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($cronDeliveryModel, 'change_server_at');?>
            <?php echo $form->textField($cronDeliveryModel, 'change_server_at', $cronDeliveryModel->getHtmlOptions('change_server_at')); ?>
            <?php echo $form->error($cronDeliveryModel, 'change_server_at');?>
        </div>
        <div class="clearfix"><!-- --></div>
        <?php 
        /**
         * This hook gives a chance to append content after the active form fields.
         * Please note that from inside the action callback you can access all the controller view variables 
         * via {@CAttributeCollection $collection->controller->data}
         * @since 1.3.3.1
         */
        $hooks->doAction('after_active_form_fields', new CAttributeCollection(array(
            'controller'        => $this,
            'form'              => $form    
        )));
        ?>
        <div class="clearfix"><!-- --></div>
    </div>
</div>