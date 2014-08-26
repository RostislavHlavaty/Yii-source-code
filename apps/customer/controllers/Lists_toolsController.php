<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Lists_toolsController
 * 
 * Handles the actions for lists related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.5
 */
 
class Lists_toolsController extends Controller
{
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('lists-tools.js')));
        parent::init();
    }
    
    /**
     * Display list available tools
     */
    public function actionIndex()
    {
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('lists', 'Tools'),
            'pageHeading'       => Yii::t('lists', 'Tools'),
            'pageBreadcrumbs'   => array(
                Yii::t('lists', 'Lists') => array('lists/index'),
                Yii::t('lists', 'Tools')
            )
        ));
        
        $options   = Yii::app()->options;
        $customer  = Yii::app()->customer->getModel();
        $syncTool  = new ListsSyncTool();
        $syncTool->customer_id = $customer->customer_id;
        
        $this->render('index', compact('syncTool'));
    }
    
    public function actionSync()
    {
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        
        if (!$request->isPostRequest) {
            $this->redirect(array('lists_tools/index'));
        }
        
        $customer = Yii::app()->customer->getModel();
        $syncTool = new ListsSyncTool();
        $syncTool->attributes = (array)$request->getPost($syncTool->modelName, array());
        $syncTool->customer_id = $customer->customer_id;
        
        if (!$syncTool->validate()) {
            $message = Yii::t('lists', 'Unable to validate your sync data!');
            if ($request->isAjaxRequest) {
                $syncTool->progress_text = $message;
                $syncTool->finished      = 1;
                return $this->renderJson(array(
                    'attributes'           => $syncTool->attributes,
                    'formatted_attributes' => $syncTool->getFormattedAttributes(),
                ));
            }
            $notify->addError($message);
            $this->redirect(array('lists_tools/index'));
        }
        
        if ($syncTool->primary_list_id == $syncTool->secondary_list_id) {
            $message = Yii::t('lists', 'The primary list and the secondary list cannot be the same!');
            if ($request->isAjaxRequest) {
                if ($request->isAjaxRequest) {
                    $syncTool->progress_text = $message;
                    $syncTool->finished      = 1;
                    return $this->renderJson(array(
                        'attributes'           => $syncTool->attributes,
                        'formatted_attributes' => $syncTool->getFormattedAttributes(),
                    ));
                }
            }
            $notify->addError($message);
            $this->redirect(array('lists_tools/index'));
        }
        
        $noAction = empty($syncTool->missing_subscribers_action);
        $noAction = $noAction && empty($syncTool->distinct_status_action);
        $noAction = $noAction && empty($syncTool->duplicate_subscribers_action);
        if ($noAction) {
            $message = Yii::t('lists', 'You need to select an action against one of the lists subscribers!');
            if ($request->isAjaxRequest) {
                if ($request->isAjaxRequest) {
                    $syncTool->progress_text = $message;
                    $syncTool->finished      = 1;
                    return $this->renderJson(array(
                        'attributes'           => $syncTool->attributes,
                        'formatted_attributes' => $syncTool->getFormattedAttributes(),
                    ));
                }
            }
            $notify->addError($message);
            $this->redirect(array('lists_tools/index'));
        }
        
        $primaryList = $syncTool->getPrimaryList();
        if (empty($primaryList)) {
            $message = Yii::t('lists', 'The primary list cannot be found!');
            if ($request->isAjaxRequest) {
                if ($request->isAjaxRequest) {
                    $syncTool->progress_text = $message;
                    $syncTool->finished      = 1;
                    return $this->renderJson(array(
                        'attributes'           => $syncTool->attributes,
                        'formatted_attributes' => $syncTool->getFormattedAttributes(),
                    ));
                }
            }
            $notify->addError($message);
            $this->redirect(array('lists_tools/index'));
        }
        
        $secondaryList = $syncTool->getSecondaryList();
        if (empty($secondaryList)) {
            $message = Yii::t('lists', 'The secondary list cannot be found!');
            if ($request->isAjaxRequest) {
                if ($request->isAjaxRequest) {
                    $syncTool->progress_text = $message;
                    $syncTool->finished      = 1;
                    return $this->renderJson(array(
                        'attributes'           => $syncTool->attributes,
                        'formatted_attributes' => $syncTool->getFormattedAttributes(),
                    ));
                }
            }
            $notify->addError($message);
            $this->redirect(array('lists_tools/index'));
        }

        if ($memoryLimit = $customer->getGroupOption('lists.copy_subscribers_memory_limit')) { 
            ini_set('memory_limit', $memoryLimit);
        }
        
        $syncTool->count  = $primaryList->subscribersCount;
        $syncTool->limit  = (int)$customer->getGroupOption('lists.copy_subscribers_at_once', 100);

        $jsonAttributes = CJSON::encode(array(
            'attributes'           => $syncTool->attributes,
            'formatted_attributes' => $syncTool->getFormattedAttributes(),
        ));
        
        if (!$request->isAjaxRequest) {
            $this->setData(array(
                'pageMetaTitle'     => $this->data->pageMetaTitle.' | '.Yii::t('lists', 'Sync lists'), 
                'pageHeading'       => Yii::t('lists', 'Sync lists'), 
                'pageBreadcrumbs'   => array(
                    Yii::t('lists', 'Tools') => $this->createUrl('tools/index'),
                    Yii::t('lists', 'Sync "{primary}" list with "{secondary}" list', array('{primary}' => $primaryList->name, '{secondary}' => $secondaryList->name)),
                ),
                'fromText' => Yii::t('lists', 'Sync "{primary}" list with "{secondary}" list', array('{primary}' => $primaryList->name, '{secondary}' => $secondaryList->name)),
            ));
            return $this->render('sync-lists', compact('syncTool', 'jsonAttributes'));
        }
        
        $criteria = new CDbCriteria();
        $criteria->compare('list_id', (int)$primaryList->list_id);
        $criteria->limit  = $syncTool->limit;
        $criteria->offset = $syncTool->offset;
        $subscribers = ListSubscriber::model()->findAll($criteria);
        
        if (empty($subscribers)) {
            $syncTool->progress_text = Yii::t('lists', 'The sync process is done.');
            $syncTool->finished      = 1;
            return $this->renderJson(array(
                'attributes'           => $syncTool->attributes,
                'formatted_attributes' => $syncTool->getFormattedAttributes(),
            ));
        }

        $syncTool->progress_text = Yii::t('lists', 'The sync process is running, please wait...');
        $syncTool->finished      = 0;
        
        $transaction = Yii::app()->getDb()->beginTransaction();

        try {
            
            foreach ($subscribers as $subscriber) {
                $syncTool->processed_total++;
                $syncTool->processed_success++;
                
                $exists = ListSubscriber::model()->findByAttributes(array(
                    'list_id' => $secondaryList->list_id,
                    'email'   => $subscriber->email,
                ));
                
                if (empty($exists)) {
                    if ($syncTool->missing_subscribers_action == ListsSyncTool::MISSING_SUBSCRIBER_ACTION_CREATE_SECONDARY) {
                        $copy = $subscriber->copyToList($secondaryList->list_id, false);
                        continue;
                    }
                }
                
                if (!empty($exists)) {
                    if ($syncTool->duplicate_subscribers_action == ListsSyncTool::DUPLICATE_SUBSCRIBER_ACTION_DELETE_SECONDARY) {
                        $exists->delete();
                        continue;
                    }
                }
                
                if (!empty($exists) && $subscriber->status != $exists->status) {
                    if ($syncTool->distinct_status_action == ListsSyncTool::DISTINCT_STATUS_ACTION_UPDATE_PRIMARY) {
                        $subscriber->status = $exists->status;
                        $subscriber->save(false);
                        continue;
                    }
                    if ($syncTool->distinct_status_action == ListsSyncTool::DISTINCT_STATUS_ACTION_UPDATE_SECONDARY) {
                        $exists->status = $subscriber->status;
                        $exists->save(false);
                        continue;
                    }
                    if ($syncTool->distinct_status_action == ListsSyncTool::DISTINCT_STATUS_ACTION_DELETE_SECONDARY) {
                        $exists->delete();
                        continue;
                    }
                }
            }    
            
            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
        }

        $syncTool->percentage  = round((($syncTool->processed_total / $syncTool->count) * 100), 2);
        $syncTool->offset += $syncTool->limit;
     
        return $this->renderJson(array(
            'attributes'           => $syncTool->attributes,
            'formatted_attributes' => $syncTool->getFormattedAttributes(),
        ));
    }
}