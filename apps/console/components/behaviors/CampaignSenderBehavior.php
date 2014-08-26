<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CampaignSenderBehavior
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class CampaignSenderBehavior extends CBehavior
{
    public $parallel_process_number  = 0;
    public $parallel_processes_count = 0;
    public $offset  = 0;
    public $limit   = 0;

    public function sendCampaign()
    {
        $campaign = $this->getOwner();
        $statuses = array(Campaign::STATUS_SENDING, Campaign::STATUS_PENDING_SENDING);
        
        if ($this->parallel_process_number > 1) {
            array_push($statuses, Campaign::STATUS_PROCESSING);
        }
        
        if (!in_array($campaign->status, $statuses)) {
            return 0;
        }
        
        $server = DeliveryServer::pickServer(0, $campaign);
        if (empty($server)) {
            return 0;
        }
        
        // this should never happen unless the list is removed while sending
        if (empty($campaign->list) || empty($campaign->list->customer)) {
            return 0;
        }
        
        $options  = Yii::app()->options;
        $list     = $campaign->list;
        $customer = $list->customer;
        
        if ($customer->getIsOverQuota()) {
            return 0;
        }
        
        if (!empty($customer->language_id)) {
            $language = Language::model()->findByPk((int)$customer->language_id);
            if (!empty($language)) {
                Yii::app()->setLanguage($language->getLanguageAndLocaleCode());
            }    
        }

        if ($this->parallel_process_number <= 1) {
            $campaign->status = Campaign::STATUS_PROCESSING;
            $campaign->save(false);
        }
        
        // find the subscribers we need to send these emails at
        $subscribers = $this->findSubscribers();
        
        if (empty($subscribers)) {
            if ($this->parallel_process_number == $this->parallel_processes_count) {
                if ($this->parallel_processes_count > 0 && $this->countSubscribers() > 0) {
                    return 0;
                }
                $this->markCampaignSent();
            }
            return 0;
        }

        try {

            $mailerPlugins = array(
                'logger' => true,
            );
            
            if ($sendAtOnce = (int)$options->get('system.cron.send_campaigns.send_at_once', 100)) {
                $mailerPlugins['antiFloodPlugin'] = array(
                    'sendAtOnce'    => $sendAtOnce,
                    'pause'         => (int)$options->get('system.cron.send_campaigns.pause', 30),
                );
            }
            
            if ($perMinute = (int)$options->get('system.cron.send_campaigns.emails_per_minute', 100)) {
                $mailerPlugins['throttlePlugin'] = array(
                    'perMinute' => $perMinute,
                );
            }
            
            $attachments = CampaignAttachment::model()->findAll(array(
                'select'    => 'file',
                'condition' => 'campaign_id = :cid',
                'params'    => array(':cid' => $campaign->campaign_id),
            ));
            
            $processedCounter = 0;
            $serverHasChanged = false;
            $changeServerAt   = (int)$options->get('system.cron.send_campaigns.change_server_at', 100);
            
            foreach ($subscribers as $subscriber) {
                
                $_campaign = Yii::app()->getDb()->createCommand()
                    ->select('status')
                    ->from($campaign->tableName())
                    ->where('campaign_id = :cid', array(':cid' => (int)$campaign->campaign_id))
                    ->queryRow();

                // if reset from customer area
                if (empty($_campaign) || $_campaign['status'] != Campaign::STATUS_PROCESSING) {
                    if (!empty($_campaign)) {
                        $_statuses = array(Campaign::STATUS_SENDING, Campaign::STATUS_PAUSED);
                        if ($campaign->status != $_campaign['status'] && in_array($campaign->status, $_statuses)) {
                            $campaign->status = $_campaign['status'];
                            $campaign->save(false);
                        }
                        $campaign->status = $_campaign['status'];
                    }
                    throw new Exception(Yii::t('campaigns', 'Campaign state has been changed by external command!'), 100);
                }
    
                // if this server is not allowed to send to this email domain, then just skip it.
                if (!$server->canSendToDomainOf($subscriber->email)) {
                    continue;
                }
                
                // if blacklisted, goodbye.
                if ($subscriber->getIsBlacklisted()) {
                    $this->logDelivery($subscriber, Yii::t('campaigns', 'This email is blacklisted. Sending is denied!'), CampaignDeliveryLog::STATUS_BLACKLISTED);
                    continue;
                }
                
                if ($server->getIsOverQuota()) {
                    $currentServerId = $server->server_id;
                    if (!($server = DeliveryServer::pickServer($currentServerId, $campaign))) {
                        throw new Exception(Yii::t('campaigns', 'Cannot find a valid server to send the campaign email, aborting until a delivery server is available!'), 99);
                    }
                }
                
                if ($customer->getIsOverQuota()) {
                    throw new Exception(Yii::t('campaigns', 'This customer reached the assigned quota!'), 98);
                }

                $emailParams = $this->prepareEmail($subscriber);
                if (empty($emailParams) || !is_array($emailParams)) {
                    $this->logDelivery($subscriber, Yii::t('campaigns', 'Unable to prepare the email content!'), CampaignDeliveryLog::STATUS_ERROR);
                    continue;
                }
                
                if ($changeServerAt > 0 && $processedCounter >= $changeServerAt && !$serverHasChanged) {
                    $currentServerId = $server->server_id;
                    if ($newServer = DeliveryServer::pickServer($currentServerId, $campaign)) {
                        $server = $newServer;
                        unset($newServer);
                    }
                    
                    $processedCounter = 0;
                    $serverHasChanged = true;
                }
                
                $listUnsubscribeHeaderValue = $options->get('system.urls.frontend_absolute_url');
                $listUnsubscribeHeaderValue .= 'lists/'.$list->list_uid.'/unsubscribe/'.$subscriber->subscriber_uid . '/' . $campaign->campaign_uid;
                $listUnsubscribeHeaderValue = '<'.$listUnsubscribeHeaderValue.'>';
                
                $emailParams['headers'] = array(
                    'X-Mw-Campaign-Uid'     => $campaign->campaign_uid,
                    'X-Mw-Subscriber-Uid'   => $subscriber->subscriber_uid, 
                    'List-Unsubscribe'      => $listUnsubscribeHeaderValue
                );
                
                $emailParams = CMap::mergeArray($emailParams, $mailerPlugins);

                if (!empty($attachments)) {
                    $emailParams['attachments'] = array();
                    foreach ($attachments as $attachment) {
                        $emailParams['attachments'][] = Yii::getPathOfAlias('root') . $attachment->file;
                    }
                }

                $processedCounter++;
                if ($processedCounter >= $changeServerAt) {
                    $serverHasChanged = false;
                }
                
                $status   = CampaignDeliveryLog::STATUS_SUCCESS;
                $sent     = $server->setDeliveryFor(DeliveryServer::DELIVERY_FOR_CAMPAIGN)->setDeliveryObject($campaign)->sendEmail($emailParams);
                $response = $server->getMailer()->getLog();
                
                $deliveryLog  = CampaignDeliveryLog::model()->findByAttributes(array(
                    'campaign_id'   => (int)$campaign->campaign_id,
                    'subscriber_id' => (int)$subscriber->subscriber_id,
                ));
                if (empty($deliveryLog)) {
                    $deliveryLog = new CampaignDeliveryLog();
                    $deliveryLog->campaign_id = $campaign->campaign_id;
                    $deliveryLog->subscriber_id = $subscriber->subscriber_id;
                }    
                if (!$sent) {
                    $status = CampaignDeliveryLog::STATUS_TEMPORARY_ERROR;
                    if(preg_match('/code\s"(\d+)"/ix', $response, $matches)) {
                        if ((int)$matches[1] >= 450) {
                            $status = CampaignDeliveryLog::STATUS_FATAL_ERROR;
                        } 
                    }
                }
                $deliveryLog->message = str_replace("\n\n", "\n", $response);
                $deliveryLog->status  = $status;
                $deliveryLog->save();
            }
            
        } catch (Exception $e) {

            if ($this->parallel_process_number == $this->parallel_processes_count) {
                if (!in_array($campaign->status, array(Campaign::STATUS_SENDING, Campaign::STATUS_PAUSED))) {
                    $campaign->status = Campaign::STATUS_SENDING;
                    $campaign->save(false);
                }
            }
            
            Yii::log($e->getMessage(), CLogger::LEVEL_ERROR);

            return (int)$e->getCode();
        }
        
        // the sending batch is over. if we don't have enough subscribers for next batch, we stop.
        $subscribers = $this->countSubscribers();
        if (empty($subscribers)) {
            if ($this->parallel_process_number == $this->parallel_processes_count) {
                $this->markCampaignSent();
            }
            return 0;
        }
        
        if ($this->parallel_process_number == $this->parallel_processes_count) {
            $campaign->status = Campaign::STATUS_SENDING;
            $campaign->save(false);
        }
        
        return 0;
    }
    
    protected function logDelivery(ListSubscriber $subscriber, $message, $status)
    {
        $campaign = $this->getOwner();
        
        $deliveryLog = new CampaignDeliveryLog();
        $deliveryLog->campaign_id = $campaign->campaign_id;
        $deliveryLog->subscriber_id = $subscriber->subscriber_id;
        $deliveryLog->message = str_replace("\n\n", "\n", $message);
        $deliveryLog->status = $status;

        return $deliveryLog->save();
    }
    
    protected function countSubscribers()
    {
        $campaign = $this->getOwner();
        if (!empty($campaign->segment_id)) {
            $count = $this->countSubscribersBySegment();
        } else {
            $count = $this->countSubscribersByList();
        }

        return $count;
    }
    
    protected function countSubscribersBySegment()
    {
        $campaign = $this->getOwner();
        $segment = $campaign->segment;
        
        // do not include people we already sent emails to.
        $criteria = new CDbCriteria();
        $criteria->compare('t.list_id', $campaign->list_id);
        $criteria->compare('t.status', ListSubscriber::STATUS_CONFIRMED);
        
        if ($campaign->isAutoresponder && !$this->addAutoresponderCriteria($campaign, $criteria)) {
            return 0;
        }

        $criteria->with['deliveryLogs'] = array(
            'select'    => false,
            'together'  => true,
            'joinType'  => 'LEFT OUTER JOIN',
            'on'        => 'deliveryLogs.campaign_id = :cid',
            'condition' => '(deliveryLogs.subscriber_id IS NULL OR deliveryLogs.`status` = :tstatus)',
            'params'    => array(':cid' => $campaign->campaign_id, ':tstatus' => CampaignDeliveryLog::STATUS_TEMPORARY_ERROR),
        );
        
        return $segment->countSubscribers($criteria);
    }
    
    protected function countSubscribersByList()
    {
        $campaign = $this->getOwner();

        $criteria = new CDbCriteria();
        $criteria->compare('t.list_id', $campaign->list_id);
        $criteria->compare('t.status', ListSubscriber::STATUS_CONFIRMED);
        
        if ($campaign->isAutoresponder && !$this->addAutoresponderCriteria($campaign, $criteria)) {
            return 0;
        }

        $criteria->with['deliveryLogs'] = array(
            'select'    => false,
            'together'  => true,
            'joinType'  => 'LEFT OUTER JOIN',
            'on'        => 'deliveryLogs.campaign_id = :cid',
            'condition' => '(deliveryLogs.subscriber_id IS NULL OR deliveryLogs.`status` = :tstatus)',
            'params'    => array(':cid' => $campaign->campaign_id, ':tstatus' => CampaignDeliveryLog::STATUS_TEMPORARY_ERROR),
        );

        return ListSubscriber::model()->count($criteria);
    }
    
    // find and sort subscribers
    protected function findSubscribers()
    {
        $campaign = $this->getOwner();
        if (!empty($campaign->segment_id)) {
            $subscribers = $this->findSubscribersBySegment();
        } else {
            $subscribers = $this->findSubscribersByList();
        }
        
        if (!empty($subscribers)) {
            $subscribers = $this->sortSubscribers($subscribers);    
        }
        
        return $subscribers;
    }
    
    protected function findSubscribersBySegment()
    {
        $campaign = $this->getOwner();        
        $segment = $campaign->segment;
        
        // do not include people we already sent emails to.
        $criteria = new CDbCriteria();
        $criteria->select = 't.subscriber_id, t.subscriber_uid, t.email';
        $criteria->compare('t.list_id', $campaign->list_id);
        $criteria->compare('t.status', ListSubscriber::STATUS_CONFIRMED);
        
        $limit = (int)$this->limit;
        if (empty($limit)) {
            $limit = (int)Yii::app()->options->get('system.cron.send_campaigns.subscribers_at_once', 300);
        }
        
        if ($campaign->isAutoresponder && !$this->addAutoresponderCriteria($campaign, $criteria)) {
            return array();
        }

        $criteria->with['deliveryLogs'] = array(
            'select'    => false,
            'together'  => true,
            'joinType'  => 'LEFT OUTER JOIN',
            'on'        => 'deliveryLogs.campaign_id = :cid',
            'condition' => '(deliveryLogs.subscriber_id IS NULL OR deliveryLogs.`status` = :tstatus)',
            'params'    => array(':cid' => $campaign->campaign_id, ':tstatus' => CampaignDeliveryLog::STATUS_TEMPORARY_ERROR),
        );

        return $segment->findSubscribers((int)$this->offset, $limit, $criteria);
    }

    protected function findSubscribersByList()
    {
        $campaign = $this->getOwner();

        $criteria = new CDbCriteria();
        $criteria->select = 't.subscriber_id, t.subscriber_uid, t.email';
        $criteria->compare('t.list_id', $campaign->list_id);
        $criteria->compare('t.status', ListSubscriber::STATUS_CONFIRMED);
        
        $limit = (int)$this->limit;
        if (empty($limit)) {
            $limit = (int)Yii::app()->options->get('system.cron.send_campaigns.subscribers_at_once', 300);
        }

        $criteria->offset = (int)$this->offset;
        $criteria->limit  = $limit;
        
        if ($campaign->isAutoresponder && !$this->addAutoresponderCriteria($campaign, $criteria)) {
            return array();
        }

        $criteria->with['deliveryLogs'] = array(
            'select'    => false,
            'together'  => true,
            'joinType'  => 'LEFT OUTER JOIN',
            'on'        => 'deliveryLogs.campaign_id = :cid',
            'condition' => '(deliveryLogs.subscriber_id IS NULL OR deliveryLogs.`status` = :tstatus)',
            'params'    => array(':cid' => $campaign->campaign_id, ':tstatus' => CampaignDeliveryLog::STATUS_TEMPORARY_ERROR),
        );

        return ListSubscriber::model()->findAll($criteria);
    }
    
    protected function addAutoresponderCriteria(Campaign $campaign, CDbCriteria $criteria)
    {
        if ($campaign->option->autoresponder_include_imported == CampaignOption::TEXT_NO) {
            $criteria->addCondition('t.source != :src');
            $criteria->params[':src'] = ListSubscriber::SOURCE_IMPORT;
        }

        $timeValue = (int)$campaign->option->autoresponder_time_value;
        $timeUnit  = strtoupper($campaign->option->autoresponder_time_unit);
            
        if ($campaign->option->autoresponder_event == CampaignOption::AUTORESPONDER_EVENT_AFTER_SUBSCRIBE) {
            $criteria->addCondition('t.date_added >= :cdate');
            $criteria->params[':cdate'] = $campaign->date_added;
            $criteria->addCondition(sprintf('DATE_ADD(t.date_added, INTERVAL %d %s) <= NOW()', $timeValue, $timeUnit));
        } elseif ($campaign->option->autoresponder_event == CampaignOption::AUTORESPONDER_EVENT_AFTER_CAMPAIGN_OPEN) {
            if (empty($campaign->option->autoresponder_open_campaign_id)) {
                return false;
            }
            $criteria->with['trackOpens'] = array(
                'select'    => false,
                'joinType'  => 'INNER JOIN',
                'together'  => true,
                'on'        => 'trackOpens.campaign_id = :tocid',
                'condition' => 'trackOpens.id = (SELECT id FROM {{campaign_track_open}} WHERE campaign_id = :tocid AND subscriber_id = t.subscriber_id ORDER BY id ASC LIMIT 1)',
                'params'    => array(':tocid' => $campaign->option->autoresponder_open_campaign_id),
            );
            $criteria->addCondition(sprintf('DATE_ADD(trackOpens.date_added, INTERVAL %d %s) <= NOW()', $timeValue, $timeUnit));
        } else {
            return false;
        }
        
        return true;
    }
    
    /**
     * Tries to: 
     * 1. Group the subscribers by domain
     * 2. Sort them so that we don't send to same domain two times in a row. 
     */
    protected function sortSubscribers($subscribers)
    {
        $subscribersCount = count($subscribers);
        $_subscribers = array();
        foreach ($subscribers as $index => $subscriber) {
            $emailParts = explode('@', $subscriber->email);
            $domainName = $emailParts[1];
            if (!isset($_subscribers[$domainName])) {
                $_subscribers[$domainName] = array();
            }
            $_subscribers[$domainName][] = $subscriber;
            unset($subscribers[$index]);
        }
        
        $subscribers = array();
        while ($subscribersCount > 0) {
            foreach ($_subscribers as $domainName => $subs) {
                foreach ($subs as $index => $sub) {
                    $subscribers[] = $sub;
                    unset($_subscribers[$domainName][$index]);
                    break;
                }
            }
            $subscribersCount--;
        }
        
        return $subscribers;
    }
    
    protected function prepareEmail($subscriber)
    {
        $campaign = $this->getOwner();
        
        // how come ?
        if (empty($campaign->template)) {
            return false;
        }
        
        $list           = $campaign->list;
        $customer       = $list->customer;
        $emailContent   = $campaign->template->content;
        $embedImages    = array();
        $emailFooter    = null;
        $onlyPlainText  = !empty($campaign->template->only_plain_text) && $campaign->template->only_plain_text === CampaignTemplate::TEXT_YES;
        $emailAddress   = $subscriber->email;
        
        if (!$onlyPlainText) {
            if (($emailFooter = $customer->getGroupOption('campaigns.email_footer')) && strlen(trim($emailFooter)) > 5) {
                $emailContent = CampaignHelper::injectEmailFooter($emailContent, $emailFooter, $campaign);
            }
            
            if (!empty($campaign->option) && !empty($campaign->option->embed_images) && $campaign->option->embed_images == CampaignOption::TEXT_YES) {
                list($emailContent, $embedImages) = CampaignHelper::embedContentImages($emailContent, $campaign);
            }
            
            if (!empty($campaign->option) && $campaign->option->xml_feed == CampaignOption::TEXT_YES) {
                $emailContent = CampaignXmlFeedParser::parseContent($emailContent, $campaign, true);
            }
            
            if (!empty($campaign->option) && $campaign->option->json_feed == CampaignOption::TEXT_YES) {
                $emailContent = CampaignJsonFeedParser::parseContent($emailContent, $campaign, true);
            }
    
            if (!empty($campaign->option) && $campaign->option->url_tracking == CampaignOption::TEXT_YES) {
                $emailContent = CampaignHelper::transformLinksForTracking($emailContent, $campaign, $subscriber, true);
            }
            
            $emailData = CampaignHelper::parseContent($emailContent, $campaign, $subscriber, true);    
            list($toName, $emailSubject, $emailContent) = $emailData;
        }

        // Plain TEXT only supports basic tags transform, no xml/json feeds nor tracking.
        $emailPlainText = null;
        if (!empty($campaign->option) && $campaign->option->plain_text_email == CampaignOption::TEXT_YES) {
            if ($campaign->template->auto_plain_text === CampaignTemplate::TEXT_YES && empty($campaign->template->plain_text)) {
                $emailPlainText = CampaignHelper::htmlToText($emailContent);
            }
            
            if (empty($emailPlainText) && !empty($campaign->template->plain_text) && !$onlyPlainText) {
                $_emailData = CampaignHelper::parseContent($campaign->template->plain_text, $campaign, $subscriber, false);
                list(, , $emailPlainText) = $_emailData;
            }
        }
        
        if ($onlyPlainText) {
            $_emailData = CampaignHelper::parseContent($campaign->template->plain_text, $campaign, $subscriber, false);
            list($toName, $emailSubject, $emailPlainText) = $_emailData;
            if (($emailFooter = $customer->getGroupOption('campaigns.email_footer')) && strlen(trim($emailFooter)) > 5) {
                $emailPlainText .= "\n\n\n";
                $emailPlainText .= strip_tags($emailFooter);
            }
            $emailPlainText = preg_replace('%<br(\s{0,}?/?)?>%i', "\n", $emailPlainText);
        }
        
        return array(
            'to'            => array($emailAddress => $toName),
            'subject'       => $emailSubject,
            'body'          => $emailContent,
            'plainText'     => $emailPlainText,
            'embedImages'   => $embedImages,
            'onlyPlainText' => $onlyPlainText,
        );
    }
    
    protected function markCampaignSent()
    {
        $campaign = $this->getOwner();
        
        if ($campaign->isAutoresponder) {
            $campaign->status = Campaign::STATUS_SENDING;
            $campaign->save(false);
            return;
        }
        
        $campaign->status = Campaign::STATUS_SENT;
        $campaign->save(false);

        if (Yii::app()->options->get('system.customer.action_logging_enabled', true)) {
            $list = $campaign->list;
            $customer = $list->customer;
            if (!($logAction = $customer->asa('logAction'))) {
                $customer->attachBehavior('logAction', array(
                    'class' => 'customer.components.behaviors.CustomerActionLogBehavior',
                ));
                $logAction = $customer->asa('logAction');        
            }
            $logAction->campaignSent($campaign);
        }

        $this->sendCampaignStats();
    }
    
    protected function sendCampaignStats()
    {
        $campaign = $this->getOwner();
        if (empty($campaign->option->email_stats) || !filter_var($campaign->option->email_stats, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        
        if (!($server = DeliveryServer::pickServer(0, $campaign))) {
            return;
        }
        
        $campaign->attachBehavior('stats', array(
            'class' => 'customer.components.behaviors.CampaignStatsProcessorBehavior',
        ));
        $viewData   = compact('campaign');
        
        // prepare and send the email.
        $emailTemplate  = Yii::app()->options->get('system.email_templates.common');
        $emailBody      = Yii::app()->command->renderFile(Yii::getPathOfAlias('console.views.campaign-stats').'.php', $viewData, true);
        $emailTemplate  = str_replace('[CONTENT]', $emailBody, $emailTemplate);

        $emailParams            = array();
        $emailParams['fromName']= $campaign->from_name;
        $emailParams['replyTo'] = array($campaign->reply_to => $campaign->from_name);
        $emailParams['to']      = array($campaign->option->email_stats => $campaign->from_name);
        $emailParams['subject'] = Yii::t('campaign_reports', 'The campaign {name} has finished sending, here are the stats', array('{name}' => $campaign->name));
        $emailParams['body']    = $emailTemplate;
        
        return $server->setDeliveryFor(DeliveryServer::DELIVERY_FOR_CAMPAIGN)->setDeliveryObject($campaign)->sendEmail($emailParams);
    }
}