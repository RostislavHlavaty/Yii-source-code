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

<div class="notification">
    <?php echo Yii::t('lists', 'A new subscriber has been added to your list.');?><br />
    <?php echo Yii::t('lists', 'List name');?>: <?php echo $list->name;?><br />
    <?php echo Yii::t('lists', 'Subscriber email');?>: <?php echo $subscriber->email;?><br />
</div>