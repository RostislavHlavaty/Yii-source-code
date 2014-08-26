<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ListsController
 * 
 * Handles the actions for lists related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class ListsController extends Controller
{
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('lists.js')));
        parent::init();
    }
    
    /**
     * Define the filters for various controller actions
     * Merge the filters with the ones from parent implementation
     */
    public function filters()
    {
        return CMap::mergeArray(array(
            'postOnly + copy',
        ), parent::filters());
    }
    
    /**
     * Show available lists
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;
        $list = new Lists('search');
        $list->attributes = (array)$request->getQuery($list->modelName, array());
        $list->customer_id = (int)Yii::app()->customer->getId();

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('lists', 'Your lists'),
            'pageHeading'       => Yii::t('lists', 'Lists'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                Yii::t('app', 'View all')
            )
        ));
        
        $this->render('list', compact('list'));
    }
    
    /**
     * Create a new list
     */
    public function actionCreate()
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $customer   = Yii::app()->customer->getModel();
        
        if (($maxLists = (int)$customer->getGroupOption('lists.max_lists', -1)) > -1) {
            $listsCount = Lists::model()->countByAttributes(array(
                'customer_id' => (int)$customer->customer_id,
            ));
            if ($listsCount >= $maxLists) {
                $notify->addWarning(Yii::t('lists', 'You have reached the maximum number of allowed lists.'));
                $this->redirect(array('lists/index'));
            }
        }
        
        $list = new Lists();
        $list->customer_id = $customer->customer_id;
        
        $listDefault = new ListDefault();
        $listCompany = new ListCompany();
        $listCustomerNotification = new ListCustomerNotification();

        // to create the default mail list fields.
        $list->attachBehavior('listDefaultFields', array(
            'class' => 'customer.components.db.behaviors.ListDefaultFieldsBehavior',
        ));
        
        if (!empty($customer->company)) {
            $listCompany->mergeWithCustomerCompany($customer->company);    
        }
        
        $listDefault->mergeWithCustomerInfo($customer);

        if ($request->isPostRequest && $request->getPost($list->modelName)) {
            $models = array($list, $listCompany, $listCustomerNotification, $listDefault);
            $hasErrors = false;
            foreach ($models as $model) {
                $model->attributes = (array)$request->getPost($model->modelName, array());
                if (!$model->validate()) {
                    $hasErrors = true; // don't break to collect errors for all models.
                }
            }
            if (!$hasErrors) {
                foreach ($models as $model) {
                    if (!($model instanceof Lists)) {
                        $model->list_id = $list->list_id;
                    }
                    $model->save(false);
                }
                
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->listCreated($list);    
                }
                
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            } else {
                $notify->addError(Yii::t('app', 'Your form contains errors, please correct them and try again.'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'    => $this,
                'success'       => $notify->hasSuccess,
                'list'          => $list,
            )));
            
            if ($collection->success) {
                $this->redirect(array('lists/update', 'list_uid' => $list->list_uid));
            }
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('lists', 'Create new list'),
            'pageHeading'       => Yii::t('lists', 'Create new list'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                Yii::t('app', 'Create new')
            )
        ));
        
        $this->render('form', compact('list', 'listDefault', 'listCompany', 'listCustomerNotification'));
    }
    
    /**
     * Update existing list
     */
    public function actionUpdate($list_uid)
    {
        $list       = $this->loadModel($list_uid);
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;

        $listDefault = $list->default;
        $listCompany = $list->company;
        $listCustomerNotification = $list->customerNotification;

        if ($request->isPostRequest && $request->getPost($list->modelName)) {
            $models = array($list, $listCompany, $listCustomerNotification, $listDefault);
            $hasErrors = false;
            foreach ($models as $model) {
                $model->attributes = (array)$request->getPost($model->modelName, array());
                if (!$model->validate()) {
                    $hasErrors = true; // don't break to collect errors for all models.
                }
            }
            if (!$hasErrors) {
                foreach ($models as $model) {
                    $model->save(false);
                }
                
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->listUpdated($list);    
                }
                
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            } else {
                $notify->addError(Yii::t('app', 'Your form contains errors, please correct them and try again.'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'    => $this,
                'success'       => $notify->hasSuccess,
                'list'          => $list,
            )));
            
            if ($collection->success) {
                $this->redirect(array('lists/update', 'list_uid' => $list->list_uid));
            }
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('lists', 'Update list'), 
            'pageHeading'       => Yii::t('lists', 'Update list'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                Yii::t('app', 'Update')
            )
        ));
        
        $this->render('form', compact('list', 'listDefault', 'listCompany', 'listCustomerNotification'));
    }
    
    /**
     * Copy list
     * The copy will include all the list base data.
     */
    public function actionCopy($list_uid)
    {
        $list     = $this->loadModel($list_uid);   
        $customer = $list->customer;
        $request  = Yii::app()->request;
        $notify   = Yii::app()->notify;
        $canCopy  = true;
        
        if (($maxLists = $customer->getGroupOption('lists.max_lists', -1)) > -1) {
            $listsCount = Lists::model()->countByAttributes(array(
                'customer_id' => (int)$customer->customer_id,
            ));
            if ($listsCount >= $maxLists) {
                $notify->addWarning(Yii::t('lists', 'You have reached the maximum number of allowed lists.'));
                $canCopy = false;
            }
        }
        
        if ($canCopy && $list->copy()) {
            $notify->addSuccess(Yii::t('lists', 'Your list was successfully copied!'));
        }

        if (!$request->isAjaxRequest) {
            $this->redirect($request->getPost('returnUrl', array('lists/index')));
        }
    }
    
    
    /**
     * Delete existing list
     * This can take a lot of time if the list is large and has many custom fields. 
     * Just make sure it has enough time to execute.
     */
    public function actionDelete($list_uid)
    {
        $list    = $this->loadModel($list_uid);
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        
        if ($request->isPostRequest) {
            
            // on big lists it takes a lot of time to do the deletions
            set_time_limit(0);
            ignore_user_abort(true);
            
            $list->delete();
            
            if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                $logAction->listDeleted($list);    
            }
            
            $notify->addSuccess(Yii::t('app', 'Your item has been successfully deleted!'));
            $this->redirect($request->getPost('returnUrl', array('lists/index')));
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('lists', 'Confirm list removal'),
            'pageHeading'       => Yii::t('lists', 'Confirm list removal'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                Yii::t('lists', 'Confirm list removal')
            )
        ));
        
        $campaign = new Campaign();
        $campaign->unsetAttributes();
        $campaign->attributes  = (array)$request->getQuery($campaign->modelName, array());
        $campaign->list_id     = $list->list_id;
        $campaign->customer_id = (int)Yii::app()->customer->getId();

        $this->render('delete', compact('list', 'campaign'));
    }
    
    /**
     * Display list overview
     * This is a page containing shortcuts to the most important list features.
     */
    public function actionOverview($list_uid)
    {
        $list = $this->loadModel($list_uid);
        
        $apps = Yii::app()->apps;
        $this->getData('pageScripts')->mergeWith(array(
            array('src' => $apps->getBaseUrl('assets/js/flot/jquery.flot.min.js')),
            array('src' => $apps->getBaseUrl('assets/js/flot/jquery.flot.resize.min.js')),
            array('src' => $apps->getBaseUrl('assets/js/flot/jquery.flot.categories.min.js')),
            array('src' => AssetsUrl::js('list-overview.js'))
        ));
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('lists', 'List overview'),
            'pageHeading'       => Yii::t('lists', 'List overview'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                Yii::t('lists', 'Overview')
            )
        ));

        $subscribersCount   = $list->subscribersCount;
        $segmentsCount      = $list->segmentsCount;
        $customFieldsCount  = $list->fieldsCount;
        $pagesCount         = ListPageType::model()->count();
        
        $this->render('overview', compact('list', 'subscribersCount', 'segmentsCount', 'customFieldsCount', 'pagesCount'));
    }

    /**
     * Ajax only action to get one year subscribers growth
     */
    public function actionSubscribers_growth($list_uid)
    {
        set_time_limit(0);
        
        if (!Yii::app()->request->isAjaxRequest) {
            $this->redirect(array('lists/index'));
        }
        
        $list      = $this->loadModel($list_uid);
        $cacheKey  = md5(__FILE__ . __METHOD__ . $list_uid);
        if ($items = Yii::app()->cache->get($cacheKey)) {
            return $this->renderJson(array(
                'label' => Yii::t('dashboard', '{n} months growth', 3),
                'data'  => $items,
                'color' => '#3c8dbc'
            ));
        }
        
        $criteria = new CDbCriteria();
        $criteria->select    = 'DISTINCT(DATE(t.date_added)) AS date_added';
        $criteria->condition = 't.list_id = :list_id AND DATE(t.date_added) >= DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 3 MONTH)), INTERVAL 1 DAY)';
        $criteria->group     = 'MONTH(t.date_added)';
        $criteria->order     = 't.date_added ASC';
        $criteria->limit     = 3;
        $criteria->params = array(
            ':list_id'=> $list->list_id,
        );
        $models = ListSubscriber::model()->findAll($criteria);
        
        $items = array();
        foreach ($models as $model) {
            $criteria = new CDbCriteria();
            $criteria->condition = 't.list_id = :list_id AND YEAR(t.date_added) = YEAR(:year) AND MONTH(t.date_added) = MONTH(:month)';
            $criteria->params = array(
                ':list_id'=> $list->list_id,
                ':year'   => $model->date_added,
                ':month'  => $model->date_added,
            );
            $monthName  = date('M', strtotime($model->date_added));
            $count      = ListSubscriber::model()->count($criteria);
            $items[]    = array(Yii::t('app', $monthName) . ' ' . date('Y', strtotime($model->date_added)), $count);
        }
        
        Yii::app()->cache->set($cacheKey, $items, 3600);
        
        return $this->renderJson(array(
            'label' => Yii::t('dashboard', '{n} months growth', 3),
            'data'  => $items,
            'color' => '#3c8dbc'
        ));
    }
    
    /**
     * Ajax only action to get campaigns growth
     */
    public function actionCampaigns_growth($list_uid)
    {
        set_time_limit(0);
        
        if (!Yii::app()->request->isAjaxRequest) {
            $this->redirect(array('lists/index'));
        }
        
        $list      = $this->loadModel($list_uid);
        $cacheKey  = md5(__FILE__ . __METHOD__ . $list_uid);
        if ($items = Yii::app()->cache->get($cacheKey)) {
            return $this->renderJson(array(
                'label' => Yii::t('app', '{n} months growth', 3),
                'data'  => $items,
                'color' => '#3c8dbc'
            ));
        }
        
        $criteria = new CDbCriteria();
        $criteria->select    = 'DISTINCT(DATE(t.date_added)) AS date_added';
        $criteria->condition = 't.list_id = :list_id AND DATE(t.date_added) >= DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 3 MONTH)), INTERVAL 1 DAY)';
        $criteria->group     = 'MONTH(t.date_added)';
        $criteria->order     = 't.date_added ASC';
        $criteria->limit     = 3;
        $criteria->params = array(
            ':list_id'=> $list->list_id,
        );
        $models = Campaign::model()->findAll($criteria);
        
        $items = array();
        foreach ($models as $model) {
            $criteria = new CDbCriteria();
            $criteria->condition = 't.list_id = :list_id AND YEAR(t.date_added) = YEAR(:year) AND MONTH(t.date_added) = MONTH(:month)';
            $criteria->params = array(
                ':list_id'=> $list->list_id,
                ':year'   => $model->date_added,
                ':month'  => $model->date_added,
            );
            $monthName  = date('M', strtotime($model->date_added));
            $count      = Campaign::model()->count($criteria);
            $items[]    = array(Yii::t('app', $monthName) . ' ' . date('Y', strtotime($model->date_added)), $count);
        }
        
        Yii::app()->cache->set($cacheKey, $items, 3600);
        
        return $this->renderJson(array(
            'label' => Yii::t('app', '{n} months growth', 3),
            'data'  => $items,
            'color' => '#3c8dbc'
        ));
    }
    
    /**
     * Ajax only action to get delivery/bounce growth
     */
    public function actionDelivery_bounce_growth($list_uid)
    {
        set_time_limit(0);
        
        if (!Yii::app()->request->isAjaxRequest) {
            $this->redirect(array('lists/index'));
        }
        
        $list  = $this->loadModel($list_uid);
        $cacheKey  = md5(__FILE__ . __METHOD__ . $list_uid);
        if ($lines = Yii::app()->cache->get($cacheKey)) {
            return $this->renderJson($lines);
        }
        
        $lines = array();
        
        // Delivery
        $criteria = new CDbCriteria();
        $criteria->select    = 'DISTINCT(DATE(t.date_added)) AS date_added';
        $criteria->condition = 'DATE(t.date_added) >= DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 3 MONTH)), INTERVAL 1 DAY)';
        $criteria->group     = 'MONTH(t.date_added)';
        $criteria->order     = 't.date_added ASC';
        $criteria->limit     = 3;
        $criteria->with = array(
            'subscriber' => array(
                'select'    => false,
                'together'  => true,
                'joinType'  => 'INNER JOIN',
                'with'      => array(
                    'list'  => array(
                        'select'    => false,
                        'together'  => true,
                        'joinType'  => 'INNER JOIN',
                        'condition' => 'list.list_id = :list_id',
                        'params'    => array(':list_id' => (int)$list->list_id),    
                    ),
                )
            ),
        );
        $models = CampaignDeliveryLog::model()->findAll($criteria);
        
        $items = array();
        foreach ($models as $model) {
            $criteria = new CDbCriteria();
            $criteria->condition = 'YEAR(t.date_added) = YEAR(:year) AND MONTH(t.date_added) = MONTH(:month)';
            $criteria->params = array(
                ':year'   => $model->date_added,
                ':month'  => $model->date_added,
            );
            $criteria->with = array(
                'subscriber' => array(
                    'select'    => false,
                    'together'  => true,
                    'joinType'  => 'INNER JOIN',
                    'with'      => array(
                        'list'  => array(
                            'select'    => false,
                            'together'  => true,
                            'joinType'  => 'INNER JOIN',
                            'condition' => 'list.list_id = :list_id',
                            'params'    => array(':list_id' => (int)$list->list_id),    
                        ),
                    )
                ),
            );
            $monthName  = date('M', strtotime($model->date_added));
            $count      = CampaignDeliveryLog::model()->count($criteria);
            $items[]    = array(Yii::t('app', $monthName) . ' ' . date('Y', strtotime($model->date_added)), $count);
        }
        
        $lines[] = array(
            'label' => Yii::t('app', 'Delivery, {n} months growth', 3),
            'data'  => $items,
            'color' => '#3c8dbc'
        );
        
        // Bounces
        $criteria = new CDbCriteria();
        $criteria->select    = 'DISTINCT(DATE(t.date_added)) AS date_added';
        $criteria->condition = 'DATE(t.date_added) >= DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 3 MONTH)), INTERVAL 1 DAY)';
        $criteria->group     = 'MONTH(t.date_added)';
        $criteria->order     = 't.date_added ASC';
        $criteria->limit     = 3;
        $criteria->with = array(
            'subscriber' => array(
                'select'    => false,
                'together'  => true,
                'joinType'  => 'INNER JOIN',
                'with'      => array(
                    'list'  => array(
                        'select'    => false,
                        'together'  => true,
                        'joinType'  => 'INNER JOIN',
                        'condition' => 'list.list_id = :list_id',
                        'params'    => array(':list_id' => (int)$list->list_id),        
                    ),
                )
            ),
        );
        $models = CampaignBounceLog::model()->findAll($criteria);

        $items = array();
        foreach ($models as $model) {
            $criteria = new CDbCriteria();
            $criteria->condition = 'YEAR(t.date_added) = YEAR(:year) AND MONTH(t.date_added) = MONTH(:month)';
            $criteria->params = array(
                ':year'   => $model->date_added,
                ':month'  => $model->date_added,
            );
            $criteria->with = array(
                'subscriber' => array(
                    'select'    => false,
                    'together'  => true,
                    'joinType'  => 'INNER JOIN',
                    'with'      => array(
                        'list'  => array(
                            'select'    => false,
                            'together'  => true,
                            'joinType'  => 'INNER JOIN',
                            'condition' => 'list.list_id = :list_id',
                            'params'    => array(':list_id' => (int)$list->list_id),    
                        ),
                    )
                ),
            );
            $monthName  = date('M', strtotime($model->date_added));
            $count      = CampaignBounceLog::model()->count($criteria);
            $items[]    = array(Yii::t('app', $monthName) . ' ' . date('Y', strtotime($model->date_added)), $count);
        }
        
        $lines[] = array(
            'label' => Yii::t('app', 'Bounce, {n} months growth', 3),
            'data'  => $items,
            'color' => '#ff0000'
        );
        
        Yii::app()->cache->set($cacheKey, $lines, 3600);
        
        return $this->renderJson($lines);
    }
    
    /**
     * Ajax only action to get unsubscribes growth
     */
    public function actionUnsubscribe_growth($list_uid)
    {
        set_time_limit(0);
        
        if (!Yii::app()->request->isAjaxRequest) {
            $this->redirect(array('dashboard/index'));
        }
        
        $list      = $this->loadModel($list_uid);
        $cacheKey  = md5(__FILE__ . __METHOD__ . $list_uid);
        if ($items = Yii::app()->cache->get($cacheKey)) {
            return $this->renderJson(array(
                'label' => Yii::t('app', '{n} months growth', 3),
                'data'  => $items,
                'color' => '#3c8dbc'
            ));
        }
        
        $criteria = new CDbCriteria();
        $criteria->select    = 'DISTINCT(DATE(t.date_added)) AS date_added';
        $criteria->condition = 'DATE(t.date_added) >= DATE_ADD(LAST_DAY(DATE_SUB(NOW(), INTERVAL 3 MONTH)), INTERVAL 1 DAY)';
        $criteria->group     = 'MONTH(t.date_added)';
        $criteria->order     = 't.date_added ASC';
        $criteria->limit     = 3;
        $criteria->with = array(
            'subscriber' => array(
                'select'    => false,
                'together'  => true,
                'joinType'  => 'INNER JOIN',
                'with'      => array(
                    'list'  => array(
                        'select'    => false,
                        'together'  => true,
                        'joinType'  => 'INNER JOIN',
                        'condition' => 'list.list_id = :list_id',
                        'params'    => array(':list_id' => (int)$list->list_id),     
                    ),
                )
            ),
        );
        $models = CampaignTrackUnsubscribe::model()->findAll($criteria);
        
        $items = array();
        foreach ($models as $model) {
            $criteria = new CDbCriteria();
            $criteria->condition = 'YEAR(t.date_added) = YEAR(:year) AND MONTH(t.date_added) = MONTH(:month)';
            $criteria->params = array(
                ':year'   => $model->date_added,
                ':month'  => $model->date_added,
            );
            $criteria->with = array(
                'subscriber' => array(
                    'select'    => false,
                    'together'  => true,
                    'joinType'  => 'INNER JOIN',
                    'with'      => array(
                        'list'  => array(
                            'select'    => false,
                            'together'  => true,
                            'joinType'  => 'INNER JOIN',
                            'condition' => 'list.list_id = :list_id',
                            'params'    => array(':list_id' => (int)$list->list_id),     
                        ),
                    )
                ),
            );
            $monthName  = date('M', strtotime($model->date_added));
            $count      = CampaignTrackUnsubscribe::model()->count($criteria);
            $items[]    = array(Yii::t('app', $monthName) . ' ' . date('Y', strtotime($model->date_added)), $count);
        }
        
        Yii::app()->cache->set($cacheKey, $items, 3600);
        
        return $this->renderJson(array(
            'label' => Yii::t('app', '{n} months growth', 3),
            'data'  => $items,
            'color' => '#3c8dbc'
        ));
    }

    /**
     * Helper method to load the list AR model
     */
    public function loadModel($list_uid)
    {
        $model = Lists::model()->findByAttributes(array(
            'list_uid'      => $list_uid,
            'customer_id'   => (int)Yii::app()->customer->getId(),
        ));
        
        if ($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        return $model;
    }
}