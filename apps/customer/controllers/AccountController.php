<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * AccountController
 * 
 * Handles the actions for account related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class AccountController extends Controller
{
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('account.js')));
        parent::init();    
    }
    
    /**
     * Default action, allowing to update the account.
     */
    public function actionIndex()
    {
        $customer = Yii::app()->customer->getModel();
        $customer->confirm_email = $customer->email;
        $customer->setScenario('update-profile');

        if (Yii::app()->request->isPostRequest && $attributes = Yii::app()->request->getPost($customer->modelName)) {
            $customer->attributes = $attributes;
            if ($customer->save()) {
                Yii::app()->notify->addSuccess(Yii::t('customers', 'Profile info successfully updated!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => Yii::app()->notify->hasSuccess,
                'customer'  => $customer,
            )));
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('customers', 'Account info'),
            'pageHeading'       => Yii::t('customers', 'Account info'),
            'pageBreadcrumbs'   => array(
                Yii::t('customers', 'Account') => $this->createUrl('account/index'),
                Yii::t('app', 'Update')
            )
        ));
        
        $this->render('index', compact('customer'));
    }
    
    /**
     * Update the account company info
     */
    public function actionCompany()
    {
        $customer = Yii::app()->customer->getModel();
        
        if (empty($customer->company)) {
            $customer->company = new CustomerCompany();
        }
        
        $company = $customer->company;
        $request = Yii::app()->request;
        
        if ($request->isPostRequest && $attributes = $request->getPost($company->modelName)) {
            $company->attributes = $attributes;
            $company->customer_id = Yii::app()->customer->getId();
            
            if ($company->save()) {
                Yii::app()->notify->addSuccess(Yii::t('customers', 'Company info successfully updated!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => Yii::app()->notify->hasSuccess,
                'customer'  => $customer,
                'company'   => $company,
            )));
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('customers', 'Company'),
            'pageHeading'       => Yii::t('customers', 'Company'),
            'pageBreadcrumbs'   => array(
                 Yii::t('customers', 'Account') => $this->createUrl('account/index'),
                 Yii::t('customers', 'Company') => $this->createUrl('account/company'),
                 Yii::t('app', 'Update')
            )
        ));
        
        $this->render('company', compact('company'));
    }
    
    /**
     * Display stats about the account, limits, etc
     */
    public function actionUsage()
    {
        $request = Yii::app()->request;
        if (!$request->isAjaxRequest) {
            $this->redirect(array('account/index'));
        }
        
        $formatter = Yii::app()->format;
        $customer  = Yii::app()->customer->getModel();
        $data = array();
        
        // sending quota
        $allowed  = (int)$customer->getGroupOption('sending.quota', -1);
        $count    = $customer->countUsageFromQuotaMark();
        $data[] = array(
            'heading' => Yii::t('customers', 'Sending quota'),
            'allowed' => !$allowed ? 0 : ($allowed == -1 ? '&infin;' : $formatter->formatNumber($allowed)),
            'used'    => $formatter->formatNumber($count),
            'percent' => $percent = ($allowed < 1 ? 0 : ($count > $allowed ? 100 : round(($count / $allowed) * 100, 2))),  
            'url'     => 'javascript:;',
            'bar_color' => $percent < 50 ? 'green' : ($percent < 70 ? 'aqua' : ($percent < 90 ? 'yellow' : 'red')),
        );
        
        // lists
        $allowed  = (int)$customer->getGroupOption('lists.max_lists', -1);
        $count    = Lists::model()->countByAttributes(array('customer_id' => $customer->customer_id));
        $data[] = array(
            'heading' => Yii::t('customers', 'Lists'),
            'allowed' => !$allowed ? 0 : ($allowed == -1 ? '&infin;' : $formatter->formatNumber($allowed)),
            'used'    => $formatter->formatNumber($count),
            'percent' => $percent = ($allowed < 1 ? 0 : ($count > $allowed ? 100 : round(($count / $allowed) * 100, 2))), 
            'url'     => Yii::app()->createUrl('lists/index'),
            'bar_color' => $percent < 50 ? 'green' : ($percent < 70 ? 'aqua' : ($percent < 90 ? 'yellow' : 'red')),
        );
        
        // campaigns
        $criteria = new CDbCriteria();
        $criteria->with = array(
            'list' => array(
                'together' => true,
                'joinType' => 'INNER JOIN',
                'condition'=> 'list.customer_id = :cid',
                'params'   => array(':cid' => $customer->customer_id),
            ),
        );
        $allowed  = (int)$customer->getGroupOption('campaigns.max_campaigns', -1);
        $count    = Campaign::model()->count($criteria);
        $data[] = array(
            'heading' => Yii::t('customers', 'Campaigns'),
            'allowed' => !$allowed ? 0 : ($allowed == -1 ? '&infin;' : $formatter->formatNumber($allowed)),
            'used'    => $formatter->formatNumber($count),
            'percent' => $percent = ($allowed < 1 ? 0 : ($count > $allowed ? 100 : round(($count / $allowed) * 100, 2))), 
            'url'     => Yii::app()->createUrl('campaigns/index'),
            'bar_color' => $percent < 50 ? 'green' : ($percent < 70 ? 'aqua' : ($percent < 90 ? 'yellow' : 'red')),
        );
        
        // subscribers
        $criteria = new CDbCriteria();
        $criteria->select = 't.email';
        $criteria->group  = 't.email';
        $criteria->with = array(
            'list' => array(
                'select'   => false,
                'together' => true,
                'joinType' => 'INNER JOIN',
                'condition'=> 'list.customer_id = :cid',
                'params'   => array(':cid' => $customer->customer_id),
            ),
        );
        $allowed  = (int)$customer->getGroupOption('lists.max_subscribers', -1);
        $count    = ListSubscriber::model()->count($criteria);
        $data[] = array(
            'heading' => Yii::t('customers', 'Subscribers'),
            'allowed' => !$allowed ? 0 : ($allowed == -1 ? '&infin;' : $formatter->formatNumber($allowed)),
            'used'    => $formatter->formatNumber($count),
            'percent' => $percent = ($allowed < 1 ? 0 : ($count > $allowed ? 100 : round(($count / $allowed) * 100, 2))), 
            'url'     => Yii::app()->createUrl('lists/index'),
            'bar_color' => $percent < 50 ? 'green' : ($percent < 70 ? 'aqua' : ($percent < 90 ? 'yellow' : 'red')),
        );
        
        // delivery servers
        $allowed  = (int)$customer->getGroupOption('servers.max_delivery_servers', 0);
        $count    = DeliveryServer::model()->countByAttributes(array('customer_id' => $customer->customer_id));
        $data[] = array(
            'heading' => Yii::t('customers', 'Delivery servers'),
            'allowed' => !$allowed ? 0 : ($allowed == -1 ? '&infin;' : $formatter->formatNumber($allowed)),
            'used'    => $formatter->formatNumber($count),
            'percent' => $percent = ($allowed < 1 ? 0 : ($count > $allowed ? 100 : round(($count / $allowed) * 100, 2))), 
            'url'     => Yii::app()->createUrl('delivery_servers/index'),
            'bar_color' => $percent < 50 ? 'green' : ($percent < 70 ? 'aqua' : ($percent < 90 ? 'yellow' : 'red')),
        );
        
        // bounce servers
        $allowed  = (int)$customer->getGroupOption('servers.max_bounce_servers', 0);
        $count    = BounceServer::model()->countByAttributes(array('customer_id' => $customer->customer_id));
        $data[] = array(
            'heading' => Yii::t('customers', 'Bounce servers'),
            'allowed' => !$allowed ? 0 : ($allowed == -1 ? '&infin;' : $formatter->formatNumber($allowed)),
            'used'    => $formatter->formatNumber($count),
            'percent' => $percent = ($allowed < 1 ? 0 : ($count > $allowed ? 100 : round(($count / $allowed) * 100, 2))), 
            'url'     => Yii::app()->createUrl('bounce_servers/index'),
            'bar_color' => $percent < 50 ? 'green' : ($percent < 70 ? 'aqua' : ($percent < 90 ? 'yellow' : 'red')),
        );
        
        // fbl servers
        $allowed  = (int)$customer->getGroupOption('servers.max_fbl_servers', 0);
        $count    = FeedbackLoopServer::model()->countByAttributes(array('customer_id' => $customer->customer_id));
        $data[] = array(
            'heading' => Yii::t('customers', 'Feedback servers'),
            'allowed' => !$allowed ? 0 : ($allowed == -1 ? '&infin;' : $formatter->formatNumber($allowed)),
            'used'    => $formatter->formatNumber($count),
            'percent' => $percent = ($allowed < 1 ? 0 : ($count > $allowed ? 100 : round(($count / $allowed) * 100, 2))),  
            'url'     => Yii::app()->createUrl('feedback_loop_servers/index'),
            'bar_color' => $percent < 50 ? 'green' : ($percent < 70 ? 'aqua' : ($percent < 90 ? 'yellow' : 'red')),
        );
        
        return $this->renderJson(array(
            'html' => $this->renderPartial('_usage', array('items' => $data), true)
        ));
    }
    
    /**
     * Display country zones
     */
    public function actionZones_by_country()
    {
        $criteria = new CDbCriteria();
        $criteria->select = 'zone_id, name';
        $criteria->compare('country_id', (int) Yii::app()->request->getQuery('country_id'));
        $models = Zone::model()->findAll($criteria);
        
        $zones = array(
            array('zone_id' => '', 'name' => Yii::t('app', 'Please select'))
        );
        foreach ($models as $model) {
            $zones[] = array(
                'zone_id'    => $model->zone_id, 
                'name'        => $model->name
            );
        }
        return $this->renderJson(array('zones' => $zones));
    }
    
    /**
     * Log the customer out
     */
    public function actionLogout()
    {
        $logoutUrl = Yii::app()->customer->loginUrl;
        
        if (Yii::app()->customer->getState('__customer_impersonate')) {
            $logoutUrl = Yii::app()->apps->getAppUrl('backend', 'customers/index', true);
        }
        
        Yii::app()->customer->logout();
        $this->redirect($logoutUrl);    
    }
    
    /**
     * Callback method to render the customer account tabs
     */
    public function renderTabs()
    {
        $route = $this->route;
        $priority = 0;
        $tabs = array();
        
        $tabs[] = array(
                'label'     => '<span class="glyphicon glyphicon-list"></span> '.Yii::t('customers', 'Profile'), 
                'url'       => array('account/index'), 
                'active'    => strpos('account/index', $route) === 0,
                'priority'  => (++$priority) * 10,
        );
        
        $tabs[] = array(
                'label'     => '<span class="glyphicon glyphicon-briefcase"></span> '.Yii::t('customers', 'Company'), 
                'url'       => array('account/company'), 
                'active'    => strpos('account/company', $route) === 0,
                'priority'  => (++$priority) * 10,
        );

        $sort = array();
        foreach ($tabs as $index => $tab) {
            if (!isset($tab['label'], $tab['url'], $tab['active'])) {
                unset($tabs[$index]);
                continue;
            }
            
            $sort[] = isset($tab['priority']) ? (int)$tab['priority'] : (++$priority);
            
            if (isset($tabs['priority'])) {
                unset($tabs['priority']);
            }
            
            if (isset($tabs['items'])) {
                unset($tabs['items']);
            }
        }
        
        if (empty($tabs) || !is_array($tabs)) {
            return;
        }
        
        array_multisort($sort, $tabs);
        
        return $this->widget('zii.widgets.CMenu', array(
            'htmlOptions'   => array('class' => 'nav nav-tabs'),
            'items'         => $tabs,
            'encodeLabel'   => false,
        ), true);
    }
}