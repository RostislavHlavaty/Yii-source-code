<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * SendCampaignsCommand
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class SendCampaignsCommand extends CConsoleCommand 
{
    // lock name
    protected $_lockName = 'send-campaigns';
    
    // default sleeping time, in microseconds!
    protected $_sleepTime = 50;

    // counter for recursive calls
    protected $_recursiveCallsCount = 0;
    
    // current campaign
    protected $_campaign;
    
    // flag 
    protected $_restoreStates = true;
    
    // flag
    protected $_improperShutDown = false;
    
    // sender status code
    protected $_senderStatusCode = 0;
    
    // global command arguments
    
    // whether allow recursive calls
    public $allow_recursive = 0;
    
    // keep a limit though
    public $max_recursive_calls = 10;
    
    // from where to start
    public $campaigns_offset;
    
    // how many
    public $campaigns_limit;
    
    // where to start
    public $subscribers_offset = 0;
    
    // how many
    public $subscribers_limit = 0;
    
    // number identifier for this particular campaign only!
    public $parallel_process_number = 0;
    
    // number identifier in all parallel campaigns
    public $total_parallel_process_number = 0;
    
    // total count for this particular campaign only!
    public $parallel_processes_count = 0;
    
    // total count for all campaigns
    public $total_parallel_processes_count = 0;
    
    // microseconds to sleep for this instance
    public $usleep = 0;
    
    public function init()
    {
        parent::init();
        
        // this will catch exit signals and restore states
        if (CommonHelper::functionExists('pcntl_signal')) {
            declare(ticks = 1);
            pcntl_signal(SIGINT,  array($this, '_handleExternalSignal'));  
            pcntl_signal(SIGTERM, array($this, '_handleExternalSignal'));
            pcntl_signal(SIGHUP,  array($this, '_handleExternalSignal'));
        }
        
        register_shutdown_function(array($this, '_restoreStates'));
        Yii::app()->attachEventHandler('onError', array($this, '_restoreStates'));
        Yii::app()->attachEventHandler('onException', array($this, '_restoreStates'));
    }
    
    public function _handleExternalSignal($signalNumber)
    {
        // this will trigger all the handlers attached via register_shutdown_function
        $this->_improperShutDown = true;
        exit;
    }

    public function _restoreStates($event = null)
    {
        if (!$this->_restoreStates) {
            return;
        }
        $this->_restoreStates = false;
        
        // remove the lock
        $this->removeLock();

        // called as a callback from register_shutdown_function
        // must pass only if improper shutdown in this case
        if ($event === null && !$this->_improperShutDown) {
            return;
        }

        if (!empty($this->_campaign) && $this->_campaign instanceof Campaign) {
            if ($this->_campaign->isProcessing) {
                $this->_campaign->status = Campaign::STATUS_SENDING;
                $this->_campaign->save(false);
            }
        }
    }
    
    public function actionIndex() 
    {
        $sleepTime = $this->_sleepTime;
        if ($this->usleep > 0) {
            $sleepTime += $this->usleep;
        }
        usleep($sleepTime);
        
        if (!$this->acquireLock()) {
            return 1;
        }   

        $options = Yii::app()->options->resetLoaded();
        $limit   = (int)$this->campaigns_limit;
        $offset  = (int)$this->campaigns_offset;
        
        if ($this->campaigns_limit === null) {
            $limit = (int)$options->get('system.cron.send_campaigns.campaigns_at_once', 10);
        }
        
        if ($this->campaigns_offset === null) {
            $offset     = (int)$options->get('system.cron.send_campaigns.campaigns_offset', 0);
            $nextOffset = $offset + $limit;
        } else {
            $nextOffset = (int)$this->campaigns_offset + $limit;
        }
        
        // if single campaign or parallel but last process
        if (in_array($this->total_parallel_process_number, array(0, $this->total_parallel_processes_count))) {
            $options->set('system.cron.send_campaigns.campaigns_offset', $nextOffset);
        }
        
        $criteria = new CDbCriteria();
        $criteria->select = 't.campaign_id';
        
        if ((int)$this->parallel_process_number <= 1) {
            $criteria->addInCondition('t.status', array(Campaign::STATUS_SENDING, Campaign::STATUS_PENDING_SENDING));
        } else {
            $criteria->addInCondition('t.status', array(Campaign::STATUS_SENDING, Campaign::STATUS_PENDING_SENDING, Campaign::STATUS_PROCESSING));
        }
        
        $criteria->addCondition('t.send_at <= NOW()');
        $criteria->order    = 't.campaign_id ASC';
        $criteria->offset   = $offset;
        $criteria->limit    = $limit;
        
        // offer a chance to alter this criteria.
        $criteria = Yii::app()->hooks->applyFilters('console_send_campaigns_command_find_campaigns_criteria', $criteria, $this);
        
        // and find all campaigns matching the criteria
        $campaigns = Campaign::model()->findAll($criteria);
        
        if (empty($campaigns)) {
            if (in_array($this->total_parallel_process_number, array(0, $this->total_parallel_processes_count))) {
                $options->set('system.cron.send_campaigns.campaigns_offset', 0);
            }
            return $this->redoIndexAction();
        }
        
        $campaignIds = array();
        foreach ($campaigns as $campaign) {
            $campaignIds[] = $campaign->campaign_id;
        }
        
        if ($memoryLimit = $options->get('system.cron.send_campaigns.memory_limit')) {
            ini_set('memory_limit', $memoryLimit);
        }
        
        foreach ($campaignIds as $campaignId) {
            $this->_campaign = Campaign::model()->findByPk((int)$campaignId);

            if (empty($this->_campaign) || $this->_campaign->status == Campaign::STATUS_SENT) {
                $this->_campaign = null;
                continue;
            }

            $this->_campaign->attachBehavior('sender', array(
                'class'                     => 'console.components.behaviors.CampaignSenderBehavior',
                'parallel_process_number'   => (int)$this->parallel_process_number,
                'parallel_processes_count'  => (int)$this->parallel_processes_count,
                'offset'                    => (int)$this->subscribers_offset,
                'limit'                     => (int)$this->subscribers_limit,
            ));
            
            $this->_senderStatusCode = $this->_campaign->sender->sendCampaign();
            
            // when a single campaign and halted from web interface
            if ($this->_senderStatusCode == 100 && $this->allow_recursive && count($campaignIds) == 1) {
                $this->_recursiveCallsCount = $this->max_recursive_calls + 1;
                break;    
            }
        }
        
        if (in_array($this->total_parallel_process_number, array(0, $this->total_parallel_processes_count))) {
            $criteria->offset = (int)$options->get('system.cron.send_campaigns.campaigns_offset', 0);
            $campaigns = Campaign::model()->findAll($criteria);
            if (empty($campaigns)) {
                $options->set('system.cron.send_campaigns.campaigns_offset', 0);
            }
        }
        
        return $this->redoIndexAction();
    }

    protected function defaultParamsChanged()
    {
        $defaultParams = array(
            'campaigns_offset'                => null,
            'campaigns_limit'                 => null,
            'subscribers_offset'              => 0,
            'subscribers_limit'               => 0,
            'parallel_process_number'         => 0,
            'total_parallel_process_number'   => 0,
            'parallel_processes_count'        => 0,
            'total_parallel_processes_count'  => 0,
            'usleep'                          => 0,
        );
        
        $changed = false;
        foreach ($defaultParams as $name => $value) {
            if ($this->$name !== $value) {
                $changed = true;
                break;
            }
        }
        
        return $changed;
    }
    
    protected function redoIndexAction()
    {
        if (!$this->allow_recursive || $this->defaultParamsChanged()) {
            return 0;
        }

        $this->removeLock();
        
        if ($this->_recursiveCallsCount >= $this->max_recursive_calls) {
            $this->_restoreStates = false;
            return 0;
        }
        
        $this->_recursiveCallsCount++;
        
        return $this->actionIndex();
    }
    
    protected function removeLock()
    {
        if (!$this->allow_recursive || $this->defaultParamsChanged()) {
            return $this;
        }
        
        Yii::app()->mutex->release($this->_lockName);
        return $this;
    }

    protected function acquireLock()
    {
        if (Yii::app()->options->resetLoaded()->get('system.common.site_status', 'offline') != 'online') {
            return false;
        }
        
        if (!$this->allow_recursive || $this->defaultParamsChanged()) {
            return true;
        }
        
        return Yii::app()->mutex->acquire($this->_lockName, 5);
    }
    

}