<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CampaignTrackingLatestClickedLinksWidget
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class CampaignTrackingLatestClickedLinksWidget extends CWidget 
{
    public $campaign;

    public function run() 
    {
        $campaign = $this->campaign;
        
        if ($campaign->status == Campaign::STATUS_DRAFT) {
            return;
        }
        
        if ($campaign->option->url_tracking != CampaignOption::TEXT_YES) {
            return;
        }
        
        $criteria = new CDbCriteria();
        $criteria->select = 't.url_id, t.subscriber_id, t.date_added';
        $criteria->with = array(
            'url' => array(
                'select'    => 'url.url_id, url.destination',
                'together'  => true,
                'joinType'  => 'INNER JOIN',
                'condition' => 'url.campaign_id = :cid',
                'params'    => array(':cid' => $campaign->campaign_id),
            ),
            'subscriber' => array(
                'select'    => 'subscriber.subscriber_uid, subscriber.email',
                'together'  => true,
                'joinType'  => 'INNER JOIN',
            ),
        );
        $criteria->order = 't.id DESC';
        $criteria->limit = 10;
        
        $models = CampaignTrackUrl::model()->findAll($criteria);
        
        $this->render('latest-clicked-links', compact('campaign', 'models'));
    }
}