<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * SendTransactionalEmailsCommand
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.5
 */
 
class SendTransactionalEmailsCommand extends CConsoleCommand 
{
    protected $_lockName;
    
    public function actionIndex() 
    {
        $mutex  = Yii::app()->mutex;
        if (!$mutex->acquire($this->getLockName())) {
            return 1;
        }
        
        $this->process();
        
        $mutex->release($this->getLockName());
        return 0;
    }
    
    protected function process()
    {
        $offset = (int)Yii::app()->options->get('system.cron.transactional_emails.offset', 0);
        $limit  = 100;
        $emails = TransactionalEmail::model()->findAll(array(
            'condition' => '`status` = "unsent" AND `send_at` < NOW() AND `retries` < `max_retries`',
            'order'     => 'email_id ASC',
            'limit'     => $limit,
            'offset'    => $offset
        ));
        
        if (empty($emails)) {
            Yii::app()->options->set('system.cron.transactional_emails.offset', 0);
            return $this;
        }
        
        $server = DeliveryServer::pickServer(0, $email);
        if (empty($server)) {
            return 0;
        }
        Yii::app()->options->set('system.cron.transactional_emails.offset', $offset + $limit);
            
        foreach ($emails as $email) {
            
            if (!$server->canSendToDomainOf($email->to_email)) {
                continue;
            }
            
            if (EmailBlacklist::isBlacklisted($email->to_email)) {
                continue;
            }
            
            if ($server->getIsOverQuota()) {
                $currentServerId = $server->server_id;
                if (!($server = DeliveryServer::pickServer($currentServerId, $email))) {
                    continue;
                }
            }
            
            if (!empty($email->customer_id) && $email->customer->getIsOverQuota()) {
                continue;
            }
            
            $emailParams = array(
                'fromName'      => $email->from_name,
                'to'            => array($email->to_email => $email->to_name),
                'subject'       => $email->subject,
                'body'          => $email->body,
                'plainText'     => $email->plain_text,
            );
            
            if (!empty($email->from_email)) {
                $emailParams['from'] = array($email->from_email => $email->from_name);
            }
            
            if (!empty($email->reply_to_name) && !empty($email->reply_to_email)) {
                $emailParams['replyTo'] = array($email->reply_to_email => $email->reply_to_name);
            }
            
            $sent = $server->setDeliveryFor(DeliveryServer::DELIVERY_FOR_TRANSACTIONAL)->setDeliveryObject($email)->sendEmail($emailParams);
            if ($sent) {
                $email->status = TransactionalEmail::STATUS_SENT;
            } else {
                $email->retries++;
            }
            
            $email->save(false);
            
            $log = new TransactionalEmailLog();
            $log->email_id = $email->email_id;
            $log->message = $server->getMailer()->getLog();
            $log->save(false);
        }

        Yii::app()->getDb()->createCommand('UPDATE {{transactional_email}} SET `status` = "sent" WHERE `status` = "unsent" AND send_at < NOW() AND retries >= max_retries')->execute();
        Yii::app()->getDb()->createCommand('DELETE FROM {{transactional_email}} WHERE `status` = "unsent" AND send_at < NOW() AND date_added < DATE_SUB(NOW(), INTERVAL 1 MONTH)')->execute();
        
        return $this;
    }
    
    protected function getLockName()
    {
        if ($this->_lockName !== null) {
            return $this->_lockName;
        }
        $offset = (int)Yii::app()->options->get('system.cron.transactional_emails.offset', 0);
        return $this->_lockName = md5(__FILE__ . __CLASS__ . $offset);
    }

}