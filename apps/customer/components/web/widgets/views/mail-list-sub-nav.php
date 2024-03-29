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

<div class="btn-group">
    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
        <?php echo Yii::t('app', 'Quick links');?> <span class="caret"></span>
    </button>
    <?php $this->controller->widget('zii.widgets.CMenu', array(
        'items'         => $this->getNavItems(),
        'htmlOptions'   => array(
            'class' => 'dropdown-menu',
            'role'  => 'menu'
        ),
    ));?>
</div>    