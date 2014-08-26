<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Delivery_serversController
 * 
 * Handles the actions for delivery servers related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class Delivery_serversController extends Controller
{
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('delivery-servers.js')));
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
            'postOnly + delete, validate',
        );
        
        return CMap::mergeArray($filters, parent::filters());
    }

    /**
     * List all available delivery servers
     */
    public function actionIndex()
    {
        $request    = Yii::app()->request;
        $server     = new DeliveryServer('search');
        $server->unsetAttributes();
        
        $server->attributes = (array)$request->getQuery($server->modelName, array());
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'View servers'),
            'pageHeading'       => Yii::t('servers', 'View servers'),
            'pageBreadcrumbs'   => array(
                Yii::t('servers', 'Delivery servers') => $this->createUrl('delivery_servers/index'),
                Yii::t('app', 'View all')
            )
        ));
        
        $types = DeliveryServer::getTypesMapping();

        $this->render('list', compact('server', 'types'));
    }
    
    /**
     * Create a new delivery server
     */
    public function actionCreate($type)
    {
        $types = DeliveryServer::getTypesMapping();
        
        if (!isset($types[$type])) {
            throw new CHttpException(500, Yii::t('servers', 'Server type not allowed.'));
        }
        
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $modelClass = $types[$type];
        $server     = new $modelClass();
        
        if (($failureMessage = $server->requirementsFailed())) {
            $notify->addWarning($failureMessage);
            $this->redirect(array('delivery_servers/index'));
        }
        
        $policy   = new DeliveryServerDomainPolicy();
        $policies = array();
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($server->modelName, array()))) {
            if (!$server->isNewRecord && empty($attributes['password']) && isset($attributes['password'])) {
                unset($attributes['password']);
            }
            $server->attributes = $attributes;
            
            if ($policiesAttributes = (array)$request->getPost($policy->modelName, array())) {
                foreach ($policiesAttributes as $attributes) {
                    $policyModel = new DeliveryServerDomainPolicy();
                    $policyModel->attributes = $attributes;
                    $policies[] = $policyModel;
                }
            }
            
            if (!$server->save()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            } else {
                if (!empty($policies)) {
                    foreach ($policies as $policyModel) {
                        $policyModel->server_id = $server->server_id;
                        $policyModel->save();
                    }
                }
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'server'    => $server,
            )));
            
            if ($collection->success) {
                $this->redirect(array('delivery_servers/update', 'type' => $type, 'id' => $server->server_id));
            }
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'Create new server'), 
            'pageHeading'       => Yii::t('servers', 'Create new delivery server'),
            'pageBreadcrumbs'   => array(
                Yii::t('servers', 'Delivery servers') => $this->createUrl('delivery_servers/index'),
                Yii::t('app', 'Create new'),
            )
        ));
        
        $this->render('form-' . $type, compact('server', 'policy', 'policies'));
    }
    
    /**
     * Update existing delivery server
     */
    public function actionUpdate($type, $id)
    {
        $types = DeliveryServer::getTypesMapping();
        
        if (!isset($types[$type])) {
            throw new CHttpException(500, Yii::t('servers', 'Server type not allowed.'));
        }
        
        $server = DeliveryServer::model($types[$type])->findByAttributes(array(
            'server_id' => (int)$id,
            'type'      => $type,
        ));

        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        if (!$server->getCanBeUpdated()) {
            $this->redirect(array('delivery_servers/index'));
        }
        
        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        
        if (($failureMessage = $server->requirementsFailed())) {
            $notify->addWarning($failureMessage);
            $this->redirect(array('delivery_servers/index'));
        }
        
        $policy   = new DeliveryServerDomainPolicy();
        $policies = DeliveryServerDomainPolicy::model()->findAllByAttributes(array('server_id' => $server->server_id));
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($server->modelName, array()))) {
            if (!$server->isNewRecord && empty($attributes['password']) && isset($attributes['password'])) {
                unset($attributes['password']);
            }
            $server->attributes = $attributes;
            
            $policies = array();
            if ($policiesAttributes = (array)$request->getPost($policy->modelName, array())) {
                foreach ($policiesAttributes as $attributes) {
                    $policyModel = new DeliveryServerDomainPolicy();
                    $policyModel->attributes = $attributes;
                    $policies[] = $policyModel;
                }
            }
            
            if (!$server->save()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            } else {
                DeliveryServerDomainPolicy::model()->deleteAllByAttributes(array('server_id' => $server->server_id));
                if (!empty($policies)) {
                    foreach ($policies as $policyModel) {
                        $policyModel->server_id = $server->server_id;
                        $policyModel->save();
                    }
                }
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'server'    => $server,
            )));
            
            if ($collection->success) {
                $this->redirect(array('delivery_servers/update', 'type' => $type, 'id' => $server->server_id));
            }
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'Update server'), 
            'pageHeading'       => Yii::t('servers', 'Update delivery server'),
            'pageBreadcrumbs'   => array(
                Yii::t('servers', 'Delivery servers') => $this->createUrl('delivery_servers/index'),
                Yii::t('app', 'Update'),
            )
        ));
        
        $this->render('form-' . $type, compact('server', 'policy', 'policies'));
    }
    
    /**
     * Delete existing delivery server
     */
    public function actionDelete($id)
    {
        $server = DeliveryServer::model()->findByPk((int)$id);
        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        if ($server->getCanBeDeleted()) {
            $server->delete();
        }

        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('app', 'The item has been successfully deleted!'));
            $this->redirect($request->getPost('returnUrl', array('delivery_servers/index')));
        }
    }
    
    /**
     * Validate a delivery server
     * The delivery server will stay inactive until validation by email.
     * While delivery server is inactive it cannot be used to send emails.
     */
    public function actionValidate($id)
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $options    = Yii::app()->options;
        
        if (!($email = $request->getPost('email'))) {
            throw new CHttpException(500, Yii::t('servers', 'The email address is missing.'));
        }
        
        $_server = DeliveryServer::model()->findByPk((int)$id);
        
        if (empty($_server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new CHttpException(500, Yii::t('app', 'The email address you provided does not seem to be valid.'));
        }
        
        $mapping = DeliveryServer::getTypesMapping();
        if (!isset($mapping[$_server->type])) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $server = DeliveryServer::model($mapping[$_server->type])->findByPk((int)$_server->server_id);

        $server->confirmation_key = sha1(uniqid(rand(0, time()), true));
        $server->save(false);
        
        $emailTemplate  = $options->get('system.email_templates.common');
        $emailBody      = $this->renderPartial('confirm-server-email', compact('server'), true);
        $emailTemplate  = str_replace('[CONTENT]', $emailBody, $emailTemplate);

        $params = $server->getParamsArray(array(
            'to'        => $email,
            'subject'   => Yii::t('servers', 'Please validate this server.'),
            'body'      => $emailTemplate,
        ));

        if ($server->sendEmail($params)) {
            $notify->addSuccess(Yii::t('servers', 'Please check your mailbox to confirm the server.'));
            $redirect = array('delivery_servers/index');
        } else {
            $dump = Yii::t('servers', 'Internal failure, maybe due to missing functions like {functions}!', array('{functions}' => 'proc_open'));
            if ($log = $server->getMailer()->getLog()) {
                $dump = $log;
            }
            if (preg_match('/\+\+\sSwift_SmtpTransport\sstarted.*/s', $dump, $matches)) {
                $dump = $matches[0];
            }
            $dump = CHtml::encode(str_replace("\n\n", "\n", $dump));
            $dump = nl2br($dump);
            $notify->addError(Yii::t('servers', 'Cannot send the confirmation email using the data you provided.'));
            $notify->addWarning(Yii::t('servers', 'Here is a transcript of the error message:') . '<hr />');
            $notify->addWarning($dump);
            
            $redirect = array('delivery_servers/update', 'type' => $server->type, 'id' => $server->server_id);
        }
        
        $this->redirect($redirect);
    }
    
    /**
     * Confirm the validation of a delivery server
     * This is accessed from the validation email and changes 
     * the status of a delivery server from inactive in active thus allowing the application to send 
     * emails using this server.
     */
    public function actionConfirm($key)
    {
        $_server = DeliveryServer::model()->findByAttributes(array(
            'confirmation_key' => $key,
        ));
        
        if (empty($_server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $mapping = DeliveryServer::getTypesMapping();
        if (!isset($mapping[$_server->type])) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        $server = DeliveryServer::model($mapping[$_server->type])->findByPk((int)$_server->server_id);
        
        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $server->status = DeliveryServer::STATUS_ACTIVE;
        $server->confirmation_key = null;
        $server->save(false);
        
        if (!empty($server->hostname)) {
            $notify->addSuccess(Yii::t('servers', 'You have successfully confirmed the server {serverName}.', array(
                '{serverName}' => $server->hostname,
            )));    
        } else {
            $notify->addSuccess(Yii::t('servers', 'The server has been successfully confirmed!'));
        }

        $this->redirect(array('delivery_servers/index'));
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