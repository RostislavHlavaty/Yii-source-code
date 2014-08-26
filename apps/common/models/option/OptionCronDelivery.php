<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * OptionCronDelivery
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class OptionCronDelivery extends OptionBase
{
    // settings category
    protected $_categoryName = 'system.cron.send_campaigns';
    
    // memory limit
    public $memory_limit;
    
    // how many campaigns to process at once
    public $campaigns_at_once = 10;
    
    // how many subscribers should we load at once for each sending campaign
    public $subscribers_at_once = 300;
    
    // after how many emails we should send at once
    public $send_at_once = 100;
    
    // how many seconds should we pause bettwen the batches
    public $pause = 30;
    
    // how many emails should we deliver within a minute
    public $emails_per_minute = 100;
    
    // after what number of emails we should change the delivery server.
    public $change_server_at = 100;
    
    // if parallel processing enabled, how many to spawn
    public $parallel_processes_per_campaign = 3;
    
    public function rules()
    {
        $rules = array(
            array('campaigns_at_once, subscribers_at_once, send_at_once, pause, emails_per_minute, change_server_at, parallel_processes_per_campaign', 'required'),
            array('memory_limit', 'in', 'range' => array_keys($this->getMemoryLimitOptions())),
            array('campaigns_at_once, subscribers_at_once, send_at_once, pause, emails_per_minute, change_server_at', 'numerical', 'integerOnly' => true),
            array('campaigns_at_once', 'numerical', 'min' => 1, 'max' => 100),
            array('subscribers_at_once', 'numerical', 'min' => 5, 'max' => 1000),
            array('send_at_once', 'numerical', 'min' => 0, 'max' => 1000),
            array('pause', 'numerical', 'min' => 5, 'max' => 600),
            array('emails_per_minute', 'numerical', 'min' => 0, 'max' => 1000),
            array('change_server_at', 'numerical', 'min' => 0, 'max' => 1000),
            array('parallel_processes_per_campaign', 'numerical', 'integerOnly' => true, 'min' => 0, 'max' => 10),
        );
        
        return CMap::mergeArray($rules, parent::rules());    
    }
    
    public function attributeLabels()
    {
        $labels = array(
            'memory_limit'          => Yii::t('settings', 'Memory limit'),
            'campaigns_at_once'     => Yii::t('settings', 'Campaigns at once'),
            'subscribers_at_once'   => Yii::t('settings', 'Subscribers at once'),
            'send_at_once'          => Yii::t('settings', 'Send at once'),
            'pause'                 => Yii::t('settings', 'Pause'),
            'emails_per_minute'     => Yii::t('settings', 'Emails per minute'),
            'change_server_at'      => Yii::t('settings', 'Change server at'),
            'parallel_processes_per_campaign' => Yii::t('settings', 'Parallel processes per campaign'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());    
    }
    
    public function attributePlaceholders()
    {
        $placeholders = array(
            'memory_limit'          => null,
            'campaigns_at_once'     => null,
            'subscribers_at_once'   => null,
            'send_at_once'          => null,
            'pause'                 => null,
            'emails_per_minute'     => null,
            'change_server_at'      => null,
            'parallel_processes_per_campaign' => null,
        );
        
        return CMap::mergeArray($placeholders, parent::attributePlaceholders());
    }
    
    public function attributeHelpTexts()
    {
        $texts = array(
            'memory_limit'          => Yii::t('settings', 'The maximum memory amount the delivery process is allowed to use while processing one batch of campaigns.'),
            'campaigns_at_once'     => Yii::t('settings', 'How many campaigns to process at once.'),
            'subscribers_at_once'   => Yii::t('settings', 'How many subscribers to process at once for each loaded campaign.'),
            'send_at_once'          => Yii::t('settings', 'How many emails should we send before pausing(this avoids server flooding and getting blacklisted). Set this to 0 to disable it.'),
            'pause'                 => Yii::t('settings', 'How many seconds to sleep after sending a batch of emails.'),
            'emails_per_minute'     => Yii::t('settings', 'Limit the number of emails sent in one minute. This avoids getting blacklisted by various providers. Set this to 0 to disable it.'),
            'change_server_at'      => Yii::t('settings', 'After how many sent emails we should change the delivery server. This only applies if there are multiple delivery servers. Set this to 0 to disable it.'),
            'parallel_processes_per_campaign' => Yii::t('settings', 'If parallel processing enabled, how many parallel processes to create for each campaign'),
        );
        
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }
}
