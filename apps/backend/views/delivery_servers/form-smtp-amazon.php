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

/**
 * This hook gives a chance to prepend content or to replace the default view content with a custom content.
 * Please note that from inside the action callback you can access all the controller view
 * variables via {@CAttributeCollection $collection->controller->data}
 * In case the content is replaced, make sure to set {@CAttributeCollection $collection->renderContent} to false 
 * in order to stop rendering the default content.
 * @since 1.3.3.1
 */
$hooks->doAction('before_view_file_content', $viewCollection = new CAttributeCollection(array(
    'controller'    => $this,
    'renderContent' => true,
)));

// and render if allowed
if ($viewCollection->renderContent) {
    $this->renderPartial('_confirm-form');
    /**
     * This hook gives a chance to prepend content before the active form or to replace the default active form entirely.
     * Please note that from inside the action callback you can access all the controller view variables 
     * via {@CAttributeCollection $collection->controller->data}
     * In case the form is replaced, make sure to set {@CAttributeCollection $collection->renderForm} to false 
     * in order to stop rendering the default content.
     * @since 1.3.3.1
     */
    $hooks->doAction('before_active_form', $collection = new CAttributeCollection(array(
        'controller'    => $this,
        'renderForm'    => true,
    )));
    
    // and render if allowed
    if ($collection->renderForm) {
        $form = $this->beginWidget('CActiveForm');
        ?>
        <div class="box box-primary">
            <div class="box-header">
                <div class="pull-left">
                    <h3 class="box-title"><span class="glyphicon glyphicon-send"></span> <?php echo $pageHeading;?></h3>
                </div>
                <div class="pull-right">
                    <?php echo CHtml::link(Yii::t('app', 'Cancel'), array('delivery_servers/index'), array('class' => 'btn btn-primary btn-xs', 'title' => Yii::t('app', 'Cancel')));?>
                </div>
                <div class="clearfix"><!-- --></div>
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
                <div class="form-group col-lg-3">
                    <?php echo $form->labelEx($server, 'hostname');?>
                    <?php echo $form->textField($server, 'hostname', $server->getHtmlOptions('hostname')); ?>
                    <?php echo $form->error($server, 'hostname');?>
                </div>
                <div class="form-group col-lg-3">
                    <?php echo $form->labelEx($server, 'from');?>
                    <?php echo $form->textField($server, 'from', $server->getHtmlOptions('from')); ?>
                    <?php echo $form->error($server, 'from');?>
                </div>    
                <div class="form-group col-lg-3">
                    <?php echo $form->labelEx($server, 'username');?>
                    <?php echo $form->textField($server, 'username', $server->getHtmlOptions('username')); ?>
                    <?php echo $form->error($server, 'username');?>
                </div>
                <div class="form-group col-lg-3">
                    <?php echo $form->labelEx($server, 'password');?>
                    <?php echo $form->textField($server, 'password', $server->getHtmlOptions('password', array('value' => ''))); ?>
                    <?php echo $form->error($server, 'password');?>
                </div>
                <div class="clearfix"><!-- --></div>
                <div class="form-group col-lg-3">
                    <?php echo $form->labelEx($server, 'port');?>
                    <?php echo $form->textField($server, 'port', $server->getHtmlOptions('port')); ?>
                    <?php echo $form->error($server, 'port');?>
                </div>
                <div class="form-group col-lg-3">
                    <?php echo $form->labelEx($server, 'protocol');?>
                    <?php echo $form->dropDownList($server, 'protocol', $server->getProtocolsArray(), $server->getHtmlOptions('protocol')); ?>
                    <?php echo $form->error($server, 'protocol');?>
                </div>
                <div class="form-group col-lg-3">
                    <?php echo $form->labelEx($server, 'timeout');?>
                    <?php echo $form->textField($server, 'timeout', $server->getHtmlOptions('timeout')); ?>
                    <?php echo $form->error($server, 'timeout');?>
                </div>
                <div class="form-group col-lg-3">
                    <?php echo $form->labelEx($server, 'probability');?>
                    <?php echo $form->dropDownList($server, 'probability', $server->getProbabilityArray(), $server->getHtmlOptions('probability', array('data-placement' => 'left'))); ?>
                    <?php echo $form->error($server, 'probability');?>
                </div>
                <div class="clearfix"><!-- --></div>
                <div class="form-group col-lg-3">
                    <?php echo $form->labelEx($server, 'bounce_server_id');?>
                    <?php echo $form->dropDownList($server, 'bounce_server_id', $server->getBounceServersArray(), $server->getHtmlOptions('bounce_server_id')); ?>
                    <?php echo $form->error($server, 'bounce_server_id');?>
                </div>
                <div class="form-group col-lg-3">
                    <?php echo $form->labelEx($server, 'hourly_quota');?>
                    <?php echo $form->textField($server, 'hourly_quota', $server->getHtmlOptions('hourly_quota')); ?>
                    <?php echo $form->error($server, 'hourly_quota');?>
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
                <div class="col-lg-12">
                    <?php $this->renderPartial('_customer', compact('form'));?> 
                </div>
                <div class="clearfix"><!-- --></div>
                <div class="col-lg-12">
                    <?php $this->renderPartial('_policies', compact('form'));?> 
                </div>
                <div class="clearfix"><!-- --></div> 
                <div class="col-lg-12">   
                    <?php $this->renderPartial('_additional-headers');?>  
                </div>  
                <div class="clearfix"><!-- --></div>                               
            </div>
            <div class="box-footer">
                <div class="pull-right">
                    <button type="submit" class="btn btn-primary btn-submit" data-loading-text="<?php echo Yii::t('app', 'Please wait, processing...');?>"><?php echo Yii::t('app', 'Save changes');?></button>
                </div>
                <div class="clearfix"><!-- --></div>
            </div>
        </div>
        <?php 
        $this->endWidget(); 
    }
    /**
     * This hook gives a chance to append content after the active form.
     * Please note that from inside the action callback you can access all the controller view variables 
     * via {@CAttributeCollection $collection->controller->data}
     * @since 1.3.3.1
     */
    $hooks->doAction('after_active_form', new CAttributeCollection(array(
        'controller'      => $this,
        'renderedForm'    => $collection->renderForm,
    )));
    ?>
    <div class="callout callout-info">
        <?php 
        $text = 'If you already have an account with <a href="http://aws.amazon.com/ses/" target="_blank">AMAZON SES (Simple Email Service)</a> then just add the credentials here in order to start sending emails with the AMAZON account.
        <br />
        Note: You can find your AWS SES SMTP settings in the AWS console: <a href="https://console.aws.amazon.com/ses/home#smtp-settings:" target="_blank">https://console.aws.amazon.com/ses/home#smtp-settings:</a>';
        echo Yii::t('servers', StringHelper::normalizeTranslationString($text));
        ?>
    </div>
<?php 
}
/**
 * This hook gives a chance to append content after the view file default content.
 * Please note that from inside the action callback you can access all the controller view
 * variables via {@CAttributeCollection $collection->controller->data}
 * @since 1.3.3.1
 */
$hooks->doAction('after_view_file_content', new CAttributeCollection(array(
    'controller'        => $this,
    'renderedContent'   => $viewCollection->renderContent,
)));