<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * NotifyManager
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class NotifyManager extends CApplicationComponent 
{
    
    const ERROR      = 'error';
    const WARNING    = 'warning';
    const INFO       = 'info';
    const SUCCESS    = 'success';    
    
    public $errorClass = 'alert alert-block alert-danger';
    
    public $warningClass = 'alert alert-block alert-warning';
    
    public $infoClass = 'alert alert-block alert-info';
    
    public $successClass = 'alert alert-block alert-success';
    
    public $htmlWrapper = '<div class="%s">%s</div>';
    
    public $htmlCloseButton = '<button type="button" class="close" data-dismiss="alert">&times;</button>';
    
    public $htmlHeading = '<p>%s</p>';

    
    /**
     * NotifyManager::show()
     * 
     * @return string
     */
    public function show()
    {
        $output = '';
        
        $error      = Yii::app()->user->getFlash('__notify_error', array());
        $warning    = Yii::app()->user->getFlash('__notify_warning', array());
        $info       = Yii::app()->user->getFlash('__notify_info', array());
        $success    = Yii::app()->user->getFlash('__notify_success', array());
        
        $error      = is_array($error)      ? array_unique($error)      : array();
        $warning    = is_array($warning)    ? array_unique($warning)    : array();
        $info       = is_array($info)       ? array_unique($info)       : array();
        $success    = is_array($success)    ? array_unique($success)    : array();

        if (count($error) > 0) {
            $liItems = array();
            foreach ($error as $message) {
                $liItems[] = CHtml::tag('li', array(), $message);
            }
            $ul = CHtml::tag('ul', array(), implode("\n", $liItems));
            
            $content = '';
            if ($this->htmlCloseButton) {
                $content.= $this->htmlCloseButton;
            }
            if (($heading = $this->getErrorHeading()) && $this->htmlHeading) {
                $content.= sprintf($this->htmlHeading, $heading);
            }
            $content.= $ul;
            $output .= sprintf($this->htmlWrapper, $this->errorClass, $content);
        }
        
        if (count($warning) > 0) {
            $liItems = array();
            foreach ($warning as $message) {
                $liItems[] = CHtml::tag('li', array(), $message);
            }
            $ul = CHtml::tag('ul', array(), implode("\n", $liItems));
            
            $content = '';
            if ($this->htmlCloseButton) {
                $content.= $this->htmlCloseButton;
            }
            if (($heading = $this->getWarningHeading()) && $this->htmlHeading) {
                $content.= sprintf($this->htmlHeading, $heading);
            }
            $content.= $ul;
            $output .= sprintf($this->htmlWrapper, $this->warningClass, $content);
        }
        
        if (count($info) > 0) {
            $liItems = array();
            foreach ($info as $message) {
                $liItems[] = CHtml::tag('li', array(), $message);
            }
            $ul = CHtml::tag('ul', array(), implode("\n", $liItems));
            
            $content = '';
            if ($this->htmlCloseButton) {
                $content.= $this->htmlCloseButton;
            }
            if (($heading = $this->getInfoHeading()) && $this->htmlHeading) {
                $content.= sprintf($this->htmlHeading, $heading);
            }
            $content.= $ul;
            $output .= sprintf($this->htmlWrapper, $this->infoClass, $content);
        }
        
        if (count($success) > 0) {
            $liItems = array();
            foreach ($success as $message) {
                $liItems[] = CHtml::tag('li', array(), $message);
            }
            $ul = CHtml::tag('ul', array(), implode("\n", $liItems));
            
            $content = '';
            if ($this->htmlCloseButton) {
                $content.= $this->htmlCloseButton;
            }
            if (($heading = $this->getSuccessHeading()) && $this->htmlHeading) {
                $content.= sprintf($this->htmlHeading, $heading);
            }
            $content.= $ul;
            $output .= sprintf($this->htmlWrapper, $this->successClass, $content);
        }
        
        return $output;
    }
    
    /**
     * NotifyManager::add()
     * 
     * @param mixed $message
     * @param mixed $type
     * @return mixed
     */
    public function add($message, $type = self::WARNING)
    {
        $map = array(
            self::ERROR     => 'addError',
            self::WARNING   => 'addWarning',
            self::INFO      => 'addInfo',
            self::SUCCESS   => 'addSuccess',
        );
        
        if (!in_array($type, array_keys($map))) {
            $type = self::WARNING;
        }
        
        return call_user_func(array($this, $map[$type]), $message);
    }
    
    /**
     * NotifyManager::addError()
     * 
     * @param mixed $message
     * @return NotifyManager
     */
    public function addError($message)
    {
        if (!is_array($message)) {
            $message = array($message);
        }
        
        $flash = Yii::app()->user->getFlash('__notify_error', array(), false);
        $flash = array_merge($flash, $message);
        Yii::app()->user->setFlash('__notify_error', $flash);
        
        return $this;
    }
    
    /**
     * NotifyManager::addWarning()
     * 
     * @param mixed $message
     * @return NotifyManager
     */
    public function addWarning($message)
    {
        if (!is_array($message)) {
            $message = array($message);
        }
        
        $flash = Yii::app()->user->getFlash('__notify_warning', array(), false);
        $flash = array_merge($flash, $message);
        Yii::app()->user->setFlash('__notify_warning', $flash);
        
        return $this;
    }
    
    /**
     * NotifyManager::addInfo()
     * 
     * @param mixed $message
     * @return NotifyManager
     */
    public function addInfo($message)
    {
        if (!is_array($message)) {
            $message = array($message);
        }
        
        $flash = Yii::app()->user->getFlash('__notify_info', array(), false);
        $flash = array_merge($flash, $message);
        Yii::app()->user->setFlash('__notify_info', $flash);
        
        return $this;
    }
    
    /**
     * NotifyManager::addSuccess()
     * 
     * @param mixed $message
     * @return NotifyManager
     */
    public function addSuccess($message)
    {
        if (!is_array($message)) {
            $message = array($message);
        }
        
        $flash = Yii::app()->user->getFlash('__notify_success', array(), false);
        $flash = array_merge($flash, $message);
        Yii::app()->user->setFlash('__notify_success', $flash);
        
        return $this;
    }
    
    /**
     * NotifyManager::clearError()
     * 
     * @return NotifyManager
     */
    public function clearError()
    {
        Yii::app()->user->setFlash('__notify_error', array());
        return $this;
    }
    
    /**
     * NotifyManager::clearWarning()
     * 
     * @return NotifyManager
     */
    public function clearWarning()
    {
        Yii::app()->user->setFlash('__notify_warning', array());
        return $this;
    }
    
    /**
     * NotifyManager::clearInfo()
     * 
     * @return NotifyManager
     */
    public function clearInfo()
    {
        Yii::app()->user->setFlash('__notify_info', array());
        return $this;
    }
    
    /**
     * NotifyManager::clearSuccess()
     * 
     * @return NotifyManager
     */
    public function clearSuccess()
    {
        Yii::app()->user->setFlash('__notify_success', array());
        return $this;
    }
    
    /**
     * NotifyManager::clearAll()
     * 
     * @return NotifyManager
     */
    public function clearAll()
    {
        return $this->clearError()->clearWarning()->clearInfo()->clearSuccess();
    }
    
    /**
     * NotifyManager::setErrorHeading()
     * 
     * @param mixed $text
     * @return NotifyManager
     */
    public function setErrorHeading($text)
    {
        Yii::app()->user->setFlash('__notify_error_heading', $text);
        return $this;
    }
    
    /**
     * NotifyManager::getErrorHeading()
     * 
     * @return string
     */
    public function getErrorHeading()
    {
        return Yii::app()->user->getFlash('__notify_error_heading');
    }
    
    /**
     * NotifyManager::setWarningHeading()
     * 
     * @param mixed $text
     * @return NotifyManager
     */
    public function setWarningHeading($text)
    {
        Yii::app()->user->setFlash('__notify_warning_heading', $text);
        return $this;
    }
    
    /**
     * NotifyManager::getWarningHeading()
     * 
     * @return string
     */
    public function getWarningHeading()
    {
        return Yii::app()->user->getFlash('__notify_warning_heading');
    }
    
    /**
     * NotifyManager::setInfoHeading()
     * 
     * @param mixed $text
     * @return NotifyManager
     */
    public function setInfoHeading($text)
    {
        Yii::app()->user->setFlash('__notify_info_heading', $text);
        return $this;
    }
    
    /**
     * NotifyManager::getInfoHeading()
     * 
     * @return string
     */
    public function getInfoHeading()
    {
        return Yii::app()->user->getFlash('__notify_info_heading');
    }
    
    /**
     * NotifyManager::setSuccessHeading()
     * 
     * @param mixed $text
     * @return NotifyManager
     */
    public function setSuccessHeading($text)
    {
        Yii::app()->user->setFlash('__notify_success_heading', $text);
        return $this;
    }
    
    /**
     * NotifyManager::getSuccessHeading()
     * 
     * @return string
     */
    public function getSuccessHeading()
    {
        return Yii::app()->user->getFlash('__notify_success_heading');
    }
    
    /**
     * NotifyManager::getHasSuccess()
     * 
     * @return bool
     */
    public function getHasSuccess()
    {
        $messages = Yii::app()->user->getFlash('__notify_success', array(), false);
        return !empty($messages);
    }
    
    /**
     * NotifyManager::getHasInfo()
     * 
     * @return bool
     */
    public function getHasInfo()
    {
        $messages = Yii::app()->user->getFlash('__notify_info', array(), false);
        return !empty($messages);
    }
    
    /**
     * NotifyManager::getHasWarning()
     * 
     * @return bool
     */
    public function getHasWarning()
    {
        $messages = Yii::app()->user->getFlash('__notify_warning', array(), false);
        return !empty($messages);
    }
    
    /**
     * NotifyManager::getHasError()
     * 
     * @return bool
     */
    public function getHasError()
    {
        $messages = Yii::app()->user->getFlash('__notify_error', array(), false);
        return !empty($messages);
    }
    
    /**
     * NotifyManager::getIsEmpty()
     * 
     * @return bool
     */
    public function getIsEmpty()
    {
        return !$this->getHasSuccess() && !$this->getHasInfo() && !$this->getHasWarning() && !$this->getHasError();
    }
}