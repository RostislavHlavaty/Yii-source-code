<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Customer_groupsController
 * 
 * Handles the actions for customer groups related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.3
 */
 
class Customer_groupsController extends Controller
{
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('customer-groups.js')));
        parent::init();
    }
    
    /**
     * Define the filters for various controller actions
     * Merge the filters with the ones from parent implementation
     */
    public function filters()
    {
        $filters = array(
            'postOnly + delete, copy',
        );
        
        return CMap::mergeArray($filters, parent::filters());
    }
    
    /**
     * List available customer groups
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;
        $group   = new CustomerGroup('search');
        
        $group->unsetAttributes();
        $group->attributes = (array)$request->getQuery($group->modelName, array());

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('customers', 'View groups'),
            'pageHeading'       => Yii::t('customers', 'View groups'),
            'pageBreadcrumbs'   => array(
                Yii::t('customers', 'Customers') => $this->createUrl('customers/index'),
                Yii::t('customers', 'Groups')    => $this->createUrl('customer_groups/index'),
                Yii::t('app', 'View all')
            )
        ));

        $this->render('list', compact('group'));
    }
    
    /**
     * Create a new customer group
     */
    public function actionCreate()
    {
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $hooks   = Yii::app()->hooks;
        $group   = new CustomerGroup();
        
        $lists   = new CustomerGroupOptionLists();
        $servers = new CustomerGroupOptionServers();
        $sending = new CustomerGroupOptionSending();
        $quotaCounters = new CustomerGroupOptionQuotaCounters();
        $campaigns = new CustomerGroupOptionCampaigns();
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($group->modelName, array()))) {
            
            $transaction = Yii::app()->getDb()->beginTransaction();
            $error = $success = null;
            
            try {
                
                $group->attributes = $attributes;
                if (!$group->save()) {
                    throw new Exception($error = Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                } else {
                    $success = Yii::t('app', 'Your form has been successfully saved!');
                }  
                
                $models = array($lists, $servers, $sending, $quotaCounters, $campaigns);
                foreach ($models as $model) {
                    $model->setGroup($group);
                    $model->attributes = (array)$request->getPost($model->modelName, array());
                    
                    if ($model instanceof CustomerGroupOptionCampaigns && isset(Yii::app()->params['POST'][$model->modelName]['email_footer'])) {
                        $model->email_footer = Yii::app()->ioFilter->purify(Yii::app()->params['POST'][$model->modelName]['email_footer']);
                    }
            
                    if (!$model->save()) {
                        $error = true;
                    }
                }

                if ($error) {
                    throw new Exception($error = Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                }

                $transaction->commit();
            } catch(Exception $e) {
                $transaction->rollBack();
                $error = $e->getMessage();
                $success = null;
            }
            
            if ($success) {
                $notify->addSuccess($success);
            } else {
                $notify->addError($error);
            }

            $hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'group'     => $group,
            )));
            
            if ($collection->success) {
                $this->redirect(array('customer_groups/update', 'id' => $group->group_id));
            }
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('customers', 'Create new group'), 
            'pageHeading'       => Yii::t('customers', 'Create new customer group'),
            'pageBreadcrumbs'   => array(
                Yii::t('customers', 'Customers') => $this->createUrl('customers/index'),
                Yii::t('customers', 'Groups')    => $this->createUrl('customer_groups/index'),
                Yii::t('app', 'Create new'),
            )
        ));
        
        $campaigns->fieldDecorator->onHtmlOptionsSetup = array($this, '_addCustomerCampaignEmailFooterEditor');
        
        $this->render('form', compact('group', 'lists', 'campaigns', 'servers', 'sending', 'quotaCounters'));
    }
    
    /**
     * Update existing customer group
     */
    public function actionUpdate($id)
    {
        $group = CustomerGroup::model()->findByPk((int)$id);
        
        if (empty($group)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $hooks   = Yii::app()->hooks;
        
        $lists = new CustomerGroupOptionLists();
        $lists->setGroup($group);
        
        $servers = new CustomerGroupOptionServers();
        $servers->setGroup($group);
        
        $sending = new CustomerGroupOptionSending();
        $sending->setGroup($group);
        
        $quotaCounters = new CustomerGroupOptionQuotaCounters();
        $quotaCounters->setGroup($group);
        
        $campaigns = new CustomerGroupOptionCampaigns();
        $campaigns->setGroup($group);

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($group->modelName, array()))) {
            $transaction = Yii::app()->getDb()->beginTransaction();
            $error = $success = null;
            
            try {
                
                $group->attributes = $attributes;
                if (!$group->save()) {
                    throw new Exception($error = Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                } else {
                    $success = Yii::t('app', 'Your form has been successfully saved!');
                } 
                
                $models = array($lists, $servers, $sending, $quotaCounters, $campaigns);
                foreach ($models as $model) {
                    $model->attributes = (array)$request->getPost($model->modelName, array());
                    
                    if ($model instanceof CustomerGroupOptionCampaigns && isset(Yii::app()->params['POST'][$model->modelName]['email_footer'])) {
                        $model->email_footer = Yii::app()->ioFilter->purify(Yii::app()->params['POST'][$model->modelName]['email_footer']);
                    }
                    
                    if (!$model->save()) {
                        $error = true;
                    }
                } 

                if ($error) {
                    throw new Exception($error = Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                }

                $transaction->commit();
            } catch(Exception $e) {
                $transaction->rollBack();
                $error = $e->getMessage();
                $success = null;
            }
            
            if ($success) {
                $notify->addSuccess($success);
            } else {
                $notify->addError($error);
            }

            $hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'group'     => $group,
            )));
            
            if ($collection->success) {
                $this->redirect(array('customer_groups/update', 'id' => $group->group_id));
            }
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('customers', 'Update group'), 
            'pageHeading'       => Yii::t('customers', 'Update customer group'),
            'pageBreadcrumbs'   => array(
                Yii::t('customers', 'Customers') => $this->createUrl('customers/index'),
                Yii::t('customers', 'Groups')    => $this->createUrl('customer_groups/index'),
                Yii::t('app', 'Update'),
            )
        ));
        
        $campaigns->fieldDecorator->onHtmlOptionsSetup = array($this, '_addCustomerCampaignEmailFooterEditor');
        
        $this->render('form', compact('group', 'lists', 'campaigns', 'servers', 'sending', 'quotaCounters'));
    }
    
    /**
     * Copy group
     */
    public function actionCopy($id)
    {
        $group = CustomerGroup::model()->findByPk((int)$id);
        
        if (empty($group)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $group->copy();
        
        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        
        if (!$request->isAjaxRequest) {
            $notify->addSuccess(Yii::t('campaigns', 'Your customer group was successfully copied!'));
            $this->redirect($request->getPost('returnUrl', array('customer_groups/index')));
        }
    }
    
    /**
     * Delete existing customer group
     */
    public function actionDelete($id)
    {
        $group = CustomerGroup::model()->findByPk((int)$id);
        
        if (empty($group)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $delete  = true;
        
        if ($group->group_id == (int)Yii::app()->options->get('system.customer_registration.default_group')) {
            $notify->addWarning(Yii::t('app', 'This group cannot be removed since it is the default group for registration process'));
            $delete = false;
        }
        
        if ($delete && $group->group_id == (int)Yii::app()->options->get('system.customer_sending.move_to_group_id')) {
            $notify->addWarning(Yii::t('app', 'This group cannot be removed since it is used for moving customers in when their quota is reached'));
            $delete = false;
        }
        
        if ($delete) {
            $criteria = new CDbCriteria();
            $criteria->compare('t.code', 'system.customer_sending.move_to_group_id');
            $criteria->compare('t.value', $group->group_id);
            $criteria->addCondition('t.group_id != :gid');
            $criteria->params[':gid'] = $group->group_id;
            $model = CustomerGroupOption::model()->find($criteria);
            if (!empty($model)) {
                $delete = false;
            }    
        }

        if ($delete) {
            $group->preDeleteCheckDone = true;
            $group->delete();
            $notify->addSuccess(Yii::t('app', 'The item has been successfully deleted!'));
        }

        if (!$request->getQuery('ajax')) {
            $this->redirect($request->getPost('returnUrl', array('customer_groups/index')));
        }
    }
    
    /**
     * Reset sending quota
     */
    public function actionReset_sending_quota($id)
    {
        $group = CustomerGroup::model()->findByPk((int)$id);
        
        if (empty($group)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $group->resetSendingQuota();
 
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        
        $notify->addSuccess(Yii::t('customers', 'The sending quota has been successfully reseted!'));
        
        if (!$request->isAjaxRequest) {
            $this->redirect($request->getPost('returnUrl', array('customer_groups/index')));
        }
    }
    
    /**
     * Callback method to set the editor options for email footer in campaigns
     */
    public function _addCustomerCampaignEmailFooterEditor(CEvent $event)
    {
        if (!in_array($event->params['attribute'], array('email_footer'))) {
            return;
        }
        
        $options = array();
        if ($event->params['htmlOptions']->contains('wysiwyg_editor_options')) {
            $options = (array)$event->params['htmlOptions']->itemAt('wysiwyg_editor_options');
        }
        $options['id'] = CHtml::activeId($event->sender->owner, $event->params['attribute']);
        $event->params['htmlOptions']->add('wysiwyg_editor_options', $options);
    }
}