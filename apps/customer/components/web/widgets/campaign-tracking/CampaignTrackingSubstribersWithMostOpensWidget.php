<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CampaignTrackingSubstribersWithMostOpensWidget
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class CampaignTrackingSubstribersWithMostOpensWidget extends CWidget 
{
    public $campaign;

    public function run() 
    {
        $campaign = $this->campaign;
        
        if ($campaign->status == Campaign::STATUS_DRAFT) {
            return;
        }
        
        $criteria = new CDbCriteria();
        $criteria->select = 't.subscriber_id, COUNT(*) as counter';
        $criteria->compare('t.campaign_id', $campaign->campaign_id);
        $criteria->group = 't.subscriber_id';
        $criteria->order = 'counter DESC';
        $criteria->limit = 10;
        
        $criteria->with = array('subscriber' => array(
            'together'  => true,
            'joinType'  => 'INNER JOIN',
            'select'    => 'subscriber.email',
        ));
        
        $models = CampaignTrackOpen::model()->findAll($criteria);
        
        $this->render('subscribers-with-most-opens', compact('campaign', 'models'));
    }
}