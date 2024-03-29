<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * List_segmentsController
 * 
 * Handles the actions for list segments related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class List_segmentsController extends Controller
{
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('segments.js')));
        parent::init();
    }
    
    /**
     * Define the filters for various controller actions
     * Merge the filters with the ones from parent implementation
     */
    public function filters()
    {
        return CMap::mergeArray(array(
            'postOnly + delete',
        ), parent::filters());
    }
    
    /**
     * List available segments
     */
    public function actionIndex($list_uid)
    {
        $list = $this->loadListModel($list_uid);
        $request = Yii::app()->request;
        
        $segment = new ListSegment('search');
        $segment->attributes = (array)$request->getQuery($segment->modelName, array());
        $segment->list_id = $list->list_id;

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('list_segments', 'Your mail list segments'), 
            'pageHeading'       => Yii::t('list_segments', 'List segments'), 
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                Yii::t('list_segments', ' List segments') => $this->createUrl('list_segments/index', array('list_uid' => $list->list_uid)),
                Yii::t('app', 'View all')
            )
        ));
        
        $this->render('list', compact('list', 'segment'));
    }
    
    /**
     * Create a new segment
     */
    public function actionCreate($list_uid)
    {
        $list       = $this->loadListModel($list_uid);
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        
        $segment = new ListSegment();
        $segment->list_id = $list->list_id;

        $condition  = new ListSegmentCondition();
        $conditions = array();
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($segment->modelName, array()))) {
            $postConditions = (array)$request->getPost($condition->modelName, array());
            if (!empty($postConditions)) {
                $hashedConditions = array();
                foreach ($postConditions as $index => $conditionAttributes) {
                    $cond = new ListSegmentCondition();
                    $cond->attributes = $conditionAttributes;
                    
                    $hashKey = sha1($cond->field_id.$cond->operator_id.$cond->value);
                    if (isset($hashedConditions[$hashKey])) {
                        continue;
                    }
                    $hashedConditions[$hashKey] = true;
                    
                    $conditions[] = $cond;
                }
            }
            $segment->attributes = $attributes;
            $transaction = Yii::app()->db->beginTransaction();
            try {
                if (!$segment->save()) {
                    throw new Exception(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                }
                
                $conditionError = false;
                foreach ($conditions as $cond) {
                    $cond->segment_id = $segment->segment_id;
                    $cond->fieldDecorator->onHtmlOptionsSetup = array($this, '_addInputErrorClass');
                    if (!$cond->save()) {
                        $conditionError = true;
                    }
                }
                if ($conditionError) {
                    throw new Exception(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                }
                
                $transaction->commit();
                
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->segmentCreated($segment);    
                }
                
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            } catch (Exception $e) {
                $notify->addError($e->getMessage());
                $transaction->rollBack();
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'list'      => $list,
                'segment'   => $segment,
            )));
            
            if ($collection->success) {
                $this->redirect(array('list_segments/update', 'list_uid' => $list->list_uid, 'segment_uid' => $segment->segment_uid));
            }
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('list_segments', 'Your mail list segments'), 
            'pageHeading'       => Yii::t('list_segments', 'Create a new list segment'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                Yii::t('list_segments', 'Segments') => $this->createUrl('list_segments/index', array('list_uid' => $list->list_uid)),
                Yii::t('app', 'Create')
            )
        ));
        
        $this->render('form', compact('list', 'segment', 'condition', 'conditions'));
    }
    
    /**
     * Update existing segment
     */
    public function actionUpdate($list_uid, $segment_uid)
    {
        $list       = $this->loadListModel($list_uid);
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        
        $segment = ListSegment::model()->findByAttributes(array(
            'segment_uid'   => $segment_uid,
            'list_id'       => $list->list_id,
        ));
        
        if (empty($segment)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $condition = new ListSegmentCondition();
        $conditions = ListSegmentCondition::model()->findAllByAttributes(array(
            'segment_id' => $segment->segment_id,
        ));
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($segment->modelName, array()))) {
            $postConditions = (array)$request->getPost($condition->modelName, array());
            if (!empty($postConditions)) {
                $conditions = array();
                $hashedConditions = array();
                foreach ($postConditions as $index => $conditionAttributes) {
                    $cond = new ListSegmentCondition();
                    $cond->attributes = $conditionAttributes;
                    
                    $hashKey = sha1($cond->field_id.$cond->operator_id.$cond->value);
                    if (isset($hashedConditions[$hashKey])) {
                        continue;
                    }
                    $hashedConditions[$hashKey] = true;
                    
                    $conditions[] = $cond;
                }
            }
            $segment->attributes = $attributes;
            $transaction = Yii::app()->db->beginTransaction();
            try {
                if (!$segment->save()) {
                    throw new Exception(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                }
                
                ListSegmentCondition::model()->deleteAllByAttributes(array(
                    'segment_id' => $segment->segment_id,
                ));
                
                $conditionError = false;
                foreach ($conditions as $cond) {
                    $cond->segment_id = $segment->segment_id;
                    $cond->fieldDecorator->onHtmlOptionsSetup = array($this, '_addInputErrorClass');
                    if (!$cond->save()) {
                        $conditionError = true;
                    }
                }
                if ($conditionError) {
                    throw new Exception(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                }
                
                $transaction->commit();
                
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->segmentUpdated($segment);    
                }
                
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            } catch (Exception $e) {
                $notify->addError($e->getMessage());
                $transaction->rollBack();
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'list'      => $list,
                'segment'   => $segment,
            )));
            
            if ($collection->success) {
                $this->redirect(array('list_segments/update', 'list_uid' => $list->list_uid, 'segment_uid' => $segment->segment_uid));
            }
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('list_segments', 'Your mail list segments'),
            'pageHeading'       => Yii::t('list_segments', 'Update list segment'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => $this->createUrl('lists/index'),
                $list->name => $this->createUrl('lists/overview', array('list_uid' => $list->list_uid)),
                Yii::t('list_segments', 'Segments') => $this->createUrl('list_segments/index', array('list_uid' => $list->list_uid)),
                Yii::t('app', 'Update')
            )
        ));
        
        $this->render('form', compact('list', 'segment', 'condition', 'conditions'));
    }
    
    /**
     * Show subscribers from belonging to a segment
     */
    public function actionSubscribers($list_uid, $segment_uid)
    {
        $request = Yii::app()->request;
        
        if (!$request->isAjaxRequest) {
            $this->redirect(array('lists/index'));
        }
        
        $list = $this->loadListModel($list_uid);
        
        $segment = ListSegment::model()->findByAttributes(array(
            'segment_uid'    => $segment_uid,
            'list_id'        => $list->list_id,
        ));
        
        if (empty($segment)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $count = $segment->countSubscribers();
        
        $pages = new CPagination($count);
        $pages->pageSize = (int)$segment->paginationOptions->getPageSize();

        $subscribers = $segment->findSubscribers($pages->getOffset(), $pages->getLimit());

        $columns = array();
        $rows = array();
        
        $criteria = new CDbCriteria();
        $criteria->compare('t.list_id', $list->list_id);
        $criteria->order = 't.sort_order ASC';
        
        $fields = ListField::model()->findAll($criteria);
    
        foreach ($fields as $field) {
            $columns[] = array(
                'label'     => $field->label,
                'field_id'  => $field->field_id,
                'value'     => isset($filters[$field->field_id]) ? CHtml::encode($filters[$field->field_id]) : null,
            );
        }
        
        foreach ($subscribers as $index => $subscriber) {
            $subscriberRow = array('columns' => array());
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
        
        return $this->renderPartial('_subscribers', compact('list', 'columns', 'rows', 'pages', 'count'));
    }
    
    /**
     * Delete existing segment
     */
    public function actionDelete($list_uid, $segment_uid)
    {
        $list = $this->loadListModel($list_uid);
        
        $segment = ListSegment::model()->findByAttributes(array(
            'segment_uid'   => $segment_uid,
            'list_id'       => $list->list_id,
        ));
        
        if (empty($segment)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $segment->delete();
        
        if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
            $logAction->segmentDeleted($segment);    
        }
                
        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('list_segments', 'Your list segment was successfully deleted!'));
            $this->redirect($request->getPost('returnUrl', array('list_segments/index', 'list_uid' => $list->list_uid)));
        }
    }
    
    /**
     * Callback method to add attribute error class to the AR model
     */
    public function _addInputErrorClass(CEvent $event)
    {
        if ($event->sender->owner->hasErrors($event->params['attribute'])) {
            $event->params['htmlOptions']['class'] .= ' error';
        }
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
}