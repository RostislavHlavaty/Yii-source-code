<?php if ( ! defined('MW_PATH')) exit('No direct script access allowed');

/**
 * MailerSwiftMailer
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.2
 */
 
class MailerSwiftMailer extends MailerAbstract
{
    private $_transport;
    
    private $_message;
    
    private $_mailer;
    
    private $_loggerPlugin;
    
    private $_antiFloodPlugin;
    
    private $_throttlePlugin;
    
    /**
     * MailerSwiftMailer::init()
     * 
     * @return
     */
    public function init()
    {
        Yii::import('common.vendors.SwiftMailer.lib.classes.Swift', true);
        Yii::registerAutoloader(array('Swift', 'autoload'));
        Yii::import('common.vendors.SwiftMailer.lib.swift_init', true);
        
        parent::init();
    }

    /**
     * MailerSwiftMailer::send()
     * 
     * Implements the parent abstract method
     * 
     * @param mixed $params
     * @return bool
     */
    public function send($params = array())
    {
        $params = new CMap($params);
        $this->clearLogs()->setTransport($params)->setMessage($params);
        
        if (!$this->getTransport() || !$this->getMessage()) {
            return false;            
        }
        
        try {
            if ($sent = (bool)$this->getMailer()->send($this->getMessage())) {
                $this->addLog('OK');
            } else {
                if ($this->getLoggerPlugin()) {
                    $this->addLog($this->getLoggerPlugin()->dump());
                } else {
                    $this->addLog('NOT OK, UNKNOWN ERROR!');
                }    
            }
        } catch (Exception $e) {
            $sent = false;
            $this->addLog($e->getMessage());
        }
        
        return $sent;
    }  
    
    /**
     * MailerSwiftMailer::getEmailMessage()
     * 
     * Implements the parent abstract method
     * 
     * @param mixed $params
     * @return mixed
     */
    public function getEmailMessage($params = array())
    {
        return $this->reset()->setMessage(new CMap($params))->getMessage()->toString();  
    } 
    
    /**
     * MailerSwiftMailer::reset()
     * 
     * Implements the parent abstract method
     * 
     * @return MailerSwiftMailer
     */
    public function reset()
    {
        return $this->resetTransport()->resetMessage()->resetMailer()->resetPlugins()->clearLogs();
    }
    
    /**
     * MailerSwiftMailer::getName()
     * 
     * Implements the parent abstract method
     * 
     * @return string
     */
    public function getName()
    {
        return 'SwiftMailer';
    }
    
    /**
     * MailerSwiftMailer::getDescription()
     * 
     * Implements the parent abstract method
     * 
     * @return string
     */
    public function getDescription()
    {
        return Yii::t('mailer', 'A fully compliant mailer.');
    }
    
    /**
     * MailerSwiftMailer::setTransport()
     * 
     * @param mixed $params
     * @return mixed
     */
    protected function setTransport(CMap $params)
    {
        if ($this->_transport !== null) {
            return $this;
        }
        
        $this->resetTransport()->resetMailer();

        if (!($transport = $this->buildTransport($params))) {
            return $this;
        }

        $this->_transport = $transport;
        $this->_mailer    = Swift_Mailer::newInstance($transport);

        $plugins = $params->itemAt('mailerPlugins');
        $plugins['loggerPlugin'] = true;
        
        if (!$this->getLoggerPlugin() && isset($plugins['loggerPlugin']) && $plugins['loggerPlugin']) {
            if (is_object($plugins['loggerPlugin']) && $plugins['loggerPlugin'] instanceof Swift_Plugins_LoggerPlugin) {
                $this->setLoggerPlugin($plugins['loggerPlugin']);
            } else {
                $this->setLoggerPlugin(new Swift_Plugins_LoggerPlugin(new Swift_Plugins_Loggers_ArrayLogger()));
            }
        }
        
        if ($plugin = $this->getLoggerPlugin()) {
            $this->_mailer->registerPlugin($plugin);
        }

        if (!$this->getAntiFloodPlugin() && isset($plugins['antiFloodPlugin']) && (is_array($plugins['antiFloodPlugin']) || is_object($plugins['antiFloodPlugin']))) {
            $data = $plugins['antiFloodPlugin'];
            if (is_object($data) && $data instanceof Swift_Plugins_AntiFloodPlugin) {
                $this->setAntiFloodPlugin($data);
            } else {
                $sendAtOnce = isset($data['sendAtOnce']) && $data['sendAtOnce'] > 0 ? $data['sendAtOnce'] : 100;
                $pause      = isset($data['pause']) && $data['pause'] > 0 ? $data['pause'] : 30;
                $this->setAntiFloodPlugin(new Swift_Plugins_AntiFloodPlugin($sendAtOnce, $pause));   
            }
        }
        
        if ($plugin = $this->getAntiFloodPlugin()) {
            $this->_mailer->registerPlugin($plugin);
        }
        
        if (!$this->getThrottlePlugin() && isset($plugins['throttlePlugin']) && (is_array($plugins['throttlePlugin']) || is_object($plugins['throttlePlugin']))) {
            $data = $plugins['throttlePlugin'];
            if (is_object($data) && $data instanceof Swift_Plugins_ThrottlerPlugin) {
                $this->setThrottlePlugin($data);
            } else {
                $perMinute = isset($data['perMinute']) && $data['perMinute'] > 0 ? $data['perMinute'] : 60;
                $this->setThrottlePlugin(new Swift_Plugins_ThrottlerPlugin($perMinute, Swift_Plugins_ThrottlerPlugin::MESSAGES_PER_MINUTE)); 
            }
        }
        
        if ($plugin = $this->getThrottlePlugin()) {
            $this->_mailer->registerPlugin($plugin); 
        }

        return $this;
    }

    /**
     * MailerSwiftMailer::setMessage()
     * 
     * @param mixed $params
     * @return mixed
     */
    protected function setMessage(CMap $params)
    {
        $this->resetMessage();

        if (!$params->itemAt('sender') && $params->itemAt('username')) {
            $params->add('sender', $params->itemAt('username'));
        }
        
        if (!$params->itemAt('from') && $params->itemAt('sender')) {
            $params->add('from', $params->itemAt('sender'));
        }
        
        if (!$params->itemAt('sender') && $params->itemAt('from')) {
            $params->add('sender', $params->itemAt('from'));
        }

        $requiredKeys = array('to', 'from', 'sender', 'subject');
        foreach ($requiredKeys as $key) {
            if (!$params->itemAt($key)) {
                return $this;
            }
        }
        
        if (!$params->itemAt('body') && !$params->itemAt('plainText')) {
            return $this;
        }
        
        list($senderEmail, $senderName)     = $this->findEmailAndName($params->itemAt('sender'));
        list($fromEmail, $fromName)         = $this->findEmailAndName($params->itemAt('from'));
        list($toEmail, $toName)             = $this->findEmailAndName($params->itemAt('to'));
        list($replyToEmail, $replyToName)   = $this->findEmailAndName($params->itemAt('replyTo'));
        list($returnEmail, $returnName)     = $this->findEmailAndName($params->itemAt('returnPath'));

        if ($params->itemAt('fromName') && is_string($params->itemAt('fromName'))) {
            $fromName = $params->itemAt('fromName');
        }

        if ($params->itemAt('toName') && is_string($params->itemAt('toName'))) {
            $toName = $params->itemAt('toName');
        }
        
        if ($params->itemAt('replyToName') && is_string($params->itemAt('replyToName'))) {
            $replyToName = $params->itemAt('replyToName');
        }
        
        // dmarc policy...
        if (!$this->isCustomFromDomainAllowed($this->getDomainFromEmail($fromEmail))) {
            $fromEmail = $params->itemAt('username');
        }
        
        $senderName     = empty($senderName)   ? $fromName     : $senderName;
        $replyToName    = empty($replyToName)  ? $fromName     : $replyToName;
        $replyToEmail   = empty($replyToEmail) ? $fromEmail    : $replyToEmail;
        $returnEmail    = empty($returnEmail)  ? $senderEmail  : $returnEmail;
        $returnDomain   = $this->getDomainFromEmail($returnEmail, $this->getDomainFromEmail($senderEmail, $this->getDomainFromEmail($fromEmail)));

        $message  = Swift_Message::newInstance();
        $message->setCharset(Yii::app()->charset);
        $message->setEncoder(Swift_Encoding::get8BitEncoding());
        $message->setMaxLineLength(900);
        //$message->setPriority(1);

        if (!empty($returnDomain)) {
            $message->setId(md5(StringHelper::uniqid() . StringHelper::uniqid() . StringHelper::uniqid()) . '@' . $returnDomain);
        }
        
        $this->_message   = $message;
        $this->_messageId = str_replace(array('<', '>'), '', $message->getId());
        
        if ($params->itemAt('headers') && is_array($params->itemAt('headers'))) {
            foreach ($params->itemAt('headers') as $name => $value) {
                $message->getHeaders()->addTextHeader($name, $value);
            }
        }
        
        $message->setSubject($params->itemAt('subject'));
        $message->setSender($senderEmail, $senderName);
        $message->setFrom($fromEmail, $fromName);
        $message->setTo($toEmail, $toName);
        $message->setReplyTo($replyToEmail, $replyToName);
        $message->setReturnPath($returnEmail);
        
        $message->getHeaders()->addTextHeader('X-Sender', $fromEmail);
        $message->getHeaders()->addTextHeader('X-Receiver', $toEmail);
        $message->getHeaders()->addTextHeader('X-Mw-Mailer', 'SwiftMailer');
        
        $body           = $params->itemAt('body');
        $plainText      = $params->itemAt('plainText');
        $onlyPlainText  = $params->itemAt('onlyPlainText') === true;
        
        if (empty($plainText) && !empty($body)) {
            $plainText = CampaignHelper::htmlToText($body);
        }
        
        if (!empty($plainText) && empty($body)) {
            $body = $plainText;
        }
        
        $embedImages = $params->itemAt('embedImages');
        if (!$onlyPlainText && !empty($embedImages) && is_array($embedImages)) {
            $cids = array();
            foreach ($embedImages as $imageData) {
                if (!isset($imageData['path'], $imageData['cid'])) {
                    continue;
                }
                if (is_file($imageData['path'])) {
                    $cids['cid:' . $imageData['cid']] = $message->embed(Swift_Image::fromPath($imageData['path']));
                }
            }
            if (!empty($cids)) {
                $body = str_replace(array_keys($cids), array_values($cids), $body);
            }
            unset($embedImages, $cids);
        }
        
        if ($onlyPlainText) {
            $message->setBody($plainText, 'text/plain', Yii::app()->charset);
        } else {
            $message->setBody($body, 'text/html', Yii::app()->charset);
            $message->addPart($plainText, 'text/plain', Yii::app()->charset);    
        }

        $attachments = $params->itemAt('attachments');
        if (!$onlyPlainText && !empty($attachments) && is_array($attachments)) {
            $attachments = array_unique($attachments);
            foreach ($attachments as $attachment) {
                if (is_file($attachment)) {
                    $message->attach(Swift_Attachment::fromPath($attachment));
                }
            }
            unset($attachments);
        }

        return $this;
    }
    
    /**
     * MailerSwiftMailer::getTransport()
     * 
     * @return mixed
     */
    protected function getTransport()
    {
        return $this->_transport;
    }
    
    /**
     * MailerSwiftMailer::getMessage()
     * 
     * @return mixed
     */
    protected function getMessage()
    {
        return $this->_message;
    }

    /**
     * MailerSwiftMailer::getMailer()
     * 
     * @return mixed
     */
    protected function getMailer()
    {
        return $this->_mailer;
    }
    
    /**
     * MailerSwiftMailer::setLoggerPlugin()
     * 
     * @param Swift_Plugins_LoggerPlugin $loggerPlugin
     * @return MailerSwiftMailer
     */
    protected function setLoggerPlugin(Swift_Plugins_LoggerPlugin $loggerPlugin)
    {
        $this->_loggerPlugin = $loggerPlugin;
        return $this;
    }
    
    /**
     * MailerSwiftMailer::getLoggerPlugin()
     * 
     * @return mixed
     */
    protected function getLoggerPlugin()
    {
        return $this->_loggerPlugin;
    }
    
    /**
     * MailerSwiftMailer::setAntiFloodPlugin()
     * 
     * @param Swift_Plugins_AntiFloodPlugin $antiFloodPlugin
     * @return MailerSwiftMailer
     */
    protected function setAntiFloodPlugin(Swift_Plugins_AntiFloodPlugin $antiFloodPlugin)
    {
        $this->_antiFloodPlugin = $antiFloodPlugin;
        return $this;
    }
    
    /**
     * MailerSwiftMailer::getAntiFloodPlugin()
     * 
     * @return mixed
     */
    protected function getAntiFloodPlugin()
    {
        return $this->_antiFloodPlugin;
    }
    
    /**
     * MailerSwiftMailer::setThrottlePlugin()
     * 
     * @param Swift_Plugins_ThrottlerPlugin $throttlePlugin
     * @return MailerSwiftMailer
     */
    protected function setThrottlePlugin(Swift_Plugins_ThrottlerPlugin $throttlePlugin)
    {
        $this->_throttlePlugin = $throttlePlugin;
        return $this;
    }
    
    /**
     * MailerSwiftMailer::getThrottlePlugin()
     * 
     * @return mixed
     */
    protected function getThrottlePlugin()
    {
        return $this->_throttlePlugin;
    }

    /**
     * MailerSwiftMailer::resetTransport()
     * 
     * @return MailerSwiftMailer
     */
    protected function resetTransport()
    {
        $this->_transport = null;
        return $this;
    }
    
    /**
     * MailerSwiftMailer::resetMessage()
     * 
     * @return MailerSwiftMailer
     */
    protected function resetMessage()
    {
        $this->_messageId = null;
        $this->_message = null;
        return $this;
    }
    
    /**
     * MailerSwiftMailer::resetMailer()
     * 
     * @return MailerSwiftMailer
     */
    protected function resetMailer()
    {
        $this->_mailer = null;
        return $this;
    }
    
    /**
     * MailerSwiftMailer::resetPlugins()
     * 
     * @return MailerSwiftMailer
     */
    protected function resetPlugins()
    {
        $this->_loggerPlugin = null;
        $this->_antiFloodPlugin = null;
        $this->_throttlePlugin = null;
        
        return $this;
    }
    
    /**
     * MailerSwiftMailer::buildTransport()
     * 
     * @param CMap $params
     * @return mixed
     */
    protected function buildTransport(CMap $params)
    {
        if (!$params->itemAt('transport')) {
            $params->add('transport', 'smtp');
        }
        
        if ($params->itemAt('transport') == 'smtp') {
            return $this->buildSmtpTransport($params);
        }
        
        if ($params->itemAt('transport') == 'php-mail') {
            return $this->buildPhpMailTransport($params);
        }
        
        if ($params->itemAt('transport') == 'sendmail') {
            return $this->buildSendmailTransport($params);
        }
        
        return false;
    }
    
    /**
     * MailerSwiftMailer::buildSmtpTransport()
     * 
     * @param CMap $params
     * @return mixed
     */
    protected function buildSmtpTransport(CMap $params)
    {
        if (!CommonHelper::functionExists('proc_open')) {
            return false;
        }
        
        $requiredKeys = array('hostname', 'username', 'password');
        $hasRequiredKeys = true;
        
        foreach ($requiredKeys as $key) {
            if (!$params->itemAt($key)) {
                $hasRequiredKeys = false;
                break;
            }
        }
        
        if (!$hasRequiredKeys) {
            return false;
        }
        
        if (!$params->itemAt('port')) {
            $params->add('port', 25);
        }
        
        if (!$params->itemAt('timeout')) {
            $params->add('timeout', 30);
        }
        
        try {
            $transport = Swift_SmtpTransport::newInstance($params->itemAt('hostname'), (int)$params->itemAt('port'), $params->itemAt('protocol'));
        } catch (Exception $e) {
            $this->addLog($e->getMessage());
            return false;
        }
        
        $transport->setUsername($params->itemAt('username'));
        $transport->setPassword($params->itemAt('password'));
        $transport->setTimeout((int)$params->itemAt('timeout'));
        
        return $transport;
    }
    
    /**
     * MailerSwiftMailer::buildSendmailTransport()
     * 
     * @param CMap $params
     * @return mixed
     */
    protected function buildSendmailTransport(CMap $params)
    {
        if (!$params->itemAt('sendmailPath') || !CommonHelper::functionExists('proc_open')) {
            return false;
        }
        
        $command = $params->itemAt('sendmailPath');
        $command = trim(preg_replace('/\s\-.*/', '', $command));
        $command .= ' -bs';
        $transport = false;
        
        try {
            $transport = Swift_SendmailTransport::newInstance($command);
        } catch (Exception $e) {
            $this->addLog($e->getMessage());
            $transport = false;
        }
        
        return $transport;
    }  
    
    /**
     * MailerSwiftMailer::buildPhpMailTransport()
     * 
     * @param CMap $params
     * @return mixed
     */
    protected function buildPhpMailTransport(CMap $params)
    {
        if (!CommonHelper::functionExists('mail')) {
            return false;
        }
        
        $transport = false;
        
        try {
            $transport = Swift_MailTransport::newInstance();
        } catch (Exception $e) {
            $this->addLog($e->getMessage());
            $transport = false;
        }
        
        return $transport;
    }   
    
    /**
     * MailerSwiftMailer::clearLogs()
     * 
     * @return
     */
    public function clearLogs()
    {
        if ($this->getLoggerPlugin()) {
            $this->getLoggerPlugin()->clear();
        }
        return parent::clearLogs();
    }       
}