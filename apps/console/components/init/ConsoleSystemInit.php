<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ConsoleSystemInit
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.1
 */
 
class ConsoleSystemInit extends CApplicationComponent 
{
    protected $_hasRanOnBeginRequest = false;
    protected $_hasRanOnEndRequest = false;
    
    public function init()
    {
        parent::init();
        Yii::app()->attachEventHandler('onBeginRequest', array($this, 'runOnBeginRequest'));
        Yii::app()->attachEventHandler('onEndRequest', array($this, 'runOnEndRequest'));
    }
    
    public function runOnBeginRequest(CEvent $event)
    {
        if ($this->_hasRanOnBeginRequest) {
            return;
        }
        
        // if the site offline, stop.
        if (Yii::app()->options->get('system.common.site_status', 'online') != 'online') {
            Yii::app()->end();
        }

        // and mark the event as completed.
        $this->_hasRanOnBeginRequest = true;
    }
    
    public function runOnEndRequest(CEvent $event)
    {
        if ($this->_hasRanOnEndRequest) {
            return;
        }

        // and mark the event as completed.
        $this->_hasRanOnEndRequest = true;
    }
}