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
if ($viewCollection->renderContent) { ?>
    <?php if (!$list->isNewRecord) { ?>
    <div class="pull-left">
        <?php $this->widget('customer.components.web.widgets.MailListSubNavWidget', array(
            'list' => $list,
        ))?>
    </div>
    <div class="clearfix"><!-- --></div>
    <hr />
    <?php 
    }
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
                    <h3 class="box-title">
                        <span class="glyphicon glyphicon-list-alt"></span> <?php echo Yii::t('lists', 'Please fill in your mail list details.');?>
                    </h3>
                </div>
                <div class="pull-right">
                    <?php echo CHtml::link(Yii::t('app', 'Cancel'), array('lists/index'), array('class' => 'btn btn-primary btn-xs', 'title' => Yii::t('app', 'Cancel')));?>
                </div>
                <div class="clearfix"><!-- --></div>
            </div>
            <div class="box-body">
                <?php 
                /**
                 * This hook gives a chance to prepend content before the active form fields.
                 * Please note that from inside the action callback you can access all the controller view variables 
                 * via {@CAttributeCollection $collection->controller->data}
                 * 
                 * @since 1.3.3.1
                 */
                $hooks->doAction('before_active_form_fields', new CAttributeCollection(array(
                    'controller'    => $this,
                    'form'          => $form    
                )));
                ?>
                <div class="clearfix"><!-- --></div>
                <div class="col-lg-6 no-margin-left">
                    <div class="box box-primary">
                        <div class="box-header">
                            <h3 class="box-title"><?php echo Yii::t('lists', 'General data');?></h3>
                        </div>
                        <div class="box-body">
                            <div class="form-group">
                                <?php echo $form->labelEx($list, 'name');?>
                                <?php echo $form->textField($list, 'name', $list->getHtmlOptions('name')); ?>
                                <?php echo $form->error($list, 'name');?>
                            </div>
                            <div class="form-group">
                                <?php echo $form->labelEx($list, 'description');?>
                                <?php echo $form->textArea($list, 'description', $list->getHtmlOptions('description', array('rows' => 5))); ?>
                                <?php echo $form->error($list, 'description');?>
                            </div>
                            <div class="form-group col-lg-6">
                                <?php echo $form->labelEx($list, 'opt_in');?>
                                <?php echo $form->dropDownList($list, 'opt_in', $list->getOptInArray(), $list->getHtmlOptions('opt_in')); ?>
                                <?php echo $form->error($list, 'opt_in');?>
                            </div>
                            <div class="form-group col-lg-6">
                                <?php echo $form->labelEx($list, 'opt_out');?>
                                <?php echo $form->dropDownList($list, 'opt_out', $list->getOptOutArray(), $list->getHtmlOptions('opt_out')); ?>
                                <?php echo $form->error($list, 'opt_out');?>
                            </div>
                            <div class="clearfix"><!-- --></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 no-margin-left">
                    <div class="box box-primary">
                        <div class="box-header">
                            <h3 class="box-title"><?php echo Yii::t('lists', 'Defaults');?></h3>
                        </div>
                        <div class="box-body">
                            <div class="form-group">
                                <?php echo $form->labelEx($listDefault, 'from_name');?>
                                <?php echo $form->textField($listDefault, 'from_name', $listDefault->getHtmlOptions('from_name')); ?>
                                <?php echo $form->error($listDefault, 'from_name');?>
                            </div>
                            <div class="form-group">
                                <?php echo $form->labelEx($listDefault, 'from_email');?>
                                <?php echo $form->textField($listDefault, 'from_email', $listDefault->getHtmlOptions('from_email')); ?>
                                <?php echo $form->error($listDefault, 'from_email');?>
                            </div>
                            <div class="form-group">
                                <div>
                                    <?php echo $form->labelEx($listDefault, 'reply_to');?>
                                </div>
                                <?php echo $form->textField($listDefault, 'reply_to', $listDefault->getHtmlOptions('reply_to')); ?>
                                <?php echo $form->error($listDefault, 'reply_to');?>
                            </div>
                            <div class="form-group">
                                <?php echo $form->labelEx($listDefault, 'subject');?>
                                <?php echo $form->textField($listDefault, 'subject', $listDefault->getHtmlOptions('subject')); ?>
                                <?php echo $form->error($listDefault, 'subject');?>
                            </div>
                            <div class="clearfix"><!-- --></div>
                        </div>
                    </div>
                </div>
                <div class="clearfix"><!-- --></div>
                
                <div class="col-lg-12 no-margin-left">
                    <div class="box box-primary">
                        <div class="box-header">
                            <h3 class="box-title"><?php echo Yii::t('lists', 'Notifications');?></h3>
                        </div>
                        <div class="box-body">
                            <div class="col-lg-6 no-margin-left">
                                <div class="form-group">
                                    <?php echo $form->labelEx($listCustomerNotification, 'subscribe');?>
                                    <?php echo $form->dropDownList($listCustomerNotification, 'subscribe', $listCustomerNotification->getYesNoDropdownOptions(),$listCustomerNotification->getHtmlOptions('subscribe')); ?>
                                    <?php echo $form->error($listCustomerNotification, 'subscribe');?>
                                </div>
                                <div class="form-group">
                                    <?php echo $form->labelEx($listCustomerNotification, 'unsubscribe');?>
                                    <?php echo $form->dropDownList($listCustomerNotification, 'unsubscribe', $listCustomerNotification->getYesNoDropdownOptions(),$listCustomerNotification->getHtmlOptions('unsubscribe')); ?>
                                    <?php echo $form->error($listCustomerNotification, 'unsubscribe');?>
                                </div>
                            </div>
                            <div class="col-lg-6 no-margin-left">
                                <div class="form-group">
                                    <?php echo $form->labelEx($listCustomerNotification, 'subscribe_to');?>
                                    <?php echo $form->textField($listCustomerNotification, 'subscribe_to', $listCustomerNotification->getHtmlOptions('subscribe_to')); ?>
                                    <?php echo $form->error($listCustomerNotification, 'subscribe_to');?>
                                </div>
                                <div class="form-group">
                                    <?php echo $form->labelEx($listCustomerNotification, 'unsubscribe_to');?>
                                    <?php echo $form->textField($listCustomerNotification, 'unsubscribe_to', $listCustomerNotification->getHtmlOptions('unsubscribe_to')); ?>
                                    <?php echo $form->error($listCustomerNotification, 'unsubscribe_to');?>
                                </div>
                            </div>
                            <div class="clearfix"><!-- --></div>
                        </div>
                    </div>
                </div>
                <div class="clearfix"><!-- --></div>
                <div class="box box-primary">
                    <div class="box-header">
                        <div class="pull-left">
                            <h3 class="box-title"><?php echo Yii::t('lists', 'Company details');?> <small>(<?php echo Yii::t('lists', 'defaults to <a href="{href}">account company</a>', array('{href}' => $this->createUrl('account/company')));?>)</small></h3>
                        </div>
                        <div class="pull-right"></div>
                        <div class="clearfix"><!-- --></div>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <?php echo $form->labelEx($listCompany, 'name');?>
                            <?php echo $form->textField($listCompany, 'name', $listCompany->getHtmlOptions('name')); ?>
                            <?php echo $form->error($listCompany, 'name');?>
                        </div>
                        <div class="clearfix"><!-- --></div>
                        <div class="col-lg-6 no-margin-left">
                            <div class="form-group">
                                <?php echo $form->labelEx($listCompany, 'country_id');?>
                                <?php echo $listCompany->getCountriesDropDown(); ?>
                                <?php echo $form->error($listCompany, 'country_id');?>
                            </div>    
                        </div>
                        <div class="col-lg-6 no-margin-left">
                            <div class="form-group">
                                <?php echo $form->labelEx($listCompany, 'zone_id');?>
                                <?php echo $listCompany->getZonesDropDown(); ?>
                                <?php echo $form->error($listCompany, 'zone_id');?>
                            </div>    
                        </div>
                        <div class="clearfix"><!-- --></div>
                        <div class="col-lg-6 no-margin-left">
                            <div class="form-group">
                                <?php echo $form->labelEx($listCompany, 'address_1');?>
                                <?php echo $form->textField($listCompany, 'address_1', $listCompany->getHtmlOptions('address_1')); ?>
                                <?php echo $form->error($listCompany, 'address_1');?>
                            </div>    
                        </div>
                        <div class="col-lg-6 no-margin-left">
                            <div class="form-group">
                                <?php echo $form->labelEx($listCompany, 'address_2');?>
                                <?php echo $form->textField($listCompany, 'address_2', $listCompany->getHtmlOptions('address_2')); ?>
                                <?php echo $form->error($listCompany, 'address_2');?>
                            </div>    
                        </div>
                        <div class="clearfix"><!-- --></div>
                        <div class="col-lg-3 no-margin-left zone-name-wrap">
                            <div class="form-group">
                                <?php echo $form->labelEx($listCompany, 'zone_name');?>
                                <?php echo $form->textField($listCompany, 'zone_name', $listCompany->getHtmlOptions('zone_name')); ?>
                                <?php echo $form->error($listCompany, 'zone_name');?>
                            </div>    
                        </div>
                        <div class="col-lg-3 no-margin-left city-wrap">
                            <div class="form-group">
                                <?php echo $form->labelEx($listCompany, 'city');?>
                                <?php echo $form->textField($listCompany, 'city', $listCompany->getHtmlOptions('city')); ?>
                                <?php echo $form->error($listCompany, 'city');?>
                            </div>    
                        </div>
                        <div class="col-lg-3 no-margin-left zip-wrap">
                            <div class="form-group">
                                <?php echo $form->labelEx($listCompany, 'zip_code');?>
                                <?php echo $form->textField($listCompany, 'zip_code', $listCompany->getHtmlOptions('zip_code')); ?>
                                <?php echo $form->error($listCompany, 'zip_code');?>
                            </div>    
                        </div>
                        <div class="col-lg-3 no-margin-left phone-wrap">
                            <div class="form-group">
                                <?php echo $form->labelEx($listCompany, 'phone');?>
                                <?php echo $form->textField($listCompany, 'phone', $listCompany->getHtmlOptions('phone')); ?>
                                <?php echo $form->error($listCompany, 'phone');?>
                            </div>    
                        </div>
                        <div class="clearfix"><!-- --></div>
                        <div class="form-group">
                            <?php echo $form->labelEx($listCompany, 'address_format');?> [<a data-toggle="modal" href="#company-available-tags-modal"><?php echo Yii::t('lists', 'Available tags');?></a>]
                            <?php echo $form->textArea($listCompany, 'address_format', $listCompany->getHtmlOptions('address_format', array('rows' => 4))); ?>
                            <?php echo $form->error($listCompany, 'address_format');?>
                        </div>
                        <div class="clearfix"><!-- --></div>
                    </div>
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
     * This hook gives a chance to append content after the active form fields.
     * Please note that from inside the action callback you can access all the controller view variables 
     * via {@CAttributeCollection $collection->controller->data}
     * @since 1.3.3.1
     */
    $hooks->doAction('after_active_form', new CAttributeCollection(array(
        'controller'      => $this,
        'renderedForm'    => $collection->renderForm,
    )));
    ?>
    <div class="modal fade" id="company-available-tags-modal" tabindex="-1" role="dialog" aria-labelledby="company-available-tags-modal-label" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
              <h4 class="modal-title"><?php echo Yii::t('lists', 'Available tags');?></h4>
            </div>
            <div class="modal-body" style="max-height: 300px; overflow-y:scroll;">
                <table class="table table-bordered table-hover table-striped">
                    <tr>
                        <td><?php echo Yii::t('lists', 'Tag');?></td>
                        <td><?php echo Yii::t('lists', 'Required');?></td>
                    </tr>
                    <?php foreach ($listCompany->getAvailableTags() as $tag) { ?>
                    <tr>
                        <td><?php echo CHtml::encode($tag['tag']);?></td>
                        <td><?php echo $tag['required'] ? strtoupper(Yii::t('app', ListCompany::TEXT_YES)) : strtoupper(Yii::t('app', ListCompany::TEXT_NO));?></td>
                    </tr>
                    <?php } ?>
                </table>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo Yii::t('app', 'Close');?></button>
            </div>
          </div>
        </div>
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