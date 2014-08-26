<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Bounce_serversController
 * 
 * Handles the actions for bounce servers related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4
 */
 
class Bounce_serversController extends Controller
{
    // init method
    public function init()
    {
        parent::init();
        $customer = Yii::app()->customer->getModel();
        if (!((int)$customer->getGroupOption('servers.max_bounce_servers', 0))) {
            $this->redirect(array('dashboard/index'));
        }
    }
    
    /**
     * Define the filters for various controller actions
     * Merge the filters with the ones from parent implementation
     */
    public function filters()
    {
        $filters = array(
            'postOnly + delete',
        );
        
        return CMap::mergeArray($filters, parent::filters());
    }
    
    /**
     * List available bounce servers
     */
    public function actionIndex()
    {
        $customer   = Yii::app()->customer->getModel();
        $request    = Yii::app()->request;
        $server     = new BounceServer('search');
        $server->unsetAttributes();
        
        $server->attributes = (array)$request->getQuery($server->modelName, array());
        $server->customer_id= (int)$customer->customer_id;
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'View servers'),
            'pageHeading'       => Yii::t('servers', 'View servers'),
            'pageBreadcrumbs'   => array(
                Yii::t('servers', 'Bounce servers') => $this->createUrl('bounce_servers/index'),
                Yii::t('app', 'View all')
            )
        ));

        $this->render('list', compact('server'));
    }
    
    /**
     * Create a new bounce server
     */
    public function actionCreate()
    {
        $customer   = Yii::app()->customer->getModel();
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $server     = new BounceServer();

        $server->customer_id = (int)$customer->customer_id;
        
        if (($limit = (int)$customer->getGroupOption('servers.max_bounce_servers', 0)) > -1) {
            $count = BounceServer::model()->countByAttributes(array('customer_id' => (int)$customer->customer_id));
            if ($count >= $limit) {
                $notify->addWarning(Yii::t('servers', 'You have reached the maximum number of allowed servers!'));
                $this->redirect(array('bounce_servers/index'));
            }
        }
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($server->modelName, array()))) {
            if (!$server->isNewRecord && empty($attributes['password']) && isset($attributes['password'])) {
                unset($attributes['password']);
            }
            $server->attributes  = $attributes;
            $server->customer_id = $customer->customer_id;
            if (!$server->testConnection() || !$server->save()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            } else {
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
                $notify->addSuccess(Yii::t('servers', 'Please do not forget to associate this server with a delivery server!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'server'    => $server,
            )));
            
            if ($collection->success) {
                $this->redirect(array('bounce_servers/update', 'id' => $server->server_id));
            }
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'Create new server'), 
            'pageHeading'       => Yii::t('servers', 'Create new bounce server'),
            'pageBreadcrumbs'   => array(
                Yii::t('servers', 'Bounce servers') => $this->createUrl('bounce_servers/index'),
                Yii::t('app', 'Create new'),
            )
        ));
        
        $this->render('form', compact('server'));
    }
    
    /**
     * Update existing bounce server
     */
    public function actionUpdate($id)
    {
        $customer = Yii::app()->customer->getModel();
        
        $server = BounceServer::model()->findByAttributes(array(
            'server_id'     => (int)$id,
            'customer_id'   => (int)$customer->customer_id,
        ));
        
        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        if (!$server->getCanBeUpdated()) {
            $this->redirect(array('bounce_servers/index'));
        }
        
        $request  = Yii::app()->request;
        $notify   = Yii::app()->notify;
        
        if ($server->getIsLocked()) {
            $notify->addWarning(Yii::t('servers', 'This server is locked, you cannot change or delete it!'));
            $this->redirect(array('bounce_servers/index'));
        }

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($server->modelName, array()))) {
            if (!$server->isNewRecord && empty($attributes['password']) && isset($attributes['password'])) {
                unset($attributes['password']);
            }
            
            $server->attributes  = $attributes;
            $server->customer_id = $customer->customer_id;
            
            if (!$server->testConnection() || !$server->save()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            } else {
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
                $deliveryServers = $server->deliveryServers;
                if (empty($deliveryServers)) {
                    $notify->addSuccess(Yii::t('servers', 'Please do not forget to associate this server with a delivery server!'));
                } elseif ($server->settingsChanged) {
                    $servers = array();
                    foreach ($deliveryServers as $srv) {
                        $servers[] = CHtml::link('&raquo; ' . $srv->hostname, $this->createUrl('delivery_servers/update', array('type' => $srv->type, 'id' => $srv->server_id)), array('target' => '_blank'));
                    }
                    $prefix = '<br />' . str_repeat('&nbsp;', 5);
                    $message = Yii::t('servers', 'Following associated servers were marked as inactive and you need to verify them again: ');
                    $message .= $prefix . implode(', ' . $prefix, $servers);
                    $notify->addSuccess($message);
                }
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'server'    => $server,
            )));
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'Update server'), 
            'pageHeading'       => Yii::t('servers', 'Update bounce server'),
            'pageBreadcrumbs'   => array(
                Yii::t('servers', 'Bounce servers') => $this->createUrl('bounce_servers/index'),
                Yii::t('app', 'Update'),
            )
        ));
        
        $this->render('form', compact('server'));
    }
    
    /**
     * Delete existing bounce server
     */
    public function actionDelete($id)
    {
        $customer = Yii::app()->customer->getModel();
        
        $server = BounceServer::model()->findByAttributes(array(
            'server_id'     => (int)$id,
            'customer_id'   => (int)$customer->customer_id,
        ));
        
        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        if ($server->getCanBeDeleted() && !$server->getIsLocked()) {
            $server->delete();
        }

        $request  = Yii::app()->request;
        $notify   = Yii::app()->notify;
        
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('app', 'The item has been successfully deleted!'));
            $this->redirect($request->getPost('returnUrl', array('bounce_servers/index')));
        }
    }
}