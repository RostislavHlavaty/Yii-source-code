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
        <h3 class="box-title"><?php echo Yii::t('settings', 'Importer settings')?></h3>
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
            'controller'    => $this,
            'form'          => $form    
        )));
        ?>
        <div class="clearfix"><!-- --></div>
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($importModel, 'enabled');?>
            <?php echo $form->dropDownList($importModel, 'enabled', $importModel->getYesNoOptions(), $importModel->getHtmlOptions('enabled')); ?>
            <?php echo $form->error($importModel, 'enabled');?>
        </div>
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($importModel, 'file_size_limit');?>
            <?php echo $form->dropDownList($importModel, 'file_size_limit', $importModel->getFileSizeOptions(), $importModel->getHtmlOptions('file_size_limit')); ?>
            <?php echo $form->error($importModel, 'file_size_limit');?>
        </div>
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($importModel, 'memory_limit');?>
            <?php echo $form->dropDownList($importModel, 'memory_limit', $importModel->getMemoryLimitOptions(), $importModel->getHtmlOptions('memory_limit')); ?>
            <?php echo $form->error($importModel, 'memory_limit');?>
        </div>    
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($importModel, 'import_at_once');?>
            <?php echo $form->textField($importModel, 'import_at_once', $importModel->getHtmlOptions('import_at_once')); ?>
            <?php echo $form->error($importModel, 'import_at_once');?>
        </div>
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($importModel, 'pause');?>
            <?php echo $form->textField($importModel, 'pause', $importModel->getHtmlOptions('pause')); ?>
            <?php echo $form->error($importModel, 'pause');?>
        </div>  
        <div class="form-group col-lg-2">
            <?php echo $form->labelEx($importModel, 'check_mime_type');?>
            <?php echo $form->dropDownList($importModel, 'check_mime_type', $importModel->getYesNoOptions(), $importModel->getHtmlOptions('check_mime_type')); ?>
            <?php echo $form->error($importModel, 'check_mime_type');?>
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
            'controller'    => $this,
            'form'          => $form    
        )));
        ?>
        <div class="clearfix"><!-- --></div>
    </div>
</div>
