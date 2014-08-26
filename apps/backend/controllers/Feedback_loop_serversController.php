<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Feedback_loop_serversController
 * 
 * Handles the actions for feedback loop servers related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.3.1
 */
 
class Feedback_loop_serversController extends Controller
{
    public function init()
    {
        $this->onBeforeAction = array($this, '_registerJuiBs');
        parent::init();
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
     * List available feedback loop servers
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;
        $server  = new FeedbackLoopServer('search');
        $server->unsetAttributes();
        
        $server->attributes = (array)$request->getQuery($server->modelName, array());
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'View servers'),
            'pageHeading'       => Yii::t('servers', 'View servers'),
            'pageBreadcrumbs'   => array(
                Yii::t('servers', 'Feedback loop servers') => $this->createUrl('feedback_loop_servers/index'),
                Yii::t('app', 'View all')
            )
        ));

        $this->render('list', compact('server'));
    }
    
    /**
     * Create a new feedback loop server
     */
    public function actionCreate()
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $server     = new FeedbackLoopServer();

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($server->modelName, array()))) {
            if (!$server->isNewRecord && empty($attributes['password']) && isset($attributes['password'])) {
                unset($attributes['password']);
            }
            $server->attributes = $attributes;
            if (!$server->testConnection() || !$server->save()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            } else {
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'server'    => $server,
            )));
            
            if ($collection->success) {
                $this->redirect(array('feedback_loop_servers/update', 'id' => $server->server_id));
            }
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'Create new server'), 
            'pageHeading'       => Yii::t('servers', 'Create new feedback loop server'),
            'pageBreadcrumbs'   => array(
                Yii::t('servers', 'Feedback loop servers') => $this->createUrl('feedback_loop_servers/index'),
                Yii::t('app', 'Create new'),
            )
        ));
        
        $this->render('form', compact('server'));
    }
    
    /**
     * Update existing feedback loop server
     */
    public function actionUpdate($id)
    {
        $server = FeedbackLoopServer::model()->findByPk((int)$id);
        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        if (!$server->getCanBeUpdated()) {
            $this->redirect(array('feedback_loop_servers/index'));
        }
        
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($server->modelName, array()))) {
            if (!$server->isNewRecord && empty($attributes['password']) && isset($attributes['password'])) {
                unset($attributes['password']);
            }
            $server->attributes = $attributes;
            if (!$server->testConnection() || !$server->save()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            } else {
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'server'    => $server,
            )));
            
            if ($collection->success) {
            
            }
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'Update server'), 
            'pageHeading'       => Yii::t('servers', 'Update feedback loop server'),
            'pageBreadcrumbs'   => array(
                Yii::t('servers', 'Feedback loop servers') => $this->createUrl('feedback_loop_servers/index'),
                Yii::t('app', 'Update'),
            )
        ));
        
        $this->render('form', compact('server'));
    }
    
    /**
     * Delete existing feedback loop server
     */
    public function actionDelete($id)
    {
        $server = FeedbackLoopServer::model()->findByPk((int)$id);
        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        if ($server->getCanBeDeleted()) {
            $server->delete();
        }
        
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('app', 'The item has been successfully deleted!'));
            $this->redirect($request->getPost('returnUrl', array('feedback_loop_servers/index')));
        }
    }
    
    /**
     * Callback to register Jquery ui bootstrap only for certain actions
     */
    public function _registerJuiBs($event)
    {
        if (in_array($event->params['action']->id, array('create', 'update'))) {
            $this->getData('pageStyles')->mergeWith(array(
                array('src' => Yii::app()->apps->getBaseUrl('assets/css/jui-bs/jquery-ui-1.10.3.custom.css'), 'priority' => -1001),
            ));
        }
    }
}