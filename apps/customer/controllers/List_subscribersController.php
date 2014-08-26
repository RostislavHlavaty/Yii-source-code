<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * List_subscribersController
 * 
 * Handles the actions for list subscribers related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class List_subscribersController extends Controller
{
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('subscribers.js')));
        parent::init();
    }
    
    /**
     * Define the filters for various controller actions
     * Merge the filters with the ones from parent implementation
     */
    public function filters()
    {
        return CMap::mergeArray(array(
            'postOnly + delete, subscribe, unsubscribe, bulk_action',
        ), parent::filters());
    }
    
    /**
     * List of behaviors attached to this controller
     * The behaviors are merged with the one from parent implementation
     */
    public function behaviors()
    {
        return CMap::mergeArray(array(
            'callbacks' => array(
                'class' => 'customer.components.behaviors.ListFieldsControllerCallbacksBehavior',
            ),
        ), parent::behaviors());
    }
    
    /**
     * List available subscribers for a list
     */
    public function actionIndex($list_uid)
    {
        $list       = $this->loadListModel($list_uid);
        $request    = Yii::app()->request;
        $hooks      = Yii::app()->hooks;
        $filters    = (array)$request->getPost('filter', array());
        $subscriber = new ListSubscriber();
        
        $subscriberStatusesList = $subscriber->getFilterStatusesList();
        
        /**
         * NOTE: 
         * Following criteria will use filesort and create a temp table because of the group by condition.
         * So far, beside subqueries this is the only optimal way i have found to work fine.
         * Needs optimization in the future if will cause problems.
         */
        $criteria = new CDbCriteria();
        $criteria->select = 't.subscriber_id, t.subscriber_uid, t.status';
        $criteria->compare('t.list_id', $list->list_id);
        $criteria->group = 't.subscriber_id';
        $criteria->order = 't.subscriber_id DESC';
        
        foreach ($filters as $field_id => $value) {
            if (empty($value)) {
                unset($filters[$field_id]);
                continue;
            }
            
            if (is_numeric($field_id)) {
                $model = ListField::model()->findByAttributes(array(
                    'field_id'  => $field_id,
                    'list_id'   => $list->list_id,
                ));
                if (empty($model)) {
                    unset($filters[$field_id]);
                }    
            }
        }
        
        if (!empty($filters['status']) && in_array($filters['status'], array_keys($subscriberStatusesList))) {
            $criteria->compare('status', $filters['status']);
        }
        
        if (!empty($filters)) {

            $with = array();
            foreach ($filters as $field_id => $value) {
                if (!is_numeric($field_id)) {
                    continue;
                }
                
                $i = (int)$field_id;
                $with['fieldValues'.$i] = array(
                    'select'    => false,
                    'together'  => true,
                    'joinType'  => 'INNER JOIN',
                    'condition' => '`fieldValues'.$i.'`.`field_id` = :field_id'.$i.' AND `fieldValues'.$i.'`.`value` LIKE :value'.$i,
                    'params'    => array(
                        ':field_id'.$i  => (int)$field_id,
                        ':value'.$i     => '%'.$value.'%',
                    ),
                );
            }
            
            $md = $subscriber->getMetaData();
            foreach ($filters as $field_id => $value) {
                if (!is_numeric($field_id)) {
                    continue;
                }
                if ($md->hasRelation('fieldValues'.$field_id)) {
                    continue;
                }
                $md->addRelation('fieldValues'.$field_id, array(ListSubscriber::HAS_MANY, 'ListFieldValue', 'subscriber_id'));
            }
            
            if (!empty($with)) {
                $criteria->with = $with;   
            }
        }
        
        // count all confirmed subscribers of this list
        $count = $subscriber->count($criteria);
        
        // instantiate the pagination and apply the limit statement to the query
        $pages = new CPagination($count);
        $pages->pageSize = (int)$subscriber->paginationOptions->getPageSize();
        $pages->applyLimit($criteria);
        
        // load the required models
        $subscribers = $subscriber->findAll($criteria);
        
        // now, we need to know what columns this list has, that is, all the tags available for this list.
        $columns = array();
        $rows = array();
        
        $criteria = new CDbCriteria();
        $criteria->compare('t.list_id', $list->list_id);
        $criteria->order = 't.sort_order ASC';
        
        $fields = ListField::model()->findAll($criteria);
        
        $columns[] = array(
            'label'     => null,
            'field_type'=> 'checkbox',
            'field_id'  => 'bulk_select',
            'value'     => null,
            'checked'   => false,
            'htmlOptions'   => array(),
        );
        
        $columns[] = array(
            'label'     => Yii::t('app', 'Options'),
            'field_type'=> null,
            'field_id'  => null,
            'value'     => null,
        );
        
        $columns[] = array(
            'label'     => Yii::t('app', 'Status'),
            'field_type'=> 'select',
            'field_id'  => 'status',
            'value'     => isset($filters['status']) ? CHtml::encode($filters['status']) : null,
            'options'   => CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $subscriberStatusesList),
        );
        
        foreach ($fields as $field) {
            $columns[] = array(
                'label'     => $field->label,
                'field_type'=> 'text',
                'field_id'  => $field->field_id,
                'value'     => isset($filters[$field->field_id]) ? CHtml::encode($filters[$field->field_id]) : null,
            );
        }
        
        foreach ($subscribers as $index => $subscriber) {
            $subscriberRow = array('columns' => array());
            
            // checkbox
            $subscriberRow['columns'][] = CHtml::checkBox('bulk_select[]', false, array('value' => $subscriber->subscriber_id, 'class' => 'bulk-select'));
            
            $actions = array();
            $actions[] = CHtml::link(' <span class="glyphicon glyphicon-pencil"><!-- --></span> ', array('list_subscribers/update', 'list_uid' => $list->list_uid, 'subscriber_uid' => $subscriber->subscriber_uid), array('title' => Yii::t('app', 'Update')));
            
            if ($subscriber->getCanBeUnsubscribed() && $subscriber->status == ListSubscriber::STATUS_CONFIRMED) {
                $actions[] = CHtml::link(' <span class="glyphicon glyphicon-log-out"><!-- --></span> ', array('list_subscribers/unsubscribe', 'list_uid' => $list->list_uid, 'subscriber_uid' => $subscriber->subscriber_uid), array('class' => 'unsubscribe', 'title' => Yii::t('app', 'Unsubscribe'), 'data-message' => Yii::t('list_subscribers', 'Are you sure you want to unsubscribe this subscriber?')));    
            } elseif ($subscriber->getCanBeConfirmed() && $subscriber->status == ListSubscriber::STATUS_UNCONFIRMED) {
                $actions[] = CHtml::link(' <span class="glyphicon glyphicon-log-in"><!-- --></span> ', array('list_subscribers/subscribe', 'list_uid' => $list->list_uid, 'subscriber_uid' => $subscriber->subscriber_uid), array('class' => 'subscribe', 'title' => Yii::t('list_subscribers', 'Subscribe back'), 'data-message' => Yii::t('list_subscribers', 'Are you sure you want to subscribe back this unsubscriber?')));
            } elseif ($subscriber->getCanBeConfirmed() && $subscriber->status == ListSubscriber::STATUS_UNSUBSCRIBED) {
                $actions[] = CHtml::link(' <span class="glyphicon glyphicon-log-in"><!-- --></span> ', array('list_subscribers/subscribe', 'list_uid' => $list->list_uid, 'subscriber_uid' => $subscriber->subscriber_uid), array('class' => 'subscribe', 'title' => Yii::t('list_subscribers', 'Confirm subscriber'), 'data-message' => Yii::t('list_subscribers', 'Are you sure you want to confirm this subscriber?')));
            }

            $actions[] = CHtml::link(' <span class="glyphicon glyphicon-remove-circle"><!-- --></span> ', array('list_subscribers/delete', 'list_uid' => $list->list_uid, 'subscriber_uid' => $subscriber->subscriber_uid), array('class' => 'delete', 'title' => Yii::t('app', 'Delete'), 'data-message' => Yii::t('app', 'Are you sure you want to delete this item? There is no way coming back after you do it.')));
            
            $subscriberRow['columns'][] = implode(str_repeat('&nbsp;', 3), $actions);
            $subscriberRow['columns'][] = ucfirst(Yii::t('list_subscribers', $subscriber->status));
            
            foreach ($fields as $field) {
                $criteria = new CDbCriteria();
                $criteria->select = 't.value';
                $criteria->compare('field_id', $field->field_id);
                $criteria->compare('subscriber_id', $subscriber->subscriber_id);
                $values = ListFieldValue::model()->findAll($criteria);
                
                $value = array();
                foreach ($values as $val) {
                    $value[] = $val->value;
                }

                $subscriberRow['columns'][] = CHtml::encode(implode(', ', $value));
            }
            
            if (count($subscriberRow['columns']) == count($columns)) {
                $rows[] = $subscriberRow;    
            }
        }
        
        if ($request->isPostRequest && $request->isAjaxRequest) {
            return $this->renderPartial('_list', compact('list', 'subscriber', 'columns', 'rows', 'pages', 'count'));
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('list_subscribers', 'Your mail list subscribers'),
            'pageHeading'       => Yii::t('list_subscribers', 'List subscribers'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                Yii::t('list_subscribers', 'Subscribers') => $this->createUrl('list_subscribers/index', array('list_uid' => $list->list_uid)),
                Yii::t('app', 'View all')
            )
        ));
        
        $subBulkFromSource = new ListSubscriberBulkFromSource();
        $this->render('index', compact('list', 'subscriber', 'columns', 'rows', 'pages', 'count', 'subBulkFromSource'));
    }
    
    /**
     * Create / Add a new subscriber in a list
     */
    public function actionCreate($list_uid)
    {
        $list       = $this->loadListModel($list_uid);
        $request    = Yii::app()->request;
        $hooks      = Yii::app()->hooks;
        
        $listFields = ListField::model()->findAll(array(
            'condition' => 'list_id = :lid',
            'params'    => array(':lid' => $list->list_id),
            'order'     => 'sort_order ASC'
        ));
        
        if (empty($listFields)) {
            throw new CHttpException(404, Yii::t('list_fields', 'Your mail list does not have any field defined.'));
        }
        
        $usedTypes = array();
        foreach ($listFields as $field) {
            $usedTypes[] = $field->type->type_id;
        }
        $criteria = new CDbCriteria();
        $criteria->addInCondition('type_id', $usedTypes);
        $types = ListFieldType::model()->findAll($criteria);
        
        $subscriber = new ListSubscriber();
        $subscriber->list_id = $list->list_id;
    
        $instances = array();
        
        foreach ($types as $type) {
            
            if (empty($type->identifier) || !is_file(Yii::getPathOfAlias($type->class_alias).'.php')) {
                continue;
            }
            
            $component = Yii::app()->getWidgetFactory()->createWidget($this, $type->class_alias, array(
                'fieldType'     => $type,
                'list'          => $list,
                'subscriber'    => $subscriber,
            ));
            
            if (!($component instanceof FieldBuilderType)) {
                continue;
            }
            
            // run the component to hook into next events
            $component->run();
            
            $instances[] = $component;
        }
        
        $fields = array();
        
        // if the fields are saved
        if ($request->isPostRequest) {
            
            $transaction = Yii::app()->db->beginTransaction();
 
            try {

                $customer                = $list->customer;
                $maxSubscribersPerList   = (int)$customer->getGroupOption('lists.max_subscribers_per_list', -1);
                $maxSubscribers          = (int)$customer->getGroupOption('lists.max_subscribers', -1);
                
                if ($maxSubscribers > -1 || $maxSubscribersPerList > -1) {
                    $criteria = new CDbCriteria();
                    $criteria->select = 't.email';
                    $criteria->group  = 't.email';
                    $criteria->with = array(
                        'list' => array(
                            'select'   => false,
                            'together' => true,
                            'joinType' => 'INNER JOIN',
                            'condition'=> 'list.customer_id = :cid',
                            'params'   => array(':cid' => (int)$customer->customer_id),
                        ),
                    );
                    
                    if ($maxSubscribers > -1) {
                        $totalSubscribersCount = ListSubscriber::model()->count($criteria);
                        if ($totalSubscribersCount >= $maxSubscribers) {
                            throw new Exception(Yii::t('lists', 'You have reached the maximum number of allowed subscribers.'));
                        }    
                    }
                    
                    if ($maxSubscribersPerList > -1) {
                        $criteria->compare('list.list_id', (int)$list->list_id);
                        $listSubscribersCount = ListSubscriber::model()->count($criteria);
                        if ($listSubscribersCount >= $maxSubscribersPerList) {
                            throw new Exception(Yii::t('lists', 'You have reached the maximum number of allowed subscribers into this list.'));
                        }
                    }
                }
        
                $attributes = (array)$request->getPost($subscriber->modelName, array());
                if (empty($subscriber->ip_address)) {
                    $subscriber->ip_address = Yii::app()->request->getUserHostAddress();
                }
                if (isset($attributes['status']) && in_array($attributes['status'], array_keys($subscriber->getStatusesList()))) {
                    $subscriber->status = $attributes['status'];
                } else {
                    $subscriber->status = ListSubscriber::STATUS_UNCONFIRMED;
                }
                
                if (!$subscriber->save()) {
                    throw new Exception(Yii::t('app', 'Temporary error, please contact us if this happens too often!'));
                }
                
                // raise event
                $this->callbacks->onSubscriberSave(new CEvent($this->callbacks, array(
                    'fields' => &$fields,
                )));
                
                // if no error thrown but still there are errors in any of the instances, stop.
                foreach ($instances as $instance) {
                    if (!empty($instance->errors)) {
                        throw new Exception(Yii::t('app', 'Your form has a few errors. Please fix them and try again!'));
                    }
                }
                
                // add the default success message
                Yii::app()->notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
                
                // raise event. at this point everything seems to be fine.
                $this->callbacks->onSubscriberSaveSuccess(new CEvent($this->callbacks, array(
                    'instances'     => $instances,
                    'subscriber'    => $subscriber, 
                    'list'          => $list,
                )));

                $transaction->commit();

            } catch (Exception $e) {
                
                $transaction->rollBack();
                Yii::app()->notify->addError($e->getMessage());
                
                // bind default save error event handler
                $this->callbacks->onSubscriberSaveError = array($this->callbacks, '_collectAndShowErrorMessages');
                
                // raise event
                $this->callbacks->onSubscriberSaveError(new CEvent($this->callbacks, array(
                    'instances'     => $instances,
                    'subscriber'    => $subscriber, 
                    'list'          => $list
                )));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'   => $this,
                'success'      => Yii::app()->notify->hasSuccess,
                'subscriber'   => $subscriber,
            )));
            
            if ($collection->success) {
                if ($request->getPost('next_action') && $request->getPost('next_action') == 'create-new') {
                    $this->redirect(array('list_subscribers/create', 'list_uid' => $subscriber->list->list_uid));
                }
                $this->redirect(array('list_subscribers/update', 'list_uid' => $subscriber->list->list_uid, 'subscriber_uid' => $subscriber->subscriber_uid));
            }
        }
        
        // raise event. simply the fields are shown
        $this->callbacks->onSubscriberFieldsDisplay(new CEvent($this->callbacks, array(
            'fields' => &$fields,
        ))); 
        
        // add the default sorting of fields actions and raise the event
        $this->callbacks->onSubscriberFieldsSorting = array($this->callbacks, '_orderFields');
        $this->callbacks->onSubscriberFieldsSorting(new CEvent($this->callbacks, array(
            'fields' => &$fields,
        )));
        
        // and build the html for the fields.
        $fieldsHtml = '';
        foreach ($fields as $type => $field) {
            $fieldsHtml .= $field['field_html'];
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('list_subscribers', 'Add a new subscriber to your list.'),
            'pageHeading'       => Yii::t('list_subscribers', 'Add a new subscriber to your list.'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                Yii::t('list_subscribers', 'Subscribers') => $this->createUrl('list_subscribers/index', array('list_uid' => $list->list_uid)),
                Yii::t('app', 'Create new')
            )
        ));
        
        $this->render('form', compact('fieldsHtml', 'list', 'subscriber'));
    }
    
    /**
     * Update existing list subscriber
     */
    public function actionUpdate($list_uid, $subscriber_uid)
    {
        $list       = $this->loadListModel($list_uid);
        $subscriber = $this->loadSubscriberModel($list->list_id, $subscriber_uid);

        $request = Yii::app()->request;
        $hooks = Yii::app()->hooks;
        
        $listFields = ListField::model()->findAll(array(
            'condition' => 'list_id = :lid',
            'params'    => array(':lid' => $list->list_id),
            'order'     => 'sort_order ASC'
        ));
        
        if (empty($listFields)) {
            throw new CHttpException(404, Yii::t('list', 'Your mail list does not have any field defined.'));
        }
        
        $usedTypes = array();
        foreach ($listFields as $field) {
            $usedTypes[] = $field->type->type_id;
        }
        $criteria = new CDbCriteria();
        $criteria->addInCondition('type_id', $usedTypes);
        $types = ListFieldType::model()->findAll($criteria);

        $instances = array();
        
        foreach ($types as $type) {
            
            if (empty($type->identifier) || !is_file(Yii::getPathOfAlias($type->class_alias).'.php')) {
                continue;
            }
            
            $component = Yii::app()->getWidgetFactory()->createWidget($this, $type->class_alias, array(
                'fieldType'     => $type,
                'list'          => $list,
                'subscriber'    => $subscriber,
            ));
            
            if (!($component instanceof FieldBuilderType)) {
                continue;
            }
            
            // run the component to hook into next events
            $component->run();
            
            $instances[] = $component;
        }
        
        $fields = array();
        
        // if the fields are saved
        if ($request->isPostRequest) {
            
            $transaction = Yii::app()->db->beginTransaction();
            
            try {
                
                $attributes = (array)$request->getPost($subscriber->modelName, array());
                if (empty($subscriber->ip_address)) {
                    $subscriber->ip_address = Yii::app()->request->getUserHostAddress();
                }
                if (isset($attributes['status']) && in_array($attributes['status'], array_keys($subscriber->getStatusesList()))) {
                    $subscriber->status = $attributes['status'];
                } else {
                    $subscriber->status = ListSubscriber::STATUS_UNCONFIRMED;
                }
            
                if (!$subscriber->save()) {
                    throw new Exception(Yii::t('app', 'Temporary error, please contact us if this happens too often!'));
                }
                
                // raise event
                $this->callbacks->onSubscriberSave(new CEvent($this->callbacks, array(
                    'fields' => &$fields,
                )));
                
                // if no error thrown but still there are errors in any of the instances, stop.
                foreach ($instances as $instance) {
                    if (!empty($instance->errors)) {
                        throw new Exception(Yii::t('app', 'Your form has a few errors. Please fix them and try again!'));
                    }
                }
                
                // add the default success message
                Yii::app()->notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
                
                // raise event. at this point everything seems to be fine.
                $this->callbacks->onSubscriberSaveSuccess(new CEvent($this->callbacks, array(
                    'instances'     => $instances,
                    'subscriber'    => $subscriber, 
                    'list'          => $list,
                )));

                $transaction->commit();
                
            } catch (Exception $e) {
                
                $transaction->rollBack();
                Yii::app()->notify->addError($e->getMessage());
                
                // bind default save error event handler
                $this->callbacks->onSubscriberSaveError = array($this->callbacks, '_collectAndShowErrorMessages');
                
                // raise event
                $this->callbacks->onSubscriberSaveError(new CEvent($this->callbacks, array(
                    'instances'     => $instances,
                    'subscriber'    => $subscriber, 
                    'list'          => $list
                )));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'   => $this,
                'success'      => Yii::app()->notify->hasSuccess,
                'subscriber'   => $subscriber,
            )));
            
            if ($collection->success) {
                if ($request->getPost('next_action') && $request->getPost('next_action') == 'create-new') {
                    $this->redirect(array('list_subscribers/create', 'list_uid' => $subscriber->list->list_uid));
                }
            }
        }
        
        // raise event. simply the fields are shown
        $this->callbacks->onSubscriberFieldsDisplay(new CEvent($this->callbacks, array(
            'fields' => &$fields,
        ))); 
        
        // add the default sorting of fields actions and raise the event
        $this->callbacks->onSubscriberFieldsSorting = array($this->callbacks, '_orderFields');
        $this->callbacks->onSubscriberFieldsSorting(new CEvent($this->callbacks, array(
            'fields' => &$fields,
        )));
        
        // and build the html for the fields.
        $fieldsHtml = '';
        foreach ($fields as $type => $field) {
            $fieldsHtml .= $field['field_html'];
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('list_subscribers', 'Update existing list subscriber.'), 
            'pageHeading'       => Yii::t('list_subscribers', 'Update existing list subscriber.'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                Yii::t('list_subscribers', 'Subscribers') => $this->createUrl('list_subscribers/index', array('list_uid' => $list->list_uid)),
                Yii::t('app', 'Update')
            )
        ));
        
        $this->render('form', compact('fieldsHtml', 'list', 'subscriber'));
    }
    
    /**
     * Delete existing list subscriber
     */
    public function actionDelete($list_uid, $subscriber_uid)
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $list       = $this->loadListModel($list_uid);
        $subscriber = $this->loadSubscriberModel($list->list_id, $subscriber_uid);
        
        $subscriber->delete();
        
        if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
            $logAction->subscriberDeleted($subscriber);    
        }
        
        if (!$request->isAjaxRequest) {
            $notify->addSuccess(Yii::t('list_subscribers', 'Your list subscriber was successfully deleted!'));
            $this->redirect($request->getPost('returnUrl', array('list_subscribers/index', 'list_uid' => $list->list_uid)));
        }
    }
    
    /**
     * Unsubscribe existing list subscriber
     */
    public function actionUnsubscribe($list_uid, $subscriber_uid)
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $list       = $this->loadListModel($list_uid);
        $subscriber = $this->loadSubscriberModel($list->list_id, $subscriber_uid);
        
        if ($subscriber->getCanBeUnsubscribed()) {
            $subscriber->status = ListSubscriber::STATUS_UNSUBSCRIBED;
            $subscriber->save(false);
        }

        if (!$request->isAjaxRequest) {
            $notify->addSuccess(Yii::t('list_subscribers', 'Your list subscriber was successfully unsubscribed!'));
            $this->redirect($request->getPost('returnUrl', array('list_subscribers/index', 'list_uid' => $list->list_uid)));
        }
    }
    
    /**
     * Subscribe existing list subscriber
     */
    public function actionSubscribe($list_uid, $subscriber_uid)
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $list       = $this->loadListModel($list_uid);
        $subscriber = $this->loadSubscriberModel($list->list_id, $subscriber_uid);
        $oldStatus  = $subscriber->status;
        
        if ($subscriber->getCanBeConfirmed()) {
            $subscriber->status = ListSubscriber::STATUS_CONFIRMED;
            $subscriber->save(false);
        }

        if (!$request->isAjaxRequest) {
            if ($oldStatus == ListSubscriber::STATUS_UNSUBSCRIBED) {
                $notify->addSuccess(Yii::t('list_subscribers', 'Your list unsubscriber was successfully subscribed back!'));
            } else {
                $notify->addSuccess(Yii::t('list_subscribers', 'Your list subscriber has been confirmed!'));
            }
            $this->redirect($request->getPost('returnUrl', array('list_subscribers/index', 'list_uid' => $list->list_uid)));
        }
    }
    
    /**
     * Bulk actions
     */
    public function actionBulk_action($list_uid)
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $list       = $this->loadListModel($list_uid);
        $subscriber = new ListSubscriber();
        $action     = $request->getPost('action');
        
        if (!in_array($action, array_keys($subscriber->getBulkActionsList()))) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        set_time_limit(0);
        
        $selectedSubscribers = (array)$request->getPost('bulk_select', array());
        $selectedSubscribers = array_values($selectedSubscribers);
        $selectedSubscribers = array_map('intval', $selectedSubscribers);
        
        if (!empty($selectedSubscribers)) {
            $criteria = new CDbCriteria();
            $criteria->compare('list_id', (int)$list->list_id);
            $criteria->addInCondition('subscriber_id', $selectedSubscribers);
            
            if ($action == ListSubscriber::BULK_SUBSCRIBE) {
                
                $criteria->addNotInCondition('status', array(ListSubscriber::STATUS_BLACKLISTED));
                
                ListSubscriber::model()->updateAll(array(
                    'status'        => ListSubscriber::STATUS_CONFIRMED,
                    'last_updated'  => new CDbExpression('NOW()'),
                ), $criteria);
            
            } elseif ($action == ListSubscriber::BULK_UNSUBSCRIBE) {
                
                $criteria->addNotInCondition('status', array(ListSubscriber::STATUS_BLACKLISTED));
                
                ListSubscriber::model()->updateAll(array(
                    'status'        => ListSubscriber::STATUS_UNSUBSCRIBED,
                    'last_updated'  => new CDbExpression('NOW()'),
                ), $criteria);
                
            } elseif ($action == ListSubscriber::BULK_DELETE) {
                
               ListSubscriber::model()->deleteAll($criteria);
                
            }
        }

        if (!$request->isAjaxRequest) {
            $notify->addSuccess(Yii::t('app', 'Bulk action completed successfully!'));
            $this->redirect($request->getPost('returnUrl', array('list_subscribers/index', 'list_uid' => $list->list_uid)));
        }
    }
    
    public function actionBulk_from_source($list_uid)
    {
        set_time_limit(0);
        
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $ioFilter   = Yii::app()->ioFilter;
        $list       = $this->loadListModel($list_uid);
        $model      = new ListSubscriberBulkFromSource();
        $redirect   = array('list_subscribers/index', 'list_uid' => $list_uid);
        
        $emailAddresses    = array();
        $model->attributes = (array)$request->getPost($model->modelName, array());
        
        if (!in_array($model->status, array_keys($model->getBulkActionsList()))) {
            $this->redirect($redirect);
        }

        if (!empty($model->bulk_from_text)) {
            $lines = explode("\n", $model->bulk_from_text);
            foreach ($lines as $line) {
                $emails = explode(',', $line);
                $emails = array_map('trim', $emails);
                foreach ($emails as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $emailAddresses[] = $email;
                    }
                } 
            }
        }
        $emailAddresses = array_unique($emailAddresses);
        
        $model->bulk_from_file = CUploadedFile::getInstance($model, 'bulk_from_file');
        if (!empty($model->bulk_from_file)) {
            if (!$model->validate()) {
                $notify->addError($model->shortErrors->getAllAsString());
            } else {
                $file = new SplFileObject($model->bulk_from_file->tempName);
                $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE | SplFileObject::READ_AHEAD);
                while (!$file->eof()) {
                    $row = $file->fgetcsv();
                    if (empty($row)) {
                        continue;
                    }
                    $row = $ioFilter->stripTags($ioFilter->xssClean($row));
                    foreach ($row as $value) {
                        if (empty($value)) {
                            continue;
                        }
                        $emails = explode(',', $value);
                        $emails = array_map('trim', $emails);
                        foreach ($emails as $email) {
                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $emailAddresses[] = $email;
                            }
                        }
                    }
                }    
            }
        }
        $emailAddresses = array_unique($emailAddresses);
        
        $total = 0;
        while (!empty($emailAddresses)) {
            $emails = array_splice($emailAddresses, 0, 10);
            
            $criteria = new CDbCriteria();
            $criteria->compare('list_id', (int)$list->list_id);
            $criteria->addInCondition('email', $emails);
            
            if ($model->status == ListSubscriber::BULK_SUBSCRIBE) {
                
                $criteria->addNotInCondition('status', array(ListSubscriber::STATUS_BLACKLISTED));
                
                ListSubscriber::model()->updateAll(array(
                    'status'        => ListSubscriber::STATUS_CONFIRMED,
                    'last_updated'  => new CDbExpression('NOW()'),
                ), $criteria);
            
            } elseif ($model->status == ListSubscriber::BULK_UNSUBSCRIBE) {
                
                $criteria->addNotInCondition('status', array(ListSubscriber::STATUS_BLACKLISTED));
                
                ListSubscriber::model()->updateAll(array(
                    'status'        => ListSubscriber::STATUS_UNSUBSCRIBED,
                    'last_updated'  => new CDbExpression('NOW()'),
                ), $criteria);
                
            } elseif ($model->status == ListSubscriber::BULK_DELETE) {
                
               ListSubscriber::model()->deleteAll($criteria);
                
            }

            $total += count($emails);
        }
        $notify->addSuccess(Yii::t('list_subscribers', 'Action completed, {count} subscribers were affected!', array(
            '{count}'   => $total,
        )));
        
        $this->redirect($redirect);
    }
    
    /**
     * Helper method to load the list AR model
     */
    public function loadListModel($list_uid)
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
    
    /**
     * Helper method to load the list subscriber AR model
     */
    public function loadSubscriberModel($list_id, $subscriber_uid)
    {
        $model = ListSubscriber::model()->findByAttributes(array(
            'subscriber_uid'    => $subscriber_uid,
            'list_id'           => (int)$list_id,
        ));
        
        if ($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        return $model;
    }
}