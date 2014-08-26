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
    public $layout = 'thin';
    
    public function behaviors()
    {
        return CMap::mergeArray(array(
            'callbacks' => array(
                'class' => 'frontend.components.behaviors.ListControllerCallbacksBehavior',
            ),
        ), parent::behaviors());
    }
    
    /**
     * Subscribe a new user to a certain email list
     */
    public function actionSubscribe($list_uid, $subscriber_uid = null)
    {
        $list = $this->loadListModel($list_uid);
        
        if (!empty($list->customer)) {
            $this->setCustomerLanguage($list->customer);
        }
        
        $pageType = $this->loadPageTypeModel('subscribe-form');
        $page     = $this->loadPageModel($list->list_id, $pageType->type_id);
        
        $content = !empty($page->content) ? $page->content : $pageType->content;
        $content = CHtml::decode($content);
        
        // list name
        $content = str_replace('[LIST_NAME]', CHtml::encode($list->name), $content);
        
        // submit button
        $content = str_replace('[SUBMIT_BUTTON]', CHtml::button(Yii::t('lists', 'Subscribe'), array('type' => 'submit', 'class' => 'btn btn-default')), $content);
        
        // load the list fields and bind the behavior.
        $listFields = ListField::model()->findAll(array(
            'condition' => 'list_id = :lid',
            'params'    => array(':lid' => (int)$list->list_id),
            'order'     => 'sort_order ASC'
        ));
        
        if (empty($listFields)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $request = Yii::app()->request;
        $hooks   = Yii::app()->hooks;
        
        if (!empty($subscriber_uid)) {
            $_subscriber = $this->loadSubscriberModel($subscriber_uid, $list->list_id);
            if (!empty($_subscriber) && $_subscriber->status == ListSubscriber::STATUS_UNSUBSCRIBED) {
                $subscriber = $_subscriber;
            } else {
                $_subscriber = null;
            }
        }
        if (empty($subscriber)) {
            $subscriber = new ListSubscriber();
        }
        $subscriber->list_id = $list->list_id;
        $subscriber->ip_address = Yii::app()->request->getUserHostAddress();

        $usedTypes = array();
        foreach ($listFields as $field) {
            $usedTypes[] = (int)$field->type->type_id;
        }
        
        $criteria = new CDbCriteria();
        $criteria->addInCondition('type_id', $usedTypes);
        $listFieldTypes = ListFieldType::model()->findAll($criteria);
        $instances = array();
        
        foreach ($listFieldTypes as $fieldType) {
            
            if (empty($fieldType->identifier) || !is_file(Yii::getPathOfAlias($fieldType->class_alias).'.php')) {
                continue;
            }
            
            $component = Yii::app()->getWidgetFactory()->createWidget($this, $fieldType->class_alias, array(
                'fieldType'     => $fieldType,
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
                            throw new Exception(Yii::t('lists', 'The maximum number of allowed subscribers has been reached.'));
                        }    
                    }
                    
                    if ($maxSubscribersPerList > -1) {
                        $criteria->compare('list.list_id', (int)$list->list_id);
                        $listSubscribersCount = ListSubscriber::model()->count($criteria);
                        if ($listSubscribersCount >= $maxSubscribersPerList) {
                            throw new Exception(Yii::t('lists', 'The maximum number of allowed subscribers for this list has been reached.'));
                        }
                    }
                }
                
                // only if this isn't a subscriber that re-subscribes and it is a double optin
                if (empty($_subscriber) && $list->opt_in == Lists::OPT_IN_DOUBLE) {
                    // bind the event handler that will send the confirm email once the subscriber is saved.
                    $this->callbacks->onSubscriberSaveSuccess = array($this->callbacks, '_sendSubscribeConfirmationEmail');    
                }
                
                if (!$subscriber->save()) {
                    throw new Exception(Yii::t('app', 'Temporary error, please contact us if this happens too often!'));
                }
                
                // raise event
                $this->callbacks->onSubscriberSave(new CEvent($this->callbacks, array(
                    'fields' => &$fields,
                    'action' => 'subscribe',
                )));
                
                // if no exception thrown but still there are errors in any of the instances, stop.
                foreach ($instances as $instance) {
                    if (!empty($instance->errors)) {
                        throw new Exception(Yii::t('app', 'Your form has a few errors. Please fix them and try again!'));
                    }
                }
                
                // raise event. at this point everything seems to be fine.
                $this->callbacks->onSubscriberSaveSuccess(new CEvent($this->callbacks, array(
                    'instances'     => $instances,
                    'subscriber'    => $subscriber, 
                    'list'          => $list,
                    'action'        => 'subscribe',
                )));

                $transaction->commit();
                
                if (!empty($_subscriber)) {
                    $subscriber->status = ListSubscriber::STATUS_UNCONFIRMED;
                    $subscriber->save(false);
                    $this->redirect(array('lists/subscribe_confirm', 'list_uid' => $list->list_uid, 'subscriber_uid' => $subscriber->subscriber_uid, 'do' => 'subscribe-back'));
                }
                
                // is single opt in.
                if ($list->opt_in == Lists::OPT_IN_SINGLE) {
                    $this->redirect(array('lists/subscribe_confirm', 'list_uid' => $list->list_uid, 'subscriber_uid' => $subscriber->subscriber_uid));
                }
                
                $this->redirect(array('lists/subscribe_pending', 'list_uid' => $list->list_uid));
                
            } catch (Exception $e) {
                
                $transaction->rollBack();
                Yii::app()->notify->addError($e->getMessage());
                
                // bind default save error event handler
                $this->callbacks->onSubscriberSaveError = array($this->callbacks, '_collectAndShowErrorMessages');
                
                // raise event
                $this->callbacks->onSubscriberSaveError(new CEvent($this->callbacks, array(
                    'instances'     => $instances,
                    'subscriber'    => $subscriber, 
                    'list'          => $list,
                    'action'        => 'subscribe',
                )));
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
        
        // list fields transform and handling
        $content = preg_replace('/\[LIST_FIELDS\]/', $fieldsHtml, $content, 1, $count);
        
        // embed output
        if ($request->getQuery('output') == 'embed') {
            $attributes = array(
                'width'     => (int)$request->getQuery('width', 400),
                'height'    => (int)$request->getQuery('height', 400),
            );
            $this->layout = 'embed';
            $this->setData('attributes', $attributes);
        }
        
        $this->render('display_content', compact('content'));
    }
    
    /**
     * This page is shown after the user has submitted the subscription form
     */
    public function actionSubscribe_pending($list_uid)
    {
        $list = $this->loadListModel($list_uid);
        
        if (!empty($list->customer)) {
            $this->setCustomerLanguage($list->customer);
        }
        
        $pageType = $this->loadPageTypeModel('subscribe-pending');
        $page     = $this->loadPageModel($list->list_id, $pageType->type_id);

        $content = !empty($page->content) ? $page->content : $pageType->content;
        $content = CHtml::decode($content);
        
        // add the list name
        $content = str_replace('[LIST_NAME]', CHtml::encode($list->name), $content);

        $this->render('display_content', compact('content'));
    }
    
    /**
     * This pages is shown when the user clicks on the confirmation email that he received
     */
    public function actionSubscribe_confirm($list_uid, $subscriber_uid, $do = null)
    {
        $list = $this->loadListModel($list_uid);
        
        if (!empty($list->customer)) {
            $this->setCustomerLanguage($list->customer);
        }
        
        $pageType   = $this->loadPageTypeModel('subscribe-confirm');
        $page       = $this->loadPageModel($list->list_id, $pageType->type_id);
        $subscriber = $this->loadSubscriberModel($subscriber_uid, $list->list_id);
        $options    = Yii::app()->options;
        
        // update profile link
        $updateProfileUrl = $this->createUrl('lists/update_profile', array('list_uid' => $list->list_uid, 'subscriber_uid' => $subscriber->subscriber_uid));
        
        // if confirmed, redirect to update profile.
        if ($subscriber->status == ListSubscriber::STATUS_CONFIRMED) {
            $this->redirect($updateProfileUrl);
        }
        
        if ($subscriber->status != ListSubscriber::STATUS_UNCONFIRMED) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $subscriber->status = ListSubscriber::STATUS_CONFIRMED;
        $saved = $subscriber->save(false);

        $content = !empty($page->content) ? $page->content : $pageType->content;
        $content = CHtml::decode($content);
        
        // add the list name
        $content = str_replace('[LIST_NAME]', CHtml::encode($list->name), $content);
        
        // add update profile url
        $content = str_replace('[UPDATE_PROFILE_URL]', $updateProfileUrl, $content);
        
        if ($do != 'subscribe-back') {
            if (Yii::app()->options->get('system.customer.action_logging_enabled', true)) {
                $customer = $list->customer;
                $customer->attachBehavior('logAction', array(
                    'class' => 'customer.components.behaviors.CustomerActionLogBehavior'
                ));
                $customer->logAction->subscriberCreated($subscriber);
            }
            
            if ($list->customerNotification->subscribe == ListCustomerNotification::TEXT_YES && !empty($list->customerNotification->subscribe_to) && ($server = DeliveryServer::pickServer(0, $list))) {
                $emailTemplate = $options->get('system.email_templates.common');
                $emailBody = $this->renderPartial('_email-subscriber-created', compact('list', 'subscriber'), true);
                $emailTemplate = str_replace('[CONTENT]', $emailBody, $emailTemplate);
    
                $params = array (
                    'to'        => array($list->customerNotification->subscribe_to => $customer->getFullName()),
                    'fromName'  => $list->default->from_name,
                    'subject'   => Yii::t('lists', 'New list subscriber!'),
                    'body'      => $emailTemplate, 
                );
                
                $server->setDeliveryFor(DeliveryServer::DELIVERY_FOR_LIST)->setDeliveryObject($list)->sendEmail($params);
            }    
        
        } else {
            
            // since it subscribes again, it makes sense to remove from unsubscribes logs for any campaign.
            CampaignTrackUnsubscribe::model()->deleteAllByAttributes(array(
                'subscriber_id' => (int)$subscriber->subscriber_id,
            ));
        }
        
        if ($saved) {
            // raise event.
            $this->callbacks->onSubscriberSaveSuccess(new CEvent($this->callbacks, array(
                'subscriber'    => $subscriber, 
                'list'          => $list,
                'action'        => 'subscribe-confirm',
                'do'            => $do,
            )));
        }  

        $this->render('display_content', compact('content'));
    }
    
    /**
     * Allows a subscriber to update his profile
     */
    public function actionUpdate_profile($list_uid, $subscriber_uid)
    {
        $list = $this->loadListModel($list_uid);
        
        if (!empty($list->customer)) {
            $this->setCustomerLanguage($list->customer);
        }
        
        $pageType   = $this->loadPageTypeModel('update-profile');
        $page       = $this->loadPageModel($list->list_id, $pageType->type_id);
        $subscriber = $this->loadSubscriberModel($subscriber_uid, $list->list_id);
        
        if ($subscriber->status != ListSubscriber::STATUS_CONFIRMED) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $subscriber->list_id    = $list->list_id;
        $subscriber->ip_address = Yii::app()->request->getUserHostAddress();

        $content = !empty($page->content) ? $page->content : $pageType->content;
        $content = CHtml::decode($content);
                
        // list name
        $content = str_replace('[LIST_NAME]', CHtml::encode($list->name), $content);
        
        // submit button
        $content = str_replace('[SUBMIT_BUTTON]', CHtml::button(Yii::t('lists', 'Update profile'), array('type' => 'submit', 'class' => 'btn btn-default')), $content);
        
        // load the list fields and bind the behavior.
        $listFields = ListField::model()->findAll(array(
            'condition' => 'list_id = :lid',
            'params'    => array(':lid' => $list->list_id),
            'order'     => 'sort_order asc'
        ));
        
        if (empty($listFields)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $request = Yii::app()->request;
        $hooks   = Yii::app()->hooks;
        
        $usedTypes = array();
        foreach ($listFields as $listField) {
            $usedTypes[] = $listField->type->type_id;
        }
        $criteria = new CDbCriteria();
        $criteria->addInCondition('type_id', $usedTypes);
        $fieldTypes = ListFieldType::model()->findAll($criteria);
        
        $instances = array();
        
        foreach ($fieldTypes as $fieldType) {
            
            if (empty($fieldType->identifier) || !is_file(Yii::getPathOfAlias($fieldType->class_alias).'.php')) {
                continue;
            }
            
            $component = Yii::app()->getWidgetFactory()->createWidget($this, $fieldType->class_alias, array(
                'fieldType'     => $fieldType,
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

                if (!$subscriber->save()) {
                    throw new Exception(Yii::t('app', 'Temporary error, please contact us if this happens too often!'));
                }
                
                // raise event
                $this->callbacks->onSubscriberSave(new CEvent($this->callbacks, array(
                    'fields' => &$fields,
                    'action' => 'update-profile',
                )));
                
                // if no exception thrown but still there are errors in any of the instances, stop.
                foreach ($instances as $instance) {
                    if (!empty($instance->errors)) {
                        throw new Exception(Yii::t('app', 'Your form has a few errors. Please fix them and try again!'));
                    }
                }
                
                // bind the default actions for sucess update
                $this->callbacks->onSubscriberSaveSuccess = array($this->callbacks, '_profileUpdatedSuccessfully'); 
                
                // raise event. at this point everything seems to be fine.
                $this->callbacks->onSubscriberSaveSuccess(new CEvent($this->callbacks, array(
                    'instances'     => $instances,
                    'subscriber'    => $subscriber, 
                    'list'          => $list,
                    'action'        => 'update-profile',
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
                    'list'          => $list,
                    'action'        => 'update-profile',
                )));
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

        // list fields transform and handling
        $content = preg_replace('/\[LIST_FIELDS\]/', $fieldsHtml, $content, 1, $count);

        $this->render('display_content', compact('content'));
    }
    
    /**
     * Allows a subscriber to unsubscribe from a list
     */
    public function actionUnsubscribe($list_uid, $subscriber_uid = null, $campaign_uid = null)
    {
        $list = $this->loadListModel($list_uid);
        
        if (!empty($list->customer)) {
            $this->setCustomerLanguage($list->customer);
        }
        
        $pageType = $this->loadPageTypeModel('unsubscribe-form');
        $page     = $this->loadPageModel($list->list_id, $pageType->type_id);
        
        $content = !empty($page->content) ? $page->content : $pageType->content;
        $content = CHtml::decode($content);
        
        // list name
        $content = str_replace('[LIST_NAME]', CHtml::encode($list->name), $content);
        
        // submit button
        $content = str_replace('[SUBMIT_BUTTON]', CHtml::button(Yii::t('lists', 'Unsubscribe'), array('type' => 'submit', 'class' => 'btn btn-default')), $content);
        
        $_subscriber = $_campaign = null;
        
        if (!empty($subscriber_uid)) {
            $_subscriber = ListSubscriber::model()->findByAttributes(array(
                'subscriber_uid'    => $subscriber_uid,
                'list_id'           => (int)$list->list_id,
                'status'            => ListSubscriber::STATUS_CONFIRMED,
            ));
        }
        
        if (!empty($campaign_uid)) {
            $_campaign = Campaign::model()->findByAttributes(array(
                'campaign_uid'  => $campaign_uid,
                'list_id'       => (int)$list->list_id,
            ));
        }
        
        $subscriber = new ListSubscriber();
        
        $this->data->list           = $list;
        $this->data->subscriber     = $subscriber;
        $this->data->_subscriber    = $_subscriber;
        $this->data->_campaign      = $_campaign;
        
        $subscriber->onRules = array($this->callbacks, '_addUnsubscribeEmailValidationRules');
        $subscriber->onAfterValidate = array($this->callbacks, '_unsubscribeAfterValidate');
        
        $request = Yii::app()->request;
        $hooks = Yii::app()->hooks;
        
        if ($request->isPostRequest && !isset($_POST[$subscriber->modelName]) && isset($_POST['EMAIL'])) {
            $_POST[$subscriber->modelName]['email'] = $request->getPost('EMAIL');
        }
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($subscriber->modelName, array()))) {
            $subscriber->attributes = $attributes;
            $subscriber->validate();
        } elseif (!$request->isPostRequest && !empty($_subscriber)) {
            $subscriber->email = $_subscriber->email;
            // $subscriber->validate(); // do not auto validate for now
        }

        // input field
        $inputField = $this->renderPartial('_unsubscribe-input', compact('subscriber'), true);
        $content = str_replace('[UNSUBSCRIBE_EMAIL_FIELD]', $inputField, $content);
        
        // avoid a nasty bug with model input array
        $content = preg_replace('/(ListSubscriber)(\[)([a-zA-Z0-9]+)(\])/', '$1_$3_', $content);
        
        // remove all remaining tags, if any of course.
        $content = preg_replace('/\[([^\]]?)+\]/six', '', $content);
        
        // put back the correct input array
        $content = preg_replace('/(ListSubscriber)(\_)([a-zA-Z0-9]+)(\_)/', '$1[$3]', $content);
        
        // embed output
        if ($request->getQuery('output') == 'embed') {
            $attributes = array(
                'width'     => (int)$request->getQuery('width', 400),
                'height'    => (int)$request->getQuery('height', 200),
            );
            $this->layout = 'embed';
            $this->setData('attributes', $attributes);
        }
        $this->render('display_content', compact('content'));
    }
    
    /**
     * This page is shown when the subscriber confirms his 
     * unsubscription from email by clicking on the unsubscribe confirm link.
     */
    public function actionUnsubscribe_confirm($list_uid, $subscriber_uid, $campaign_uid = null)
    {
        $list = $this->loadListModel($list_uid);
        
        if (!empty($list->customer)) {
            $this->setCustomerLanguage($list->customer);
        }
        
        $pageType   = $this->loadPageTypeModel('unsubscribe-confirm');
        $page       = $this->loadPageModel($list->list_id, $pageType->type_id);
        $subscriber = $this->loadSubscriberModel($subscriber_uid, $list->list_id);
        $options    = Yii::app()->options;
        
        if ($subscriber->status != ListSubscriber::STATUS_CONFIRMED) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $subscriber->status = ListSubscriber::STATUS_UNSUBSCRIBED;
        $saved = $subscriber->save(false);
        
        if ($saved && !empty($campaign_uid)) {
            $campaign = Campaign::model()->findByAttributes(array(
                'campaign_uid'  => $campaign_uid,
                'list_id'       => (int)$list->list_id,
            ));
            
            // add this subscriber to the list of campaign unsubscribers
            if (!empty($campaign)) {
                $track = CampaignTrackUnsubscribe::model()->findByAttributes(array(
                    'campaign_id'   => (int)$campaign->campaign_id,
                    'subscriber_id' => (int)$subscriber->subscriber_id,
                ));
                
                $saved = true;
                if (empty($track)) {
                    $track = new CampaignTrackUnsubscribe();
                    $track->campaign_id   = (int)$campaign->campaign_id;
                    $track->subscriber_id = (int)$subscriber->subscriber_id;
                    $track->ip_address    = Yii::app()->request->getUserHostAddress();
                    $track->user_agent    = substr(Yii::app()->request->getUserAgent(), 0, 255);
                    $saved = $track->save();   
                }
                
                if ($saved) {
                    // raise the action, hook added in 1.2
                    $this->setData('ipLocationSaved', false);
                    Yii::app()->hooks->doAction('frontend_lists_after_track_campaign_unsubscribe', $this, $track); 
                }
            }
        }
        
        $content = !empty($page->content) ? $page->content : $pageType->content;
        $content = CHtml::decode($content);
        
        // add the list name
        $content = str_replace('[LIST_NAME]', CHtml::encode($list->name), $content);
        
        // subscribe url
        $subscribeUrl = Yii::app()->apps->getAppUrl('frontend', sprintf('lists/%s/subscribe/%s', $list->list_uid, $subscriber->subscriber_uid));

        $content = str_replace('[SUBSCRIBE_URL]', $subscribeUrl, $content);
        
        if ($saved) {
            // raise event.
            $this->callbacks->onSubscriberSaveSuccess(new CEvent($this->callbacks, array(
                'subscriber'    => $subscriber, 
                'list'          => $list,
                'action'        => 'unsubscribe-confirm',
            )));
        }
        
        if (Yii::app()->options->get('system.customer.action_logging_enabled', true)) {
            $customer = $list->customer;
            $customer->attachBehavior('logAction', array(
                'class' => 'customer.components.behaviors.CustomerActionLogBehavior'
            ));
            $customer->logAction->subscriberUnsubscribed($subscriber);
        }
        
        if ($list->customerNotification->unsubscribe == ListCustomerNotification::TEXT_YES && !empty($list->customerNotification->unsubscribe_to) && ($server = DeliveryServer::pickServer(0, $list))) {
            $emailTemplate = $options->get('system.email_templates.common');
            $emailBody = $this->renderPartial('_email-subscriber-unsubscribed', compact('list', 'subscriber'), true);
            $emailTemplate = str_replace('[CONTENT]', $emailBody, $emailTemplate);

            $params = array (
                'to'        => array($list->customerNotification->unsubscribe_to => $customer->getFullName()),
                'fromName'  => $list->default->from_name,
                'subject'   => Yii::t('lists', 'List subscriber unsubscribed!'),
                'body'      => $emailTemplate, 
            );
            
            $server->setDeliveryFor(DeliveryServer::DELIVERY_FOR_LIST)->setDeliveryObject($list)->sendEmail($params);
        }
        
        $this->render('display_content', compact('content'));
    }
    
    /**
     * Helper method to load the list AR model
     */
    public function loadListModel($list_uid)
    {
        $model = Lists::model()->findByUid($list_uid);
        
        if ($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        return $model;
    }
    
    /**
     * Helper method to load the list page type AR model
     */
    public function loadPageTypeModel($slug)
    {
        $model = ListPageType::model()->findBySlug($slug);
        
        if ($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        return $model;
    }
    
    /**
     * Helper method to load the list page AR model
     */
    public function loadPageModel($list_id, $type_id)
    {
        return ListPage::model()->findByAttributes(array(
            'list_id' => (int)$list_id,
            'type_id' => (int)$type_id,
        ));
    }
    
    /**
     * Helper method to load the list subscriber AR model
     */
    public function loadSubscriberModel($subscriber_uid, $list_id)
    {
        $model = ListSubscriber::model()->findByAttributes(array(
            'subscriber_uid'    => $subscriber_uid,
            'list_id'           => (int)$list_id
        ));
        
        if ($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        return $model;
    }
    
    /**
     * Helper method to set the language for this customer.
     */
    public function setCustomerLanguage($customer)
    {
        if (empty($customer->language_id)) {
            return $this;
        }

        $isUser     = Yii::app()->hasComponent('user') && Yii::app()->user->getId() > 0;
        $isCustomer = Yii::app()->hasComponent('customer') && Yii::app()->customer->getId() > 0;
        
        // impersonating maybe, language set in init anyway...
        if ($isUser) {
            return $this;
        }
        
        // same customer, language set in init!
        if (!$isUser && (Yii::app()->hasComponent('customer') && $customer->customer_id == Yii::app()->customer->getId())) {
            return $this;
        }
        
        // multilanguage is available since 1.1 and the Language class does not exist prior to that version
        if (!version_compare(Yii::app()->options->get('system.common.version'), '1.1', '>=')) {
            return $this;    
        }
        
        $language = Language::model()->findByPk((int)$customer->language_id);
        
        if (!empty($language)) {
            Yii::app()->setLanguage($language->getLanguageAndLocaleCode());
        }
        
        return $this;
    }
    
}