<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Campaign_reportsController
 * 
 * Handles the actions for campaign reports related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class Campaign_reportsController extends Controller
{
    
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('campaign-reports.js')));
        parent::init();
    }
    
    /**
     * Show delivery report for campaign
     */
    public function actionDelivery($campaign_uid)
    {
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $request    = Yii::app()->request;
        
        $deliveryLogs = new CampaignDeliveryLog('customer-search');
        $deliveryLogs->unsetAttributes();
        $deliveryLogs->attributes   = (array)$request->getQuery($deliveryLogs->modelName, array());
        $deliveryLogs->campaign_id  = (int)$campaign->campaign_id;
        
        $subscriber  = new ListSubscriber();
        $bulkActions = $subscriber->getBulkActionsList();
        foreach ($bulkActions as $value => $name) {
            if (!empty($value) && $value != ListSubscriber::BULK_DELETE) {
                unset($bulkActions[$value]);
            }
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('campaign_reports', 'Delivery report'), 
            'pageHeading'       => Yii::t('campaign_reports', 'Delivery report'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaign_reports', 'Delivery report'),
            ),
        ));

        $this->render('delivery', compact('campaign', 'deliveryLogs', 'bulkActions'));
    }
    
    /**
     * Show bounce report for a campaign
     */
    public function actionBounce($campaign_uid)
    {
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $request    = Yii::app()->request;
        
        $bounceLogs = new CampaignBounceLog('customer-search');
        $bounceLogs->unsetAttributes();
        $bounceLogs->attributes     = (array)$request->getQuery($bounceLogs->modelName, array());
        $bounceLogs->campaign_id    = (int)$campaign->campaign_id;
        
        $subscriber  = new ListSubscriber();
        $bulkActions = $subscriber->getBulkActionsList();
        foreach ($bulkActions as $value => $name) {
            if (!empty($value) && $value != ListSubscriber::BULK_DELETE) {
                unset($bulkActions[$value]);
            }
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('campaign_reports', 'Bounce report'), 
            'pageHeading'       => Yii::t('campaign_reports', 'Bounce report'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaign_reports', 'Bounce report'),
            ),
        ));

        $this->render('bounce', compact('campaign', 'bounceLogs', 'bulkActions'));
    }
    
    /**
     * Show campaign opens
     */
    public function actionOpen($campaign_uid)
    {
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $request    = Yii::app()->request;
        
        $model = new CampaignTrackOpen();
        $model->unsetAttributes();

        $criteria = new CDbCriteria;
        $criteria->compare('campaign_id', (int)$campaign->campaign_id);
        $criteria->order = 'id DESC';
        
        $dataProvider = new CActiveDataProvider($model->modelName, array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => (int)$model->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
        ));
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('campaign_reports', 'Opens report'), 
            'pageHeading'       => Yii::t('campaign_reports', 'Opens report'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaign_reports', 'Opens report'),
            ),
        ));

        $this->render('open', compact('campaign', 'model', 'dataProvider'));
    }
    
    /**
     * Show campaign unique opens
     */
    public function actionOpen_unique($campaign_uid)
    {
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $request    = Yii::app()->request;
        
        $model = new CampaignTrackOpen();
        $model->unsetAttributes();

        $criteria = new CDbCriteria;
        $criteria->select = 't.*, COUNT(*) AS counter';
        $criteria->compare('campaign_id', (int)$campaign->campaign_id);
        $criteria->group = 't.subscriber_id';
        $criteria->order = 'counter DESC';
        
        $dataProvider = new CActiveDataProvider($model->modelName, array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => (int)$model->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
        ));
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('campaign_reports', 'Unique opens report'), 
            'pageHeading'       => Yii::t('campaign_reports', 'Unique opens report'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaign_reports', 'Unique opens report'),
            ),
        ));

        $this->render('open-unique', compact('campaign', 'model', 'dataProvider'));
    }
    
    /**
     * Show campaign opens by subscriber
     */
    public function actionOpen_by_subscriber($campaign_uid, $subscriber_uid)
    {
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $subscriber = $this->loadSubscriberModel($campaign->list->list_id, $subscriber_uid);
        $request    = Yii::app()->request;
        
        $model = new CampaignTrackOpen();
        $model->unsetAttributes();

        $criteria = new CDbCriteria;
        $criteria->compare('campaign_id', (int)$campaign->campaign_id);
        $criteria->compare('subscriber_id', (int)$subscriber->subscriber_id);
        $criteria->order = 'id DESC';
        
        $dataProvider = new CActiveDataProvider($model->modelName, array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => (int)$model->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
        ));
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('campaign_reports', 'Opens report by subscriber'), 
            'pageHeading'       => Yii::t('campaign_reports', 'Opens report by subscriber'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaign_reports', 'Opens report by subscriber'),
            ),
        ));

        $this->render('open-by-subscriber', compact('campaign', 'subscriber', 'model', 'dataProvider'));
    }
    
    /**
     * Show clicked url from within the campaign email
     */
    public function actionClick($campaign_uid)
    {
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $request    = Yii::app()->request;
        
        if ($campaign->option->url_tracking != CampaignOption::TEXT_YES) {
            $this->redirect(array('campaigns/overview', 'campaign_uid' => $campaign->campaign_uid));
        }
        
        $model = new CampaignUrl();
        $model->unsetAttributes();

        $criteria = new CDbCriteria;
        $criteria->select = 't.*, (SELECT COUNT(*) FROM {{campaign_track_url}} WHERE url_id = t.url_id) AS counter';
        $criteria->compare('t.campaign_id', (int)$campaign->campaign_id);
        $criteria->order = 'counter DESC';
        
        $dataProvider = new CActiveDataProvider($model->modelName, array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => (int)$model->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
        ));
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('campaign_reports', 'Clicks report'), 
            'pageHeading'       => Yii::t('campaign_reports', 'Clicks report'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaign_reports', 'Clicks report'),
            ),
        ));

        $this->render('click', compact('campaign', 'model', 'dataProvider'));
    }
    
    /**
     * Show only stats about a certain url from campaign
     */
    public function actionClick_url($campaign_uid, $url_id)
    {
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $request    = Yii::app()->request;
        
        if ($campaign->option->url_tracking != CampaignOption::TEXT_YES) {
            $this->redirect(array('campaigns/overview', 'campaign_uid' => $campaign->campaign_uid));
        }
        
        $url = $this->loadUrlModel($campaign->campaign_id, $url_id);
        

        $model = new CampaignTrackUrl();
        $model->unsetAttributes();

        $criteria = new CDbCriteria;
        $criteria->select = 't.*, COUNT(*) AS counter';
        $criteria->compare('t.url_id', (int)$url->url_id);
        $criteria->with = array(
            'subscriber' => array(
                'together' => true,
                'joinType' => 'INNER JOIN',
            ),
        );
        $criteria->order = 'counter DESC';
        $criteria->group = 't.subscriber_id';
        
        $dataProvider = new CActiveDataProvider($model->modelName, array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => (int)$model->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
        ));
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('campaign_reports', 'Url clicks report'), 
            'pageHeading'       => Yii::t('campaign_reports', 'Url clicks report'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaign_reports', 'Url clicks report'),
            ),
        ));

        $this->render('click-url', compact('campaign', 'url', 'model', 'dataProvider'));
    }
    
    /**
     * Show what links a certain subscriber clicked
     */
    public function actionClick_by_subscriber($campaign_uid, $subscriber_uid)
    {
        $campaign = $this->loadCampaignModel($campaign_uid);
        
        if ($campaign->option->url_tracking != CampaignOption::TEXT_YES) {
            $this->redirect(array('campaigns/overview', 'campaign_uid' => $campaign->campaign_uid));
        }
        
        $subscriber = $this->loadSubscriberModel($campaign->list->list_id, $subscriber_uid);
        $request    = Yii::app()->request;
        
        $model = new CampaignTrackUrl();
        $model->unsetAttributes();
        
        $criteria = new CDbCriteria();
        $criteria->compare('t.subscriber_id', (int)$subscriber->subscriber_id);
        
        $criteria->with = array(
            'url' => array(
                'select'    => 'url.url_id, url.destination',
                'together'    => true,
                'joinType'    => 'INNER JOIN',
                'condition'    => 'url.campaign_id = :cid',
                'params'    => array(':cid' => $campaign->campaign_id),
            )
        );
        $criteria->order = 't.id DESC';

        $dataProvider = new CActiveDataProvider($model->modelName, array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => (int)$model->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
        ));
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('campaign_reports', 'Clicks report by subscriber'), 
            'pageHeading'       => Yii::t('campaign_reports', 'Clicks report by subscriber'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaign_reports', 'Clicks report by subscriber'),
            ),
        ));

        $this->render('click-by-subscriber', compact('campaign', 'subscriber', 'model', 'dataProvider'));
    }
    
    /**
     * Show only unique click for a certain subscriber
     */
    public function actionClick_by_subscriber_unique($campaign_uid, $subscriber_uid)
    {
        $campaign = $this->loadCampaignModel($campaign_uid);
        
        if ($campaign->option->url_tracking != CampaignOption::TEXT_YES) {
            $this->redirect(array('campaigns/overview', 'campaign_uid' => $campaign->campaign_uid));
        }
        
        $subscriber = $this->loadSubscriberModel($campaign->list->list_id, $subscriber_uid);
        $request    = Yii::app()->request;
        
        $model = new CampaignTrackUrl();
        $model->unsetAttributes();
        
        $criteria = new CDbCriteria();
        $criteria->select = 't.*, COUNT(*) AS counter';
        $criteria->compare('t.subscriber_id', (int)$subscriber->subscriber_id);
        
        $criteria->with = array(
            'url' => array(
                'select'    => 'url.url_id, url.destination',
                'together'  => true,
                'joinType'  => 'INNER JOIN',
                'condition' => 'url.campaign_id = :cid',
                'params'    => array(':cid' => $campaign->campaign_id),
            )
        );
        $criteria->group = 't.url_id';
        $criteria->order = 'counter DESC';

        $dataProvider = new CActiveDataProvider($model->modelName, array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => (int)$model->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
        ));
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('campaign_reports', 'Clicks report by subscriber'), 
            'pageHeading'       => Yii::t('campaign_reports', 'Clicks report by subscriber'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaign_reports', 'Clicks report by subscriber'),
            ),
        ));

        $this->render('click-by-subscriber-unique', compact('campaign', 'subscriber', 'model', 'dataProvider'));
    }
    
    /**
     * Show campaign unsubscribes
     */
    public function actionUnsubscribe($campaign_uid)
    {
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $request    = Yii::app()->request;
        
        $model = new CampaignTrackUnsubscribe();
        $model->unsetAttributes();

        $criteria = new CDbCriteria;
        $criteria->compare('campaign_id', (int)$campaign->campaign_id);
        $criteria->order = 'id DESC';
        
        $dataProvider = new CActiveDataProvider($model->modelName, array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => (int)$model->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
        ));
        
        $subscriber  = new ListSubscriber();
        $bulkActions = $subscriber->getBulkActionsList();
        foreach ($bulkActions as $value => $name) {
            if (!empty($value) && $value != ListSubscriber::BULK_DELETE) {
                unset($bulkActions[$value]);
            }
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('campaign_reports', 'Unsubscribes report'), 
            'pageHeading'       => Yii::t('campaign_reports', 'Unsubscribes report'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaign_reports', 'Unsubscribes report'),
            ),
        ));

        $this->render('unsubscribe', compact('campaign', 'model', 'dataProvider', 'bulkActions'));
    }
    
    /**
     * Helper method to load the campaign AR model
     */
    public function loadCampaignModel($campaign_uid)
    {
        $criteria = new CDbCriteria();
        $criteria->with = array(
            'list' => array(
                'together'  => true,
                'joinType'  => 'INNER JOIN',
                'condition' => 'list.customer_id = :cid',
                'params'    => array(':cid' => (int)Yii::app()->customer->getId()),
            )
        );
        $criteria->compare('t.campaign_uid', $campaign_uid);
        $criteria->addInCondition('t.status', array(Campaign::STATUS_SENT, Campaign::STATUS_SENDING, Campaign::STATUS_PROCESSING, Campaign::STATUS_PAUSED));
        
        $model = Campaign::model()->find($criteria);
        
        if ($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        return $model;
    }
    
    /**
     * Helper method to load the list subscriber AR model
     */
    public function loadSubscriberModel($list_id, $subscriber_uid)
    {
        $model = ListSubscriber::model()->findByAttributes(array(
            'subscriber_uid'    => $subscriber_uid,
            'list_id'           => $list_id,
        ));
        
        if ($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        return $model;
    }
    
    /**
     * Helper method to load the campaign url AR model
     */
    public function loadUrlModel($campaign_id, $url_id)
    {
        $model = CampaignUrl::model()->findByAttributes(array(
            'url_id'        => $url_id,
            'campaign_id'   => $campaign_id,
        ));
        
        if ($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        return $model;
    }

}