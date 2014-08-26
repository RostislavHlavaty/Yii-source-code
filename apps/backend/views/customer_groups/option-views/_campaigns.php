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
 <div class="col-lg-12 row-group-category">
    <div class="box box-primary">
        <div class="box-body">
            <div class="clearfix"><!-- --></div>
            <div class="form-group col-lg-4">
                <?php echo $form->labelEx($model, 'max_campaigns');?>
                <?php echo $form->textField($model, 'max_campaigns', $model->getHtmlOptions('max_campaigns')); ?>
                <?php echo $form->error($model, 'max_campaigns');?>
            </div>
            <div class="form-group col-lg-4">
                <?php echo $form->labelEx($model, 'send_to_multiple_lists');?>
                <?php echo $form->dropDownList($model, 'send_to_multiple_lists', $model->getYesNoOptions(), $model->getHtmlOptions('send_to_multiple_lists')); ?>
                <?php echo $form->error($model, 'send_to_multiple_lists');?>
            </div>
            <div class="clearfix"><!-- --></div>
            <div class="form-group col-lg-12">
                <?php echo $form->labelEx($model, 'email_footer');?>
                <?php echo $form->textArea($model, 'email_footer', $model->getHtmlOptions('email_footer')); ?>
                <?php echo $form->error($model, 'email_footer');?>
                <div class="callout callout-info"><?php echo $model->getAttributeHelpText('email_footer');?></div>
            </div>
            <div class="clearfix"><!-- --></div>
        </div>
        <div class="clearfix"><!-- --></div>
    </div>
</div>