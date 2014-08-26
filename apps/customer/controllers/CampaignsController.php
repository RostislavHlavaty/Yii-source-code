<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CampaignsController
 * 
 * Handles the actions for campaigns related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class CampaignsController extends Controller
{
    public function init()
    {
        $this->getData('pageStyles')->add(array('src' => AssetsUrl::js('datetimepicker/css/bootstrap-datetimepicker.min.css')));
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('datetimepicker/js/bootstrap-datetimepicker.min.js')));
        
        $languageCode = LanguageHelper::getAppLanguageCode();
        if (Yii::app()->language != Yii::app()->sourceLanguage && is_file(AssetsPath::js($languageFile = 'datetimepicker/js/locales/bootstrap-datetimepicker.'.$languageCode.'.js'))) {
            $this->getData('pageScripts')->add(array('src' => AssetsUrl::js($languageFile)));
        }
        
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('campaigns.js')));
        $this->getData('pageStyles')->add(array('src' => AssetsUrl::css('wizard.css')));
        
        parent::init();
    }
    
    /**
     * Define the filters for various controller actions
     * Merge the filters with the ones from parent implementation
     */
    public function filters()
    {
        return CMap::mergeArray(array(
            'postOnly + delete, pause_unpause, copy, spamcheck, resume_sending, remove_attachment',
        ), parent::filters());
    }
    
    /**
     * List available campaigns
     */
    public function actionIndex()
    {                    
        $request = Yii::app()->request;
        $campaign = new Campaign('search');
        $campaign->unsetAttributes();
        
        $campaign->attributes = (array)$request->getQuery($campaign->modelName, array());
        $campaign->customer_id = (int)Yii::app()->customer->getId();
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('campaigns', 'Your campaigns'), 
            'pageHeading'       => Yii::t('campaigns', 'Your campaigns'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                Yii::t('app', 'View all')
            )
        ));
        
        $this->render('index', compact('campaign'));
    }
    
    /**
     * Show the overview for a campaign
     */
    public function actionOverview($campaign_uid)
    {
        $campaign = $this->loadCampaignModel($campaign_uid);
        $request = Yii::app()->request;
        
        if (!$campaign->accessOverview) {
            $this->redirect(array('campaigns/setup', 'campaign_uid' => $campaign->campaign_uid));
        }
        
        $campaign->attachBehavior('stats', array(
            'class' => 'customer.components.behaviors.CampaignStatsProcessorBehavior',
        ));
        
        $options        = Yii::app()->options;
        $webVersionUrl  = $options->get('system.urls.frontend_absolute_url');
        $webVersionUrl .= 'campaigns/' . $campaign->campaign_uid;

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('campaigns', 'Campaign overview'),
            'pageHeading'       => Yii::t('campaigns', 'Campaign overview'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/overview', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaigns', 'Overview')
            )
        ));

        $this->render('overview', compact('campaign', 'webVersionUrl'));
    }
    
    /**
     * Create a new campaign
     */
    public function actionCreate()
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $customer   = Yii::app()->customer->getModel();
        
        $campaign = new Campaign('step-name');
        $campaign->customer_id = (int)$customer->customer_id;
        
        if (($maxCampaigns = (int)$customer->getGroupOption('campaigns.max_campaigns', -1)) > -1) {
            $criteria = new CDbCriteria();
            $criteria->with = array(
                'list' => array(
                    'joinType'  => 'INNER JOIN',
                    'together'  => true,
                    'condition' => 'list.customer_id = :cid',
                    'params'    => array(':cid' => $customer->customer_id),
                ),
            );
            $campaignsCount = Campaign::model()->count($criteria);
            if ($campaignsCount >= $maxCampaigns) {
                $notify->addWarning(Yii::t('lists', 'You have reached the maximum number of allowed campaigns.'));
                $this->redirect(array('campaigns/index'));
            }
        }
        
        $campaignTempSource = new CampaignTemporarySource();
        $temporarySources   = array();
        $multiListsAllowed  = $customer->getGroupOption('campaigns.send_to_multiple_lists', 'no') == 'yes';
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($campaign->modelName, array()))) {
            $campaign->attributes = $attributes;
            if ($campaign->save()) {
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->campaignCreated($campaign);    
                }
                
                $option = new CampaignOption();
                $option->campaign_id = $campaign->campaign_id;
                $option->save();
                
                if ($multiListsAllowed && ($attributes = (array)$request->getPost($campaignTempSource->modelName, array()))) {
                    foreach ($attributes as $attrs) {
                        $tempModel = new CampaignTemporarySource();
                        $tempModel->attributes  = $attrs;
                        $tempModel->campaign_id = $campaign->campaign_id;
                        $tempModel->save();
                    }
                }
                
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            } else {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'campaign'  => $campaign,
            )));
            
            if ($collection->success) {
                $this->redirect(array('campaigns/setup', 'campaign_uid' => $campaign->campaign_uid));
            }
        }

        $listsArray     = CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $campaign->getListsDropDownArray());
        $segmentsArray  = CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $campaign->getSegmentsDropDownArray());
        $groupsArray    = CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $campaign->getGroupsDropDownArray());
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('campaigns', 'Create new campaign'),
            'pageHeading'       => Yii::t('campaigns', 'Create new campaign'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                Yii::t('app', 'Create new')
            )
        ));
        
        $this->render('step-name', compact('campaign', 'listsArray', 'segmentsArray', 'groupsArray', 'campaignTempSource', 'temporarySources', 'multiListsAllowed'));
    }
    
    /**
     * Update existing campaign
     */
    public function actionUpdate($campaign_uid)
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $customer   = Yii::app()->customer->getModel();
        
        if (!$campaign->editable) {
            $this->redirect(array('campaigns/overview', 'campaign_uid' => $campaign->campaign_uid));
        }
        $campaign->scenario = 'step-name';
        
        $campaignTempSource = new CampaignTemporarySource();
        $temporarySources   = CampaignTemporarySource::model()->findAllByAttributes(array('campaign_id' => $campaign->campaign_id));
        $multiListsAllowed  = $customer->getGroupOption('campaigns.send_to_multiple_lists', 'no') == 'yes';
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($campaign->modelName, array()))) {
            // since 1.3.4.2 we don't allow changing the list/segment if the campaign is paused.
            if ($campaign->getIsPaused()) {
                $this->redirect(array('campaigns/setup', 'campaign_uid' => $campaign->campaign_uid));
            }
            $campaign->attributes = $attributes;
            if ($campaign->save()) {
                if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                    $logAction->campaignUpdated($campaign);    
                }
                CampaignTemporarySource::model()->deleteAllByAttributes(array('campaign_id' => $campaign->campaign_id));
                if ($multiListsAllowed && ($attributes = (array)$request->getPost($campaignTempSource->modelName, array()))) {
                    foreach ($attributes as $attrs) {
                        $tempModel = new CampaignTemporarySource();
                        $tempModel->attributes  = $attrs;
                        $tempModel->campaign_id = $campaign->campaign_id;
                        $tempModel->save();
                    }
                }
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            } else {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'campaign'  => $campaign,
            )));
            
            if ($collection->success) {
                $this->redirect(array('campaigns/setup', 'campaign_uid' => $campaign->campaign_uid));
            }
        }

        $listsArray     = CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $campaign->getListsDropDownArray());
        $segmentsArray  = CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $campaign->getSegmentsDropDownArray());
        $groupsArray    = CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $campaign->getGroupsDropDownArray());
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('campaigns', 'Update campaign'),
            'pageHeading'       => Yii::t('campaigns', 'Update campaign'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/update', array('campaign_uid' => $campaign_uid)),
                Yii::t('app', 'Update')
            )
        ));
        
        $this->render('step-name', compact('campaign', 'listsArray', 'segmentsArray', 'groupsArray', 'campaignTempSource', 'temporarySources', 'multiListsAllowed'));
    }
    
    /**
     * Setup campaign
     */
    public function actionSetup($campaign_uid)
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $options    = Yii::app()->options;
        $campaign   = $this->loadCampaignModel($campaign_uid);
        
        if (!$campaign->editable) {
            $this->redirect(array('campaigns/overview', 'campaign_uid' => $campaign->campaign_uid));
        }
        
        $campaign->scenario = 'step-setup';
        $default = $campaign->list->default;
        $sameFields = array('from_name', 'from_email', 'subject', 'reply_to');
        
        if (!empty($default)) {
            foreach ($sameFields as $attribute) {
                if (empty($campaign->$attribute)) {
                    $campaign->$attribute = $default->$attribute;
                }    
            }    
        }
        
        // customer reference
        $customer = $campaign->list->customer;
        
        // delivery servers for this campaign - start
        $canSelectDeliveryServers       = $customer->getGroupOption('servers.can_select_delivery_servers_for_campaign', 'no') == 'yes';
        $campaignDeliveryServersArray   = array();
        $campaignToDeliveryServers      = CampaignToDeliveryServer::model();
        if ($canSelectDeliveryServers) {
            $deliveryServers = $customer->getAvailableDeliveryServers();
            
            $campaignDeliveryServers = $campaignToDeliveryServers->findAllByAttributes(array(
                'campaign_id' => $campaign->campaign_id,
            ));
            
            foreach ($campaignDeliveryServers as $srv) {
                $campaignDeliveryServersArray[] = $srv->server_id;
            }            
        }
        // delivery servers for this campaign - end
        
        // attachments - start
        $canAddAttachments = $options->get('system.campaign.attachments.enabled', 'no') == 'yes';
        $attachment        = null;
        if ($canAddAttachments) {
            $attachment = new CampaignAttachment('multi-upload');
            $attachment->campaign_id = $campaign->campaign_id;    
        }
        // attachments - end

        // actions upon open - start
        $openAction = new CampaignOpenActionSubscriber();
        $openAction->campaign_id = $campaign->campaign_id;
        $openActionLists    = array();
        $openActions        = array();
        $openAllowedActions = CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $openAction->getActions());
        $openActionLists    = $campaign->getListsDropDownArray();
        foreach ($openActionLists as $list_id => $name) {
            if ($list_id == $campaign->list_id) {
                unset($openActionLists[$list_id]);
                break;
            }
        }
        $canShowOpenActions = !empty($openActionLists);
        $openActionLists    = CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $openActionLists);
        $openActions        = CampaignOpenActionSubscriber::model()->findAllByAttributes(array(
            'campaign_id' => $campaign->campaign_id,
        ));
        // actions upon open - end
        
        // populate list custom field upon open - start
        $openListFieldAction = new CampaignOpenActionListField();
        $openListFieldAction->campaign_id = $campaign->campaign_id;
        $openListFieldAction->list_id     = $campaign->list_id; 
        $openListFieldActionOptions       = $openListFieldAction->getTextFieldsAsDropDownOptions();
        $canShowOpenListFieldActions      = !empty($openListFieldActionOptions);
        $openListFieldActionOptions       = CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $openListFieldActionOptions);
        $openListFieldActions = CampaignOpenActionListField::model()->findAllByAttributes(array(
            'campaign_id' => $campaign->campaign_id,
        ));
        // populate list custom field upon open - end
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($campaign->modelName, array()))) {
            $campaign->attributes = $attributes;
            $campaign->option->attributes = (array)$request->getPost($campaign->option->modelName, array());
            if ($campaign->save() && $campaign->option->save()) {
                
                // actions upon open against subscriber
                CampaignOpenActionSubscriber::model()->deleteAllByAttributes(array(
                    'campaign_id' => $campaign->campaign_id
                ));
                if ($postAttributes = (array)$request->getPost($openAction->modelName, array())) {
                    foreach ($postAttributes as $index => $attributes) {
                        $openAct = new CampaignOpenActionSubscriber();
                        $openAct->attributes = $attributes;
                        $openAct->campaign_id = $campaign->campaign_id;
                        $openAct->save();
                    }
                }
                
                // action upon open against subscriber custom fields.
                CampaignOpenActionListField::model()->deleteAllByAttributes(array(
                    'campaign_id' => $campaign->campaign_id
                ));
                if ($postAttributes = (array)$request->getPost($openListFieldAction->modelName, array())) {
                    foreach ($postAttributes as $index => $attributes) {
                        $openListFieldAct = new CampaignOpenActionListField();
                        $openListFieldAct->attributes  = $attributes;
                        $openListFieldAct->campaign_id = $campaign->campaign_id;
                        $openListFieldAct->list_id     = $campaign->list_id;
                        $openListFieldAct->save();
                    }
                }
                    
                $campaignToDeliveryServers->deleteAllByAttributes(array(
                    'campaign_id' => $campaign->campaign_id,
                ));
                if ($canSelectDeliveryServers && ($attributes = (array)$request->getPost($campaignToDeliveryServers->modelName, array()))) {
                    foreach ($attributes as $serverId) {
                        $relation = new CampaignToDeliveryServer();
                        $relation->campaign_id = $campaign->campaign_id;
                        $relation->server_id = (int)$serverId;
                        $relation->save();
                    }
                }
                
                if ($canAddAttachments && $attachments = CUploadedFile::getInstances($attachment, 'file')) {
                    $attachment->file = $attachments;
                    $attachment->validateAndSave();
                }
                
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            } else {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'campaign'  => $campaign,
            )));
            
            if ($collection->success) {
                $this->redirect(array('campaigns/template', 'campaign_uid' => $campaign->campaign_uid));
            }
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('campaigns', 'Setup campaign'),
            'pageHeading'       => Yii::t('campaigns', 'Campaign setup'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/update', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaigns', 'Setup')
            )
        ));
        
        $this->render('step-setup', compact(
            'campaign', 
            'canSelectDeliveryServers',
            'campaignToDeliveryServers', 
            'deliveryServers', 
            'campaignDeliveryServersArray',
            'canAddAttachments',
            'attachment',
            'canShowOpenActions',
            'openAction',
            'openActions',
            'openAllowedActions',
            'openActionLists',
            'openListFieldAction',
            'openListFieldActions',
            'openListFieldActionOptions',
            'canShowOpenListFieldActions'
        ));
    }
    
    /**
     * Choose or create campaign template
     */
    public function actionTemplate($campaign_uid, $do = 'create')
    {
        $request = Yii::app()->request;
        $campaign = $this->loadCampaignModel($campaign_uid);
        
        if (!$campaign->editable) {
            $this->redirect(array('campaigns/overview', 'campaign_uid' => $campaign->campaign_uid));
        }

        if ($do === 'select') {
            
            if ($template_uid = $request->getQuery('template_uid')) {
                $campaignTemplate = $campaign->template;
                if (empty($campaignTemplate)) {
                    $campaignTemplate = new CampaignTemplate();
                }
                $campaignTemplate->setScenario('copy');
                
                if (!empty($campaignTemplate->template_id)) {
                    CampaignTemplateUrlActionSubscriber::model()->deleteAllByAttributes(array(
                        'template_id' => $campaignTemplate->template_id
                    ));
                }
                
                $selectedTemplate = CustomerEmailTemplate::model()->findByAttributes(array(
                    'template_uid'  => $template_uid,
                    'customer_id'   => (int)Yii::app()->customer->getId(),
                ));
                
                $redirect = array('campaigns/template', 'campaign_uid' => $campaign_uid, 'do' => 'create');
                
                if (!empty($selectedTemplate)) {
                    
                    $campaignTemplate->campaign_id           = $campaign->campaign_id;
                    $campaignTemplate->customer_template_id  = $selectedTemplate->template_id;
                    $campaignTemplate->content               = $selectedTemplate->content;
                    $campaignTemplate->inline_css            = $selectedTemplate->inline_css;
                    $campaignTemplate->minify                = $selectedTemplate->minify;
                    
                    if (!empty($campaign->option) && $campaign->option->plain_text_email == CampaignOption::TEXT_YES && $campaignTemplate->auto_plain_text === CampaignTemplate::TEXT_YES) {
                        $campaignTemplate->plain_text = CampaignHelper::htmlToText($selectedTemplate->content);
                    }
                
                    $campaignTemplate->save();
                    
                    /**
                     * We also need to create a copy of the template files.
                     * This avoids the scenario where a campaign based on a uploaded template is sent
                     * then after a while the template is deleted. 
                     * In this scenario, the campaign will remain without images. 
                     */

                    $storagePath = Yii::getPathOfAlias('root.frontend.assets.gallery');
                    
                    // make sure the new template has images, otherwise don't bother.
                    $filesPath = $storagePath.'/'.$selectedTemplate->template_uid;
                    if (!file_exists($filesPath) || !is_dir($filesPath)) {
                        $this->redirect($redirect);
                    }

                    // check if there's already a copy if this campaign template. if so, remove it, we don't want a folder with 1000 images.
                    $campaignFiles = $storagePath.'/cmp'.$campaign->campaign_uid;
                    if (file_exists($campaignFiles) && is_dir($campaignFiles)) {
                        FileSystemHelper::deleteDirectoryContents($campaignFiles, true, 1);
                    }
                    
                    // copy the template folder to the campaign folder.
                    if (!FileSystemHelper::copyOnlyDirectoryContents($filesPath, $campaignFiles)) {
                        $this->redirect($redirect);
                    }
                    
                    $search = array (
                        'frontend/assets/gallery/cmp'.$campaign->campaign_uid,
                        'frontend/assets/gallery/'.$selectedTemplate->template_uid
                    );
                    $replace = 'frontend/assets/gallery/cmp'.$campaign->campaign_uid;
                    $campaignTemplate->content = str_ireplace($search, $replace, $campaignTemplate->content);
                    
                    if (!empty($campaign->option) && $campaign->option->plain_text_email == CampaignOption::TEXT_YES && $campaignTemplate->auto_plain_text === CampaignTemplate::TEXT_YES) {
                        $campaignTemplate->plain_text = CampaignHelper::htmlToText($campaignTemplate->content);
                    }
                    
                    $campaignTemplate->save(false);
                }
                $this->redirect($redirect);
            }
            
            $criteria = new CDbCriteria();
            $criteria->compare('t.customer_id', (int)Yii::app()->customer->getId());
            $criteria->order = 't.template_id DESC';
            $count = CustomerEmailTemplate::model()->count($criteria);
            
            $pages = new CPagination($count);
            $pages->pageSize = 30;
            $pages->applyLimit($criteria);
            
            $this->data->templates = CustomerEmailTemplate::model()->findAll($criteria);
            $this->data->pages = $pages;
            
            $viewFile = 'step-template-select';
        
        } elseif ($do === 'create' || $do === 'from-url') {
            
            $template = $campaign->template;
            if (empty($template)) {
                $template = new CampaignTemplate();
            }
            $template->fieldDecorator->onHtmlOptionsSetup = array($this, '_setEditorOptions');
            $template->campaign_id = $campaign->campaign_id;
            $this->data->template = $template;
            
            if ($request->getQuery('prev') == 'upload' && !empty($template->template_id)) {
                CampaignTemplateUrlActionSubscriber::model()->deleteAllByAttributes(array(
                    'template_id' => $template->template_id
                ));
                CampaignTemplateUrlActionListField::model()->deleteAllByAttributes(array(
                    'template_id' => $template->template_id
                ));
                $this->redirect(array('campaigns/template', 'campaign_uid' => $campaign_uid, 'do' => 'create'));
            }
            
            if ($request->isPostRequest && ($attributes = (array)$request->getPost($template->modelName, array()))) {
                $template->attributes = $attributes;
                
                if (isset(Yii::app()->params['POST'][$template->modelName]['content'])) {
                    $template->content = Yii::app()->params['POST'][$template->modelName]['content'];
                } else {
                    $template->content = null;
                }
                
                if ($campaign->option->plain_text_email != CampaignOption::TEXT_YES) {
                    $template->only_plain_text = CampaignTemplate::TEXT_NO;
                    $template->auto_plain_text = CampaignTemplate::TEXT_NO;
                    $template->plain_text      = null;
                }
                
                $template->campaign_id = $campaign->campaign_id;
                $parser = new EmailTemplateParser();
                $parser->inlineCss = $template->inline_css === CampaignTemplate::TEXT_YES;
                $parser->minify    = $template->minify === CampaignTemplate::TEXT_YES;
                    
                // since 1.3.4.2, allow content fetched from url
                // TO DO: Add an option in backend to enable/disable this feature!
                $errors = array();
                if ($do === 'from-url' && isset($attributes['from_url'])) {
                    if (!filter_var($attributes['from_url'], FILTER_VALIDATE_URL)) {
                        $errors[] = Yii::t('campaigns', 'The provided url does not seem to be valid!');
                    } else {
                        $response = AppInitHelper::simpleCurlGet($attributes['from_url']);
                        if ($response['status'] == 'error') {
                            $errors[] = $response['message'];
                        } else {
                            // do a blind search after some common html elements
                            $elements = array('<div', '<table', '<a', '<p', '<br', 'style=');
                            $found = false;
                            foreach ($elements as $elem) {
                                if (stripos($response['message'], $elem) !== false) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $errors[] = Yii::t('campaigns', 'The provided url does not seem to contain valid html!');
                            } else {
                                $template->content = $response['message'];
                            }
                        }    
                    }
                }
                
                if (!$template->isOnlyPlainText) {
                    $template->content = $parser->setContent($template->content)->getContent();
                } else {
                    $template->content    = Yii::app()->ioFilter->purify($template->plain_text);
                    $template->plain_text = $template->content;
                }
                    
                $isNext = $request->getPost('is_next', 0);
                
                if (!empty($template->content) && !empty($campaign->option) && $campaign->option->plain_text_email == CampaignOption::TEXT_YES && $template->auto_plain_text === CampaignTemplate::TEXT_YES && empty($template->plain_text)) {
                    $template->plain_text = CampaignHelper::htmlToText($template->content);
                }
                
                if (empty($errors) && $template->save()) {
                    Yii::app()->notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
                    if ($isNext) {
                        $redirect = array('campaigns/confirm', 'campaign_uid' => $campaign_uid);
                    }
                    // since 1.3.4.3
                    CampaignTemplateUrlActionSubscriber::model()->deleteAllByAttributes(array(
                        'template_id' => $template->template_id
                    ));
                    if ($postAttributes = (array)$request->getPost('CampaignTemplateUrlActionSubscriber', array())) {
                        foreach ($postAttributes as $index => $attributes) {
                            $templateUrlActionSubscriber = new CampaignTemplateUrlActionSubscriber();
                            $templateUrlActionSubscriber->attributes = $attributes;
                            $templateUrlActionSubscriber->template_id = $template->template_id;
                            $templateUrlActionSubscriber->campaign_id = $campaign->campaign_id;
                            $templateUrlActionSubscriber->save();
                        }
                    }
                    // since 1.3.4.5
                    CampaignTemplateUrlActionListField::model()->deleteAllByAttributes(array(
                        'template_id' => $template->template_id
                    ));
                    if ($postAttributes = (array)$request->getPost('CampaignTemplateUrlActionListField', array())) {
                        foreach ($postAttributes as $index => $attributes) {
                            $templateUrlActionListField = new CampaignTemplateUrlActionListField();
                            $templateUrlActionListField->attributes = $attributes;
                            $templateUrlActionListField->template_id = $template->template_id;
                            $templateUrlActionListField->campaign_id = $campaign->campaign_id;
                            $templateUrlActionListField->list_id     = $campaign->list_id;
                            $templateUrlActionListField->save();
                        }
                    }
                } else {
                    Yii::app()->notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                    if (!empty($errors)) {
                        Yii::app()->notify->addError($errors);
                    }
                }
                
                Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                    'controller'=> $this,
                    'success'   => Yii::app()->notify->hasSuccess,
                    'do'        => $do,
                    'campaign'  => $campaign,
                    'template'  => $template,
                )));
                
                if ($collection->success) {
                    if (!empty($redirect)) {
                        $this->redirect($redirect);
                    }
                }
            }
            
            // since 1.3.4.3
            if ($campaign->option->url_tracking === CampaignOption::TEXT_YES && !empty($template->content)) {
                $contentUrls = $template->getContentUrls();
                if (!empty($contentUrls)) {
                    $templateListsArray = $campaign->getListsDropDownArray();
                    foreach ($templateListsArray as $list_id => $name) {
                        if ($list_id == $campaign->list_id) {
                            unset($templateListsArray[$list_id]);
                            break;
                        }
                    }
                    $templateUrlActionSubscriber = new CampaignTemplateUrlActionSubscriber();
                    $templateUrlActionSubscriber->campaign_id = $campaign->campaign_id;
                    $this->setData(array(
                        'templateListsArray'                => !empty($templateListsArray) ? CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $templateListsArray) : array(),
                        'templateContentUrls'               => CMap::mergeArray(array('' => Yii::t('app', 'Choose')), array_combine($contentUrls, $contentUrls)),
                        'clickAllowedActions'               => CMap::mergeArray(array('' => Yii::t('app', 'Choose')), $templateUrlActionSubscriber->getActions()),
                        'templateUrlActionSubscriber'       => $templateUrlActionSubscriber,
                        'templateUrlActionSubscriberModels' => $templateUrlActionSubscriber->findAllByAttributes(array('template_id' => $template->template_id)),
                    ));

                    // since 1.3.4.5
                    $templateUrlActionListField = new CampaignTemplateUrlActionListField();
                    $templateUrlActionListField->campaign_id = $campaign->campaign_id;
                    $templateUrlActionListField->list_id     = $campaign->list_id;
                    $this->setData(array(
                        'templateUrlActionListField'  => $templateUrlActionListField,
                        'templateUrlActionListFields' => $templateUrlActionListField->findAllByAttributes(array('template_id' => $template->template_id)),
                    ));
                }
            }
                        
            $this->data->templateUp = new CampaignEmailTemplateUpload('upload');
            $viewFile = 'step-template-create';
        
        } elseif ($do == 'upload') {
            
            if ($request->isPostRequest && $request->getPost('is_next', 0)) {
                $this->redirect(array('campaigns/confirm', 'campaign_uid' => $campaign_uid));
            }
            
            $template = $campaign->template;
            if (empty($template)) {
                $template = new CampaignTemplate();
            }
            $template->fieldDecorator->onHtmlOptionsSetup = array($this, '_setEditorOptions');
            $template->campaign_id = $campaign->campaign_id;

            $templateUp = new CampaignEmailTemplateUpload('upload');
            $templateUp->customer_id = (int)Yii::app()->customer->getId();
            $templateUp->campaign    = $campaign;
            
            $redirect = array('campaigns/template', 'campaign_uid' => $campaign_uid, 'do' => 'create', 'prev' => 'upload');
            
            if ($request->isPostRequest && ($attributes = (array)$request->getPost($templateUp->modelName, array()))) {
                $templateUp->attributes = $attributes;
                $templateUp->archive = CUploadedFile::getInstance($templateUp, 'archive');
                if (!$templateUp->validate() || !$templateUp->uploader->handleUpload()) {
                    Yii::app()->notify->addError($templateUp->shortErrors->getAllAsString());
                } else {
                    $template->content      = $templateUp->content;
                    $template->inline_css   = $templateUp->inline_css;
                    $template->minify       = $templateUp->minify;                    
                    
                    if (!empty($campaign->option) && $campaign->option->plain_text_email == CampaignOption::TEXT_YES && $templateUp->auto_plain_text === CampaignTemplate::TEXT_YES && empty($templateUp->plain_text)) {
                        $template->plain_text = CampaignHelper::htmlToText($templateUp->content);
                    }
                    
                    if ($template->save()) {
                        Yii::app()->notify->addSuccess(Yii::t('app', 'Your file has been successfully uploaded!'));
                    } else {
                        Yii::app()->notify->addError($template->shortErrors->getAllAsString());
                    }
                }
                
                Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                    'controller'=> $this,
                    'success'   => Yii::app()->notify->hasSuccess,
                    'do'        => $do,
                    'campaign'  => $campaign,
                    'template'  => $template,
                    'templateUp'=> $templateUp,
                )));
                
                if ($collection->success) {
                    $this->redirect($redirect);
                }
            }
            
            $this->data->templateUp = $templateUp;
            $this->data->template   = $template;
            $viewFile = 'step-template-create';
            
        } else {
            
            $this->redirect(array('campaigns/template', 'campaign_uid' => $campaign_uid, 'do' => 'create'));
        
        }
        
        // since 1.3.4.2, add a warning if the campaign is paused and template changed
        if ($campaign->getIsPaused()) {
            Yii::app()->notify->addWarning(Yii::t('campaigns', 'This campaign is paused, please have this in mind if you are going to change the template, it will affect subscribers that already received the current template!'));
        }
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('campaigns', 'Campaign template'),
            'pageHeading'       => Yii::t('campaigns', 'Campaign template'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                $campaign->name => $this->createUrl('campaigns/update', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaigns', 'Template')
            )
        ));
        
        $this->render($viewFile, compact('campaign'));
    }
    
    /**
     * Confirm the campaign and schedule it for sending
     */
    public function actionConfirm($campaign_uid)
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $campaign   = $this->loadCampaignModel($campaign_uid);

        if (!$campaign->editable) {
            $this->redirect(array('campaigns/overview', 'campaign_uid' => $campaign->campaign_uid));
        }
        
        $customer = Yii::app()->customer->getModel();
        $campaign->scenario = 'step-confirm';
        
        if ($campaign->isAutoresponder) {
            $campaign->option->setScenario('step-confirm-ar');
        }
        
        $segmentSubscribers = 0;
        $listSubscribers = 0;
        $hasError = false;

        if (empty($campaign->template->content)) {
            $hasError = true;
            $notify->addError(Yii::t('campaigns', 'Missing campaign template!'));
        }
        
        if (!$hasError && $request->isPostRequest) {
            $campaign->attributes = (array)$request->getPost($campaign->modelName, array());
            $campaign->status     = Campaign::STATUS_PENDING_SENDING;
            
            // since 1.3.4.2, we allow paused campaigns to be edited.
            if ($campaign->getIsPaused()) {
                $campaign->status = Campaign::STATUS_PAUSED;
            }
            
            $transaction = Yii::app()->getDb()->beginTransaction();
            $redirect    = array('campaigns/index'); 
            $saved       = false;
            
            if (!empty($campaign->temporarySources)) {
                $redirect = array('campaigns/merge_lists', 'campaign_uid' => $campaign_uid);
                $campaign->status = Campaign::STATUS_DRAFT;
            }

            if ($campaign->save()) {
                $saved    = true;    
                if ($campaign->isAutoresponder) {
                    $campaign->option->attributes = (array)$request->getPost($campaign->option->modelName, array());
                    if (!$campaign->option->save()) {
                        $saved = false;
                        $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
                    }
                }
                if ($saved) {
                    if (!empty($campaign->temporarySources)) {
                        $notify->addSuccess(Yii::t('campaigns', 'Please wait while all selected lists for this campaigns are merged. You will be redirected back once everything is done.')); 
                    } else {
                        if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                            $logAction->campaignScheduled($campaign);    
                        }
                        $notify->addSuccess(Yii::t('campaigns', 'Your campaign({type}) named "{campaignName}" has been successfully saved and will start sending at {sendDateTime}!', array(
                            '{campaignName}'    => $campaign->name,
                            '{sendDateTime}'    => $campaign->getDisplaySendAt(),
                            '{type}'            => Yii::t('campaigns', $campaign->type),
                        )));    
                    }
                }
            } else {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            }
            
            if ($saved) {
                $transaction->commit();
            } else {
                $transaction->rollBack();
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'campaign'  => $campaign,
            )));
            
            if ($collection->success) {
                if (!empty($redirect)) {
                    $this->redirect($redirect);
                }
            }
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('campaigns', 'Campaign overview'),
            'pageHeading'       => Yii::t('campaigns', 'Campaign confirmation'),
            'pageBreadcrumbs'   => array(
                Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'),
                CHtml::encode($campaign->name) => $this->createUrl('campaigns/update', array('campaign_uid' => $campaign_uid)),
                Yii::t('campaigns', 'Confirmation')
            )
        ));
        
        $this->render('step-confirm', compact('campaign', 'listSubscribers', 'segmentSubscribers'));
    }
    
    /**
     * Merge multiple lists into one for this campaign
     */
    public function actionMerge_lists($campaign_uid)
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $customer   = Yii::app()->customer->getModel();
        
        if ($customer->getGroupOption('campaigns.send_to_multiple_lists', 'no') != 'yes') {
            $notify->addError(Yii::t('campaigns', 'Your don\'t have enough priviledges to access this feature!'));
            $this->redirect(array('campaigns/confirm', 'campaign_uid' => $campaign_uid));
        }
        
        if (empty($campaign->temporarySources)) {
            $notify->addError(Yii::t('campaigns', 'This campaign does not support sending to multiple lists!'));
            $this->redirect(array('campaigns/confirm', 'campaign_uid' => $campaign_uid));
        }
        
        if (!Yii::app()->mutex->acquire('mergeListsFor' . $campaign_uid)) {
            if ($request->isAjaxRequest) {
                return $this->renderJson(array(
                    'finished'      => true,
                    'progress_text' => Yii::t('campaigns', 'Unable to acquire lock!'),
                ));
            }
            $notify->addError(Yii::t('campaigns', 'Unable to acquire lock!'));
            $this->redirect(array('campaigns/confirm', 'campaign_uid' => $campaign_uid));
        }
        
        $listId           = (int)$request->getPost('list_id', $campaign->list_id);
        $segmentId        = (int)$request->getPost('segment_id', $campaign->segment_id);
        $sourceId         = (int)$request->getPost('source_id');
        $clid             = (int)$request->getPost('clid');
        $processedTotal   = (int)$request->getPost('processed_total', 0);
        $processedSuccess = (int)$request->getPost('processed_success', 0);
        $processedError   = (int)$request->getPost('processed_error', 0);
        $progressText     = Yii::t('campaigns', 'The merging process is running, please wait...');
        $finished         = false;

        if ($memoryLimit = $customer->getGroupOption('lists.copy_subscribers_memory_limit')) { 
            ini_set('memory_limit', $memoryLimit);
        }
        
        $fromList = Lists::model()->findByAttributes(array(
            'list_id'     => $listId,
            'customer_id' => (int)Yii::app()->customer->getId(),
        ));
        
        if (empty($fromList)) {
            $this->redirect(array('campaigns/confirm', 'campaign_uid' => $campaign_uid));
        }
        
        $fromSegment = null;
        if (!empty($segmentId)) {
            $fromSegment = ListSegment::model()->findByAttributes(array(
                'list_id'    => $fromList->list_id,
                'segment_id' => $segmentId,
            ));    
            
            if (empty($fromSegment)) {
                $this->redirect(array('campaigns/confirm', 'campaign_uid' => $campaign_uid));
            }
        }
        
        if (!empty($fromSegment)) {
            $count = $fromSegment->countSubscribers();
        } else {
            $count = $fromList->confirmedSubscribersCount;
        }

        $limit  = (int)$customer->getGroupOption('lists.copy_subscribers_at_once', 100);
        $pages  = $count <= $limit ? 1 : ceil($count / $limit);
        $page   = (int)$request->getPost('page', 1);
        $page   = $page < 1 ? 1 : $page; 
        $offset = ($page - 1) * $limit;

        $attributes = array(
            'total'             => $count,
            'processed_total'   => $processedTotal,
            'processed_success' => $processedSuccess,
            'processed_error'   => $processedError,
            'percentage'        => 0,
            'progress_text'     => Yii::t('campaigns', 'The merging process is starting, please wait...'),
            'post_url'          => $this->createUrl('campaigns/merge_lists', array('campaign_uid' => $campaign_uid)),
            'list_id'           => (int)$listId,
            'segment_id'        => (int)$segmentId,
            'source_id'         => (int)$sourceId,
            'clid'              => (int)$clid,
            'page'              => $page,
        );
        
        $jsonAttributes = CJSON::encode($attributes);
        
        if (!$request->isAjaxRequest) {
            $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('campaign-lists-merge.js')));
            $this->setData(array(
                'pageMetaTitle'     => $this->data->pageMetaTitle.' | '.Yii::t('campaigns', 'Merge lists'), 
                'pageHeading'       => Yii::t('campaigns', 'Merge lists'), 
                'pageBreadcrumbs'   => array(
                    Yii::t('campaigns', 'Campaigns') => $this->createUrl('campaigns/index'), 
                    CHtml::encode($campaign->name) => $this->createUrl('campaigns/update', array('campaign_uid' => $campaign_uid)),
                    Yii::t('campaigns', 'Merge lists')
                )
            ));
            return $this->render('merge-lists', compact('campaign', 'jsonAttributes'));
        }

        if (empty($clid)) {
            if (!($list = $fromList->copy())) {
                return $this->renderJson(array(
                    'finished'      => true,
                    'progress_text' => Yii::t('campaigns', 'Unable to copy the campaign initial list.'),
                ));
            }
            $listName = array('MERGED - ' . $list->name);
            foreach ($campaign->temporarySources as $source) {
                $listName[] = empty($source->segment_id) ? $source->list->name : $source->list->name . '/' . $source->segment->name;
            }
            $list->name = implode(', ', $listName);
            $list->name = StringHelper::truncateLength($list->name, 100);
            $list->save(false);
            $clid = $list->list_id;
            $attributes['clid'] = (int)$clid;
        }
        
        if (empty($list)) {
            $list = Lists::model()->findByAttributes(array(
                'list_id'     => (int)$clid,
                'customer_id' => (int)Yii::app()->customer->getId(),
            ));
            if (empty($list)) {
                return $this->renderJson(array(
                    'finished'      => true,
                    'progress_text' => Yii::t('campaigns', 'Unable to copy the campaign initial list.'),
                ));
            }
        }
        
        if (!empty($fromSegment)) {
            $criteria = new CDbCriteria;
            $criteria->select = 't.*';
            $subscribers = $fromSegment->findSubscribers($offset, $limit, $criteria);
        } else {
            $criteria = new CDbCriteria();
            $criteria->compare('list_id', (int)$listId);
            $criteria->compare('status', ListSubscriber::STATUS_CONFIRMED);
            $criteria->limit  = $limit;
            $criteria->offset = $offset;
            $subscribers = ListSubscriber::model()->findAll($criteria);
        }
        
        if (empty($subscribers)) {
            if (!empty($campaign->temporarySources)) {
                $sources = $campaign->temporarySources;
                foreach ($sources as $index => $source) {
                    if ($source->source_id == $sourceId) {
                        $source->delete();
                        unset($sources[$index]);
                        break;
                    }
                }
                if (!empty($sources)) {
                    $source  = array_shift($sources);
                    return $this->renderJson(array_merge($attributes, array(
                        'total'             => 0,
                        'processed_total'   => 0,
                        'processed_success' => 0,
                        'processed_error'   => 0,
                        'percentage'        => 0,
                        'page'              => 1,
                        'reset_counters'    => true,
                        'progress_text'     => Yii::t('campaigns', 'Now merging the list/segment "{sourceName}"', array('{sourceName}' => $source->name)),
                        'list_id'           => (int)$source->list_id,
                        'segment_id'        => (int)$source->segment_id,
                        'source_id'         => (int)$source->source_id,
                        'clid'              => (int)$clid,
                    )));
                }
            }
            
            // update the list id for custom fields action so that it properly loads the custom fields.
            $openActionListFields = CampaignOpenActionListField::model()->findAllByAttributes(array(
                'campaign_id' => $campaign->campaign_id,
            ));
            if (!empty($openActionListFields)) {
                foreach ($openActionListFields as $openActionListField) {
                    $theOtherListField = ListField::model()->findByAttributes(array(
                        'list_id' => $list->list_id,
                        'tag'     => $openActionListField->field->tag
                    ));
                    if (empty($theOtherListField)) {
                        continue;
                    }
                    $openActionListField->field_id = $theOtherListField->field_id;
                    $openActionListField->list_id  = $list->list_id;
                    $openActionListField->save(false);
                }
            }
            
            // delete, just in case...
            CampaignTemporarySource::model()->deleteAllByAttributes(array(
                'campaign_id' => (int)$campaign->campaign_id,
            ));
            $campaign->segment_id = null;
            $campaign->list_id    = $list->list_id;
            $campaign->status     = Campaign::STATUS_PENDING_SENDING;
            $campaign->save(false);
            if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                $logAction->campaignScheduled($campaign);    
            }
            $notify->addSuccess(Yii::t('campaigns', 'Your campaign({type}) named "{campaignName}" has been successfully saved and will start sending at {sendDateTime}!', array(
                '{campaignName}'    => $campaign->name,
                '{sendDateTime}'    => $campaign->getDisplaySendAt(),
                '{type}'            => Yii::t('campaigns', $campaign->type),
            )));
            //
            
            return $this->renderJson(array(
                'finished'      => true,
                'progress_text' => Yii::t('campaigns', 'The merging process is done, your merged list for this campaign is {list}. Please wait to be redirected...', array('{list}' => $list->name)),
                'redirect'      => $this->createUrl('campaigns/index'),
                'timeout'       => 5000,
            ));
        }

        $transaction = Yii::app()->getDb()->beginTransaction();
        
        try {
            foreach ($subscribers as $subscriber) {
                $processedTotal++;
                if ($newSubscriber = $subscriber->copyToList($list->list_id, false)) {
                    $processedSuccess++;
                } else {
                    $processedError++;
                }
            }    
            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
        }
        
        $percentage = round((($processedTotal / $count) * 100), 2);
        
        return $this->renderJson(array_merge($attributes, array(
            'processed_total'   => $processedTotal,
            'processed_success' => $processedSuccess,
            'processed_error'   => $processedError,
            'percentage'        => $percentage,
            'page'              => $page + 1,
            'progress_text'     => $progressText,
            'finished'          => $finished,
            'clid'              => (int)$clid,
        )));
    }

    /**
     * Test the campaign email template by sending it to desired email addressed
     */
    public function actionTest($campaign_uid)
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $campaign   = $this->loadCampaignModel($campaign_uid);
        $template   = $campaign->template;
        
        if (!$campaign->editable) {
            $this->redirect(array('campaigns/overview', 'campaign_uid' => $campaign->campaign_uid));
        }

        if (!$request->getPost('email')) {
            $notify->addError(Yii::t('campaigns', 'Please specify the email address to where we should send the test email.'));
            $this->redirect(array('campaigns/template', 'campaign_uid' => $campaign_uid));
        }
        
        $emails = explode(',', $request->getPost('email'));
        $emails = array_map('trim', $emails);
        $emails = array_unique($emails);
        $emails = array_slice($emails, 0, 10);
        
        $server = DeliveryServer::pickServer(0, $campaign);
        if (empty($server)) {
            $notify->addError(Yii::t('app', 'Email delivery is temporary disabled.'));
            $this->redirect(array('campaigns/template', 'campaign_uid' => $campaign_uid));
        }
        
        foreach ($emails as $index => $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $notify->addError(Yii::t('email_templates',  'The email address {email} does not seem to be valid!', array('{email}' => CHtml::encode($email))));
                unset($emails[$index]);
                continue;
            }
        }
        
        if (empty($emails)) {
            $notify->addError(Yii::t('campaigns', 'Cannot send using provided email address(es)!'));
            $this->redirect(array('campaigns/template', 'campaign_uid' => $campaign_uid));
        }
        
        $subscriber = ListSubscriber::model()->findByAttributes(array(
            'list_id'   => $campaign->list->list_id,
            'status'    => ListSubscriber::STATUS_CONFIRMED,
        ));
        
        $emailSubject   = '[TEST CAMPAIGN] '.$campaign->name;
        $onlyPlainText  = !empty($template->only_plain_text) && $template->only_plain_text === CampaignTemplate::TEXT_YES;
        $emailContent   = !$onlyPlainText ? $template->content : $template->plain_text;
        $embedImages    = array();
        
        if (!$onlyPlainText && !empty($campaign->option) && !empty($campaign->option->embed_images) && $campaign->option->embed_images == CampaignOption::TEXT_YES) {
            list($emailContent, $embedImages) = CampaignHelper::embedContentImages($emailContent, $campaign);
        }
                        
        if (!empty($subscriber)) {
            
            if (!$onlyPlainText && !empty($campaign->option) && $campaign->option->xml_feed == CampaignOption::TEXT_YES) {
                $emailContent = CampaignXmlFeedParser::parseContent($emailContent);
            }
            
            if (!$onlyPlainText && !empty($campaign->option) && $campaign->option->json_feed == CampaignOption::TEXT_YES) {
                $emailContent = CampaignJsonFeedParser::parseContent($emailContent);
            }

            $emailData  = CampaignHelper::parseContent($emailContent, $campaign, $subscriber, false);
            list(, $_emailSubject, $emailContent) = $emailData;
                
            $emailSubject .= ' - ' . $_emailSubject;
        }
        
        if ($onlyPlainText) {
            $emailContent = preg_replace('%<br(\s{0,}?/?)?>%i', "\n", $emailContent);
        }
        
        foreach ($emails as $email) {

            $params = array(
                'to'            => $email,
                'subject'       => $emailSubject,
                'body'          => $onlyPlainText ? null : $emailContent,
                'plainText'     => $onlyPlainText ? $emailContent : null,
                'embedImages'   => $embedImages,  
                'onlyPlainText' => $onlyPlainText,              
            );
            
            $sent = false;
            for ($i = 0; $i < 3; ++$i) {
                if ($sent = $server->setDeliveryFor(DeliveryServer::DELIVERY_FOR_CAMPAIGN_TEST)->setDeliveryObject($campaign)->sendEmail($params)) {
                    break;
                }
                sleep(1);
                $server = DeliveryServer::pickServer($server->server_id, $campaign);    
            }
            
            if (!$sent) {
                $notify->addError(Yii::t('campaigns', 'Unable to send the test email to {email}!', array(
                    '{email}' => CHtml::encode($email),
                )));
            } else {
                $notify->addSuccess(Yii::t('campaigns', 'Test email successfully sent to {email}!', array(
                    '{email}' => CHtml::encode($email),
                )));
            }
        }
        
        $this->redirect(array('campaigns/template', 'campaign_uid' => $campaign_uid));
    }
    
    /**
     * List available list segments when choosing a list for a campaign
     */
    public function actionList_segments($list_id)
    {
        $request = Yii::app()->request;
        if (!$request->isAjaxRequest) {
            $this->redirect(array('campaigns/index'));
        }
        
        $list = Lists::model()->findByAttributes(array(
            'list_id'       => (int)$list_id,
            'customer_id'   => (int)Yii::app()->customer->getId(),
        ));
        if (empty($list)){
            return $this->renderJson(array('segments' => array()));
        }
        
        $campaign = new Campaign();
        $campaign->list_id = $list->list_id;
        $segments = $campaign->getSegmentsDropDownArray();

        $json = array();
        $json[] = array(
            'segment_id'    => '',
            'name'          => Yii::t('app', 'Choose')
        );
        
        foreach ($segments as $segment_id => $name) {
            $json[] = array(
                'segment_id' => $segment_id, 
                'name'       => CHtml::encode($name)
            );
        }
        
        return $this->renderJson(array('segments' => $json));
    }
    
    /**
     * Copy campaign
     */
    public function actionCopy($campaign_uid)
    {
        $campaign = $this->loadCampaignModel($campaign_uid);
        $list     = $campaign->list;
        $customer = $list->customer;
        $canCopy  = true;
        $request  = Yii::app()->request;
        $notify   = Yii::app()->notify;
        
        if (($maxCampaigns = (int)$customer->getGroupOption('campaigns.max_campaigns', -1)) > -1) {
            $criteria = new CDbCriteria();
            $criteria->with = array(
                'list' => array(
                    'joinType'  => 'INNER JOIN',
                    'together'  => true,
                    'condition' => 'list.customer_id = :cid',
                    'params'    => array(':cid' => $customer->customer_id),
                ),
            );
            $campaignsCount = Campaign::model()->count($criteria);
            if ($campaignsCount >= $maxCampaigns) {
                $notify->addWarning(Yii::t('lists', 'You have reached the maximum number of allowed campaigns.'));
                $canCopy = false;
            }
        }
        
        $copied = false;
        if ($canCopy) {
            $copied = $campaign->copy();
        }

        if (!$request->isAjaxRequest) {
            if ($copied) {
                $notify->addSuccess(Yii::t('campaigns', 'Your campaign was successfully copied!'));
            }
            $this->redirect($request->getPost('returnUrl', array('campaigns/index')));
        }
    }
    
    /**
     * Delete campaign, will remove all campaign related data
     */
    public function actionDelete($campaign_uid)
    {
        $campaign = $this->loadCampaignModel($campaign_uid);
        
        if ($campaign->removable) {
            
            set_time_limit(0);
            ignore_user_abort(true);
        
            $campaign->delete();
            
            if ($logAction = Yii::app()->customer->getModel()->asa('logAction')) {
                $logAction->campaignDeleted($campaign);    
            }    
        }
        
        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('campaigns', 'Your campaign was successfully deleted!'));
            $this->redirect($request->getPost('returnUrl', array('campaigns/index')));
        }
    }
    
    /**
     * Allows to pause/unpause the sending of a campaign
     */
    public function actionPause_unpause($campaign_uid)
    {
        $campaign = $this->loadCampaignModel($campaign_uid);
        
        $campaign->pauseUnpause();
        
        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('campaigns', 'Your campaign was successfully changed!'));
            $this->redirect($request->getPost('returnUrl', array('campaigns/index')));
        }
    }
    
    /**
     * Allows to resume sending of a stuck campaign
     */
    public function actionResume_sending($campaign_uid)
    {
        $campaign = $this->loadCampaignModel($campaign_uid);
        
        if ($campaign->isProcessing) {
            $campaign->status = Campaign::STATUS_SENDING;
            $campaign->save(false);
        }
        
        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        
        if (!$request->isAjaxRequest) {
            $notify->addSuccess(Yii::t('campaigns', 'Your campaign was successfully changed!'));
            $this->redirect($request->getPost('returnUrl', array('campaigns/index')));
        }
    }
    
    /**
     * Tries to get the spam score for campaign.
     * For now this is in beta usage, need to see how it behaves.
     * 
     */
    public function actionSpamcheck($campaign_uid)
    {
        $campaign = $this->loadCampaignModel($campaign_uid);
        
        $score = CampaignHelper::getSpamScore($campaign);
        
        if (!is_array($score) || !isset($score['score'])) {
            return $this->renderJson(array(
                'result' => 'error',
                'message'=> Yii::t('campaigns', 'Unable to get the spam score for your campaign!')
            ));
        }
        
        $message = Yii::t('campaigns', 'Between 0 and 5, your campaign spam score is {score}.', array(
            '{score}' => $score['score'],
        ));
                
        return $this->renderJson(array(
            'result'    => 'success',
            'message'   => $message,
            'response'  => $score,
        ));
    }
    
    /**
     * Remove certain campaign attachment
     */
    public function actionRemove_attachment($campaign_uid, $attachment_id)
    {
        $campaign = $this->loadCampaignModel($campaign_uid);
        $attachment = CampaignAttachment::model()->findByAttributes(array(
            'attachment_id' => (int)$attachment_id,
            'campaign_id'   => (int)$campaign->campaign_id,
        ));
        
        if (!empty($attachment)) {
            $attachment->delete();
        }
        
        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        
        if (!$request->isAjaxRequest) {
            $notify->addSuccess(Yii::t('campaigns', 'Your campaign attachment was successfully removed!'));
            $this->redirect($request->getPost('returnUrl', array('campaigns/index')));
        }
    }
    
    public function actionSync_datetime()
    {
        $customer   = Yii::app()->customer->getModel();
        $request    = Yii::app()->request;
        
        $timeZoneDateTime   = date('Y-m-d H:i:s', strtotime($request->getQuery('date', date('Y-m-d H:i:s'))));
        $timeZoneTimestamp  = strtotime($timeZoneDateTime);
        $localeDateTime     = Yii::app()->dateFormatter->formatDateTime($timeZoneTimestamp, 'short', 'short');

        // since the date is already in customer timezone we need to convert it back to utc
        $sourceTimeZone      = new DateTimeZone($customer->timezone);
        $destinationTimeZone = new DateTimeZone(Yii::app()->timeZone);
        $dateTime            = new DateTime($timeZoneDateTime, $sourceTimeZone);
        $dateTime->setTimezone($destinationTimeZone);
        $utcDateTime = $dateTime->format('Y-m-d H:i:s');

        return $this->renderJson(array(
            'localeDateTime'  => $localeDateTime,
            'utcDateTime'     => $utcDateTime,
        ));
    }
    
    /**
     * Helper method to load the campaign AR model
     */
    public function loadCampaignModel($campaign_uid)
    {
        $model = Campaign::model()->findByAttributes(array(
            'customer_id'   => (int)Yii::app()->customer->getId(),
            'campaign_uid'  => $campaign_uid,
        ));
        
        if($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        return $model;
    }
    
    /**
     * Callback method to setup the editor for the template step
     */
    public function _setEditorOptions(CEvent $event)
    {
        if ($event->params['attribute'] == 'content') {
            $options = array();
            if ($event->params['htmlOptions']->contains('wysiwyg_editor_options')) {
                $options = (array)$event->params['htmlOptions']->itemAt('wysiwyg_editor_options');
            }
            $options['id'] = CHtml::activeId($event->sender->owner, 'content');
            $options['fullPage'] = true;
            $options['allowedContent'] = true;
            $options['contentsCss'] = array();
            $options['height'] = 500;
            
            $event->params['htmlOptions']->add('wysiwyg_editor_options', $options);
        }
    }
}