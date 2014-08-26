<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * OrdersController
 * 
 * Handles the actions for price plans related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.3
 */
 
class OrdersController extends Controller
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
            'postOnly + delete, delete_note',
        );
        
        return CMap::mergeArray($filters, parent::filters());
    }
    
    /**
     * List all available orders
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;
        $ioFilter= Yii::app()->ioFilter;
        $order   = new PricePlanOrder('search');
        $order->unsetAttributes();
        
        $order->attributes = $ioFilter->xssClean((array)$request->getOriginalQuery($order->modelName, array()));
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('orders', 'View orders'),
            'pageHeading'       => Yii::t('orders', 'View orders'),
            'pageBreadcrumbs'   => array(
                Yii::t('orders', 'Orders') => $this->createUrl('orders/index'),
                Yii::t('app', 'View all')
            )
        ));
        
        $this->render('list', compact('order'));
    }
    
    /**
     * Create order
     */
    public function actionCreate()
    {
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $order   = new PricePlanOrder();

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($order->modelName, array()))) {
            $order->attributes = $attributes;
            if (!$order->save()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            } else {
                $note = new PricePlanOrderNote();
                $note->attributes = (array)$request->getPost($note->modelName, array());
                $note->order_id   = $order->order_id;
                $note->user_id    = Yii::app()->user->getId();
                $note->save();
                
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'order'     => $order,
            )));
            
            if ($collection->success) {
                $this->redirect(array('orders/index'));
            }
        }
        
        $note = new PricePlanOrderNote('search');
        $note->attributes = (array)$request->getQuery($note->modelName, array());
        $note->order_id   = (int)$order->order_id;

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('orders', 'Create order'),
            'pageHeading'       => Yii::t('orders', 'Create order'),
            'pageBreadcrumbs'   => array(
                Yii::t('orders', 'Orders') => $this->createUrl('orders/index'),
                Yii::t('app', 'Create'),
            )
        ));
        
        $this->render('form', compact('order', 'note'));
    }
    
    /**
     * Update existing order
     */
    public function actionUpdate($id)
    {
        $order = PricePlanOrder::model()->findByPk((int)$id);

        if (empty($order)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($order->modelName, array()))) {
            $order->attributes = $attributes;
            if (!$order->save()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            } else {
                $note = new PricePlanOrderNote();
                $note->attributes = (array)$request->getPost($note->modelName, array());
                $note->order_id   = $order->order_id;
                $note->user_id    = Yii::app()->user->getId();
                $note->save();
                
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'order'     => $order,
            )));
            
            if ($collection->success) {
                $this->redirect(array('orders/index'));
            }
        }
        
        $note = new PricePlanOrderNote('search');
        $note->attributes = (array)$request->getQuery($note->modelName, array());
        $note->order_id   = (int)$order->order_id;
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('orders', 'Update order'),
            'pageHeading'       => Yii::t('orders', 'Update order'),
            'pageBreadcrumbs'   => array(
                Yii::t('orders', 'Orders') => $this->createUrl('orders/index'),
                Yii::t('app', 'Update'),
            )
        ));
        
        $this->render('form', compact('order', 'note'));
    }
    
    public function actionView($id)
    {
        $request = Yii::app()->request;
        $order   = PricePlanOrder::model()->findByPk((int)$id);
        
        if (empty($order)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $pricePlan = $order->plan;
        $customer  = $order->customer;
        
        $note = new PricePlanOrderNote('search');
        $note->unsetAttributes();
        $note->attributes = (array)$request->getQuery($note->modelName, array());
        $note->order_id   = (int)$order->order_id;
        
        $transaction = new PricePlanOrderTransaction('search');
        $transaction->unsetAttributes();
        $transaction->attributes = (array)$request->getQuery($transaction->modelName, array());
        $transaction->order_id   = $order->order_id;
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('orders', 'View your order'),
            'pageHeading'       => Yii::t('orders', 'View your order'),
            'pageBreadcrumbs'   => array(
                Yii::t('price_plans', 'Price plans') => $this->createUrl('price_plans/index'),
                Yii::t('orders', 'Orders') => $this->createUrl('price_plans/orders'),
                Yii::t('app', 'View')
            )
        ));
        
        $this->render('order_detail', compact('order', 'pricePlan', 'customer', 'note', 'transaction'));
    }
    
    /**
     * Delete existing order
     * Warning, all data related to this order will also be deleted!
     */
    public function actionDelete($id)
    {
        $order = PricePlanOrder::model()->findByPk((int)$id);
        
        if (empty($order)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $order->delete();
 
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('app', 'The item has been successfully deleted!'));
            $this->redirect($request->getPost('returnUrl', array('orders/index')));
        }
    }
    
    /**
     * Delete existing order note
     */
    public function actionDelete_note($id)
    {
        $note = PricePlanOrderNote::model()->findByPk((int)$id);
        
        if (empty($note)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $note->delete();
 
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('app', 'The item has been successfully deleted!'));
            $this->redirect($request->getPost('returnUrl', array('orders/index')));
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