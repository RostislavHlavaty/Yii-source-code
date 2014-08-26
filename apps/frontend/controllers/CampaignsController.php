<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CampaignsController
 * 
 * Handles the actions for campaigns related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class CampaignsController extends Controller
{
    public $layout = 'thin';
    
    /**
     * Will show the web version of a campaign email
     */
    public function actionWeb_version($campaign_uid, $subscriber_uid = null)
    {
        $campaign = Campaign::model()->findByUid($campaign_uid);
        if (empty($campaign)) {
            $this->redirect(array('site/index'));
        }
        
        $subscriber = null;
        if (!empty($subscriber_uid)) {
            $subscriber = ListSubscriber::model()->findByAttributes(array(
                'subscriber_uid'    => $subscriber_uid,
                'status'            => ListSubscriber::STATUS_CONFIRMED,
            ));
        }
        
        $list           = $campaign->list;
        $customer       = $list->customer;
        $template       = $campaign->template;
        $emailContent   = $template->content;
        $emailFooter    = null;
        
        if (($emailFooter = $customer->getGroupOption('campaigns.email_footer')) && strlen(trim($emailFooter)) > 5) {
            $emailContent = CampaignHelper::injectEmailFooter($emailContent, $emailFooter, $campaign);
        }
        
        if (!empty($campaign->option) && $campaign->option->xml_feed == CampaignOption::TEXT_YES) {
            $emailContent = CampaignXmlFeedParser::parseContent($emailContent, $campaign, true);
        }
        
        if (!empty($campaign->option) && $campaign->option->json_feed == CampaignOption::TEXT_YES) {
            $emailContent = CampaignJsonFeedParser::parseContent($emailContent, $campaign, true);
        }

        if ($subscriber) {
            if (!empty($campaign->option) && $campaign->option->url_tracking == CampaignOption::TEXT_YES) {
                $emailContent = CampaignHelper::transformLinksForTracking($emailContent, $campaign, $subscriber, false);
            }
        } else {
            $subscriber = new ListSubscriber();
        }
        
        $emailData = CampaignHelper::parseContent($emailContent, $campaign, $subscriber, true);
        list(,,$emailContent) = $emailData;

        echo $emailContent;
    }
    
    /**
     * Will track and register the email openings
     * 
     * GMail will store the email images, therefore there might be cases when successive opens by same subscriber
     * will not be tracked.
     * In order to trick this, it seems that the content length must be set to 0 as pointed out here:
     * http://www.emailmarketingtipps.de/2013/12/07/gmails-image-caching-affects-email-marketing-heal-opens-tracking/
     * 
     * Note: When mod gzip enabled on server, the content length will be at least 20 bytes as explained in this bug:
     * https://issues.apache.org/bugzilla/show_bug.cgi?id=51350
     * In order to alleviate this, seems that we need to use a fake content type, like application/json
     */
    public function actionTrack_opening($campaign_uid, $subscriber_uid)
    {
        header("Content-Type: application/json");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: private");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header('P3P: CP="OTI DSP COR CUR IVD CONi OTPi OUR IND UNI STA PRE"');
        header("Pragma: no-cache");
        header("Content-Length: 0");
        
        $campaign = Campaign::model()->findByUid($campaign_uid);
        if (empty($campaign)) {
            Yii::app()->end();
        }
        
        $subscriber = ListSubscriber::model()->findByAttributes(array(
            'subscriber_uid'    => $subscriber_uid,
            'status'            => ListSubscriber::STATUS_CONFIRMED,
        ));
        
        if (empty($subscriber)) {
            Yii::app()->end();
        }
        
        Yii::app()->hooks->addAction('frontend_campaigns_after_track_opening', array($this, '_openActionChangeSubscriberListField'), 99);
        Yii::app()->hooks->addAction('frontend_campaigns_after_track_opening', array($this, '_openActionAgainstSubscriber'), 100);
        
        $track = new CampaignTrackOpen();
        $track->campaign_id     = $campaign->campaign_id;
        $track->subscriber_id   = $subscriber->subscriber_id;
        $track->ip_address      = Yii::app()->request->getUserHostAddress();
        $track->user_agent      = substr(Yii::app()->request->getUserAgent(), 0, 255);
        
        if ($track->save()) {
            // raise the action, hook added in 1.2
            $this->setData('ipLocationSaved', false);
            Yii::app()->hooks->doAction('frontend_campaigns_after_track_opening', $this, $track, $campaign, $subscriber);    
        }
        
        Yii::app()->end();
    }
    
    /**
     * Will track the clicks the subscribers made in the campaign email
     */
    public function actionTrack_url($campaign_uid, $subscriber_uid, $hash)
    {
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        $campaign = Campaign::model()->findByUid($campaign_uid);
        if (empty($campaign)) {
            $this->redirect(array('site/index'));
        }
        
        $subscriber = ListSubscriber::model()->findByAttributes(array(
            'subscriber_uid'    => $subscriber_uid,
            'status'            => ListSubscriber::STATUS_CONFIRMED,
        ));
        
        if (empty($subscriber)) {
            $this->redirect(array('site/index'));
        }
        
        $url = CampaignUrl::model()->findByAttributes(array(
            'campaign_id'   => $campaign->campaign_id,
            'hash'          => $hash,
        ));
        
        if (empty($url)) {
            $this->redirect(array('site/index'));
        }
        
        Yii::app()->hooks->addAction('frontend_campaigns_after_track_url_before_redirect', array($this, '_urlActionChangeSubscriberListField'), 99);
        Yii::app()->hooks->addAction('frontend_campaigns_after_track_url_before_redirect', array($this, '_urlActionAgainstSubscriber'), 100);
        
        $track = new CampaignTrackUrl();
        $track->url_id          = $url->url_id;
        $track->subscriber_id   = $subscriber->subscriber_id;
        $track->ip_address      = Yii::app()->request->getUserHostAddress();
        $track->user_agent      = substr(Yii::app()->request->getUserAgent(), 0, 255);
        
        if ($track->save()) {
            // hook added in 1.2
            $this->setData('ipLocationSaved', false);
            Yii::app()->hooks->doAction('frontend_campaigns_after_track_url', $this, $track, $campaign, $subscriber);    
        }

        $destination = str_replace('&amp;', '&', $url->destination);
        if (preg_match('/\[(.*)?\]/', $destination)) {
            list(,,$destination) = CampaignHelper::parseContent($destination, $campaign, $subscriber, false);
        }
        
        Yii::app()->hooks->doAction('frontend_campaigns_after_track_url_before_redirect', $this, $campaign, $subscriber, $url, $destination); 
        
        $this->redirect($destination, true, 301);
    }
    
    public function _openActionChangeSubscriberListField(Controller $controller, CampaignTrackOpen $track, Campaign $campaign, ListSubscriber $subscriber)
    {
        $models = CampaignOpenActionListField::model()->findAllByAttributes(array(
            'campaign_id' => $campaign->campaign_id,
        ));
        
        if (empty($models)) {
            return;
        }
        
        foreach ($models as $model) {
            $valueModel = ListFieldValue::model()->findByAttributes(array(
                'field_id'      => $model->field_id,
                'subscriber_id' => $subscriber->subscriber_id,
            ));
            if (empty($valueModel)) {
                $valueModel = new ListFieldValue();
                $valueModel->field_id       = $model->field_id;
                $valueModel->subscriber_id  = $subscriber->subscriber_id;
            }
            $valueModel->value = $model->field_value;
            $valueModel->save();
        }
    }
    
    public function _openActionAgainstSubscriber(Controller $controller, CampaignTrackOpen $track, Campaign $campaign, ListSubscriber $subscriber)
    {
        $models = CampaignOpenActionSubscriber::model()->findAllByAttributes(array(
            'campaign_id' => $campaign->campaign_id,
        ));
        
        if (empty($models)) {
            return;
        }
        
        $move = false;
        foreach ($models as $model) {
            $status = $subscriber->copyToList($model->list_id);
            if ($status && $model->action == CampaignOpenActionSubscriber::ACTION_MOVE) {
                $move = true;
            }
        }
        
        if ($move) {
            $subscriber->delete();
        }
    }
    
    public function _urlActionChangeSubscriberListField(Controller $controller, Campaign $campaign, ListSubscriber $subscriber, CampaignUrl $url, $destination)
    {
        $models = CampaignTemplateUrlActionListField::model()->findAllByAttributes(array(
            'campaign_id' => $campaign->campaign_id,
            'url'         => $destination,
        ));
        
        if (empty($models)) {
            return;
        }
        
        foreach ($models as $model) {
            $valueModel = ListFieldValue::model()->findByAttributes(array(
                'field_id'      => $model->field_id,
                'subscriber_id' => $subscriber->subscriber_id,
            ));
            if (empty($valueModel)) {
                $valueModel = new ListFieldValue();
                $valueModel->field_id       = $model->field_id;
                $valueModel->subscriber_id  = $subscriber->subscriber_id;
            }
            $valueModel->value = $model->field_value;
            $valueModel->save();
        }
    }
    
    public function _urlActionAgainstSubscriber(Controller $controller, Campaign $campaign, ListSubscriber $subscriber, CampaignUrl $url, $destination)
    {
        $models = CampaignTemplateUrlActionSubscriber::model()->findAllByAttributes(array(
            'campaign_id' => $campaign->campaign_id,
            'url'         => $destination,
        ));
        
        if (empty($models)) {
            return;
        }

        $move = false;
        foreach ($models as $model) {
            $status = $subscriber->copyToList($model->list_id);
            if ($status && $model->action == CampaignOpenActionSubscriber::ACTION_MOVE) {
                $move = true;
            }
        }
        
        if ($move) {
            $subscriber->delete();
        }
    }
}