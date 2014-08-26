<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ProcessDeliveryAndBounceLogCommand
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class ProcessDeliveryAndBounceLogCommand extends CConsoleCommand 
{
    // will process the logs and decide what subscribers to be blacklisted.
    public function actionIndex() 
    {
        $options = Yii::app()->options;
        
        $processLimit = (int)$options->get('system.cron.process_delivery_bounce.process_at_once', 100);
        $blacklistAtDeliveryFatalErrors = (int)$options->get('system.cron.process_delivery_bounce.max_fatal_errors', 1);
        $blacklistAtDeliverySoftErrors = (int)$options->get('system.cron.process_delivery_bounce.max_soft_errors', 5);
        $blacklistAtHardBounce = (int)$options->get('system.cron.process_delivery_bounce.max_hard_bounce', 1);
        $blacklistAtSoftBounce = (int)$options->get('system.cron.process_delivery_bounce.max_soft_bounce', 5);
        
        if ($memoryLimit = $options->get('system.cron.process_delivery_bounce.memory_limit')) {
            ini_set('memory_limit', $memoryLimit);
        }
        // subscribers with fatal delivery errors.
        $criteria = new CDbCriteria();
        $criteria->condition = '`t`.`processed` = :processed AND `t`.`status` = :status AND ';
        $criteria->condition .= '(SELECT COUNT(`subscriber_id`) FROM `{{campaign_delivery_log}}` WHERE `subscriber_id` = `t`.`subscriber_id` AND `processed` = :processed AND `status` = :status) >= :counter';
        $criteria->group = '`t`.`subscriber_id`';
        $criteria->limit = $processLimit;
        $criteria->params = array(
            ':processed'    => CampaignDeliveryLog::TEXT_NO,
            ':status'       => CampaignDeliveryLog::STATUS_FATAL_ERROR,
            ':counter'      => $blacklistAtDeliveryFatalErrors,
        );
        $logs = CampaignDeliveryLog::model()->findAll($criteria);
        
        foreach ($logs as $log) {
            $subscriber = ListSubscriber::model()->findByPk((int)$log->subscriber_id);
            if (empty($subscriber)) {
                continue;
            }
            $subscriber->addToBlacklist($log->message);
            $criteria = new CDbCriteria();
            $criteria->compare('subscriber_id', $log->subscriber_id);
            $criteria->compare('processed', CampaignDeliveryLog::TEXT_NO);
            $criteria->compare('status', CampaignDeliveryLog::STATUS_FATAL_ERROR);
            CampaignDeliveryLog::model()->updateAll(array('processed' => CampaignDeliveryLog::TEXT_YES), $criteria);
        }
        
        // subscribers with soft delivery errors.
        $criteria = new CDbCriteria();
        $criteria->condition = '`t`.`processed` = :processed AND `t`.`status` = :status AND ';
        $criteria->condition .= '(SELECT COUNT(`subscriber_id`) FROM `{{campaign_delivery_log}}` WHERE `subscriber_id` = `t`.`subscriber_id` AND `processed` = :processed AND `status` = :status) >= :counter';
        $criteria->group = '`t`.`subscriber_id`';
        $criteria->limit = $processLimit;
        $criteria->params = array(
            ':processed'    => CampaignDeliveryLog::TEXT_NO,
            ':status'       => CampaignDeliveryLog::STATUS_ERROR,
            ':counter'      => $blacklistAtDeliverySoftErrors,
        );
        $logs = CampaignDeliveryLog::model()->findAll($criteria);
        
        foreach ($logs as $log) {
            $subscriber = ListSubscriber::model()->findByPk((int)$log->subscriber_id);
            if (empty($subscriber)) {
                continue;
            }
            $subscriber->addToBlacklist($log->message);
            $criteria = new CDbCriteria();
            $criteria->compare('subscriber_id', $log->subscriber_id);
            $criteria->compare('processed', CampaignDeliveryLog::TEXT_NO);
            $criteria->compare('status', CampaignDeliveryLog::STATUS_ERROR);
            CampaignDeliveryLog::model()->updateAll(array('processed' => CampaignDeliveryLog::TEXT_YES), $criteria);
        }
        
        // subscribers with hard bounces.
        $criteria = new CDbCriteria();
        $criteria->condition = '`t`.`processed` = :processed AND `t`.`bounce_type` = :bounce_type AND ';
        $criteria->condition .= '(SELECT COUNT(`subscriber_id`) FROM `{{campaign_bounce_log}}` WHERE `subscriber_id` = `t`.`subscriber_id` AND `processed` = :processed AND `bounce_type` = :bounce_type) >= :counter';
        $criteria->group = '`t`.`subscriber_id`';
        $criteria->limit = $processLimit;
        $criteria->params = array(
            ':processed'    => CampaignBounceLog::TEXT_NO,
            ':bounce_type'  => CampaignBounceLog::BOUNCE_HARD,
            ':counter'      => $blacklistAtHardBounce,
        );
        $logs = CampaignBounceLog::model()->findAll($criteria);
        
        foreach ($logs as $log) {
            $subscriber = ListSubscriber::model()->findByPk((int)$log->subscriber_id);
            if (empty($subscriber)) {
                continue;
            }
            $subscriber->addToBlacklist($log->message);
            $criteria = new CDbCriteria();
            $criteria->compare('subscriber_id', $log->subscriber_id);
            $criteria->compare('processed', CampaignBounceLog::TEXT_NO);
            $criteria->compare('bounce_type', CampaignBounceLog::BOUNCE_HARD);
            CampaignBounceLog::model()->updateAll(array('processed' => CampaignBounceLog::TEXT_YES), $criteria);
        }
        
        // subscribers with soft bounces.
        $criteria = new CDbCriteria();
        $criteria->condition = '`t`.`processed` = :processed AND `t`.`bounce_type` = :bounce_type AND ';
        $criteria->condition .= '(SELECT COUNT(`subscriber_id`) FROM `{{campaign_bounce_log}}` WHERE `subscriber_id` = `t`.`subscriber_id` AND `processed` = :processed AND `bounce_type` = :bounce_type) >= :counter';
        $criteria->group = '`t`.`subscriber_id`';
        $criteria->limit = $processLimit;
        $criteria->params = array(
            ':processed'    => CampaignBounceLog::TEXT_NO,
            ':bounce_type'  => CampaignBounceLog::BOUNCE_SOFT,
            ':counter'      => $blacklistAtSoftBounce,
        );
        $logs = CampaignBounceLog::model()->findAll($criteria);
        
        foreach ($logs as $log) {
            $subscriber = ListSubscriber::model()->findByPk((int)$log->subscriber_id);
            if (empty($subscriber)) {
                continue;
            }
            $subscriber->addToBlacklist($log->message);
            $criteria = new CDbCriteria();
            $criteria->compare('subscriber_id', $log->subscriber_id);
            $criteria->compare('processed', CampaignBounceLog::TEXT_NO);
            $criteria->compare('bounce_type', CampaignBounceLog::BOUNCE_SOFT);
            CampaignBounceLog::model()->updateAll(array('processed' => CampaignBounceLog::TEXT_YES), $criteria);
        }
        
        return 0;
    }

}
