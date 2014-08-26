<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * TemplatesController
 * 
 * Handles the actions for templates related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class TemplatesController extends Controller
{
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('templates.js')));
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
     * List available templates
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;

        $criteria = new CDbCriteria();
        $criteria->compare('customer_id', (int)Yii::app()->customer->getId());
        $criteria->order = 't.template_id DESC';
        
        $count = CustomerEmailTemplate::model()->count($criteria);
        
        $pages = new CPagination($count);
        $pages->pageSize = 30;
        $pages->applyLimit($criteria);
        
        $templates = CustomerEmailTemplate::model()->findAll($criteria);

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle.' | '.Yii::t('email_templates',  'Your email templates'), 
            'pageHeading'       => Yii::t('email_templates',  'Email templates'),
            'pageBreadcrumbs'   => array(
                Yii::t('email_templates',  'Email templates') => $this->createUrl('templates/index'),
                Yii::t('app', 'View all')
            )
        ));
        
        $templateUp = new CustomerEmailTemplate('upload');
        
        $this->render('list', compact('templates', 'templateUp', 'pages'));
    }
    
    /**
     * Create a new template
     */
    public function actionCreate()
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $customer   = Yii::app()->customer->getModel();
        
        $template = new CustomerEmailTemplate();
        $template->customer_id = (int)Yii::app()->customer->getId();
        
        if ($request->isPostRequest && ($attributes = $request->getPost($template->modelName, array()))) {
            $template->attributes = $attributes;

            $parser = new EmailTemplateParser();
            $parser->inlineCss = $template->inline_css === CustomerEmailTemplate::TEXT_YES;
            $parser->minify    = $template->minify === CustomerEmailTemplate::TEXT_YES;
            $template->content = $parser->setContent(Yii::app()->params['POST'][$template->modelName]['content'])->getContent();
            
            if ($template->save()) {
                $notify->addSuccess(Yii::t('email_templates',  'You successfully created a new email template!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'    => $this,
                'success'       => $notify->hasSuccess,
                'template'      => $template,
            )));
            
            if ($collection->success) {
                $this->redirect(array('templates/update', 'template_uid' => $template->template_uid));
            }
        }
        
        $template->fieldDecorator->onHtmlOptionsSetup = array($this, '_setDefaultEditorForContent');
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle.' | '.Yii::t('email_templates',  'Create a new email template'),
            'pageHeading'       => Yii::t('email_templates',  'Create a new email template'),
            'pageBreadcrumbs'   => array(
                Yii::t('email_templates',  'Email templates') => $this->createUrl('templates/index'),
                Yii::t('app', 'Create new')
            )
        ));
        
        $this->render('form', compact('template'));
    }
    
    /**
     * Update existing template
     */
    public function actionUpdate($template_uid)
    {
        $template   = $this->loadModel($template_uid);
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;

        if ($request->isPostRequest && $attributes = $request->getPost($template->modelName, array())) {
            $template->attributes = $attributes;
            
            $parser = new EmailTemplateParser();
            $parser->inlineCss = $template->inline_css === CustomerEmailTemplate::TEXT_YES;
            $parser->minify    = $template->minify === CustomerEmailTemplate::TEXT_YES;
            $template->content = $parser->setContent(Yii::app()->params['POST'][$template->modelName]['content'])->getContent();
            
            if ($template->save()) {
                $notify->addSuccess(Yii::t('email_templates',  'You successfully updated your email template!'));
            }
            
            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'    => $this,
                'success'       => $notify->hasSuccess,
                'template'      => $template,
            )));
            
            if ($collection->success) {
                $this->redirect(array('templates/update', 'template_uid' => $template->template_uid));
            }
        }
        
        $template->fieldDecorator->onHtmlOptionsSetup = array($this, '_setDefaultEditorForContent');
        $this->data->previewUrl = $this->createUrl('templates/preview', array('template_uid' => $template_uid));
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle.' | '.Yii::t('email_templates',  'Update email template'),
            'pageHeading'       => Yii::t('email_templates',  'Update email template'), 
            'pageBreadcrumbs'   => array(
                Yii::t('email_templates',  'Email templates') => $this->createUrl('templates/index'),
                Yii::t('app', 'Update')
            )
        ));
        
        $this->render('form', compact('template'));
    }
    
    /**
     * Preview template
     */
    public function actionPreview($template_uid)
    {
        $template   = $this->loadModel($template_uid);
        $request    = Yii::app()->request;
        
        $cs = Yii::app()->clientScript;
        $cs->reset();
        $cs->registerCoreScript('jquery');
        
        if ($template->create_screenshot === CustomerEmailTemplate::TEXT_YES) {

            if (Yii::app()->request->enableCsrfValidation) {
                $cs->registerMetaTag($request->csrfTokenName, 'csrf-token-name');
                $cs->registerMetaTag($request->csrfToken, 'csrf-token-value');
            }
            
            $cs->registerMetaTag($this->createUrl('templates/save_screenshot', array('template_uid' => $template_uid)), 'save-screenshot-url');
            $cs->registerMetaTag(Yii::t('email_templates',  'Please wait while saving your template screenshot...'), 'wait-message');
            $cs->registerScriptFile(AssetsUrl::js('html2canvas/html2canvas.min.js'));  
        }
        
        $cs->registerScriptFile(AssetsUrl::js('template-preview.js'));
        
        $this->renderPartial('preview', compact('template'), false, true);
    }
    
    /**
     * Save template screenshot
     */
    public function actionSave_screenshot($template_uid)
    {
        $request = Yii::app()->request;
        if (!$request->isPostRequest) {
           Yii::app()->end();
        }
        
        $template = $this->loadModel($template_uid);
        
        if ($template->create_screenshot !== CustomerEmailTemplate::TEXT_YES) {
           Yii::app()->end();
        }
        
        $data = null;
        
        // in case it takes to much.
        set_time_limit(0);
        
        // in case the user closes the popup!
        ignore_user_abort(true);
        
        if (isset(Yii::app()->params['POST']['data'])) {
            $data = Yii::app()->ioFilter->purify(Yii::app()->params['POST']['data']);
        }
        
        if (empty($data) || strpos($data, 'data:image/png;base64,') !== 0) {
           Yii::app()->end();
        }
        
        $base64img = str_replace('data:image/png;base64,', '', $data);
        if (!($image = base64_decode($base64img))) {
           Yii::app()->end();
        }
        
        $baseDir = Yii::getPathOfAlias('root.frontend.assets.gallery.'.$template_uid);
        if((!file_exists($baseDir) && !@mkdir($baseDir, 0777, true)) || (!@is_writable($baseDir) && !@chmod($baseDir, 0777))){
           Yii::app()->end();
        }
        
        $destination = $baseDir.'/'.$template_uid.'.png';
        file_put_contents($destination, $image);
        
        if (!($info = @getimagesize($destination))) {
            @unlink($destination);
        }
        
        $template->screenshot = '/frontend/assets/gallery/' . $template_uid . '/' . $template_uid . '.png';
        $template->create_screenshot = CustomerEmailTemplate::TEXT_NO;
        $template->save(false);

       Yii::app()->end();
    }
    
    /**
     * Upload a template zip archive
     */
    public function actionUpload()
    {
        $model = new CustomerEmailTemplate('upload');
        $model->customer_id = (int)Yii::app()->customer->getId();
        
        $request = Yii::app()->request;
        $redirect = array('templates/index');
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($model->modelName, array()))) {
            $model->attributes = $attributes;
            $model->archive = CUploadedFile::getInstance($model, 'archive');
            if (!$model->validate() || !$model->uploader->handleUpload()) {
                Yii::app()->notify->addError($model->shortErrors->getAllAsString());
            } else {
                Yii::app()->notify->addSuccess(Yii::t('app', 'Your file has been successfully uploaded!'));
                $redirect = array('templates/update', 'template_uid' => $model->template_uid);
            }
            $this->redirect($redirect);
          }
          
         Yii::app()->notify->addError(Yii::t('app', 'Please select a file for upload!'));
         $this->redirect($redirect);
    }
    
    /**
     * Test the template by sending an email
     */
    public function actionTest($template_uid)
    {
        $template   = $this->loadModel($template_uid);
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        
        if (!$request->getPost('email')) {
            $notify->addError(Yii::t('email_templates',  'Please specify the email address to where we should send the test email.'));
            $this->redirect(array('templates/update', 'template_uid' => $template_uid));
        }
        
        $emails = explode(',', $request->getPost('email'));
        $emails = array_map('trim', $emails);
        $emails = array_unique($emails);
        $emails = array_slice($emails, 0, 10);
        
        $server = DeliveryServer::pickServer(0, $template);
        if (empty($server)) {
            $notify->addError(Yii::t('email_templates',  'Email delivery is temporary disabled.'));
            $this->redirect(array('templates/update', 'template_uid' => $template_uid));
        }
        
        foreach ($emails as $index => $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $notify->addError(Yii::t('email_templates',  'The email address {email} does not seem to be valid!', array('{email}' => CHtml::encode($email))));
                unset($emails[$index]);
                continue;
            }
        }
        
        if (empty($emails)) {
            $notify->addError(Yii::t('email_templates',  'Cannot send using provided email address(es)!'));
            $this->redirect(array('templates/update', 'template_uid' => $template_uid));
        }
        
        $customer = Yii::app()->customer->getModel();
        $fromName = $customer->getFullName();
        
        if (!empty($customer->company)) {
            $fromName = $customer->company->name;
        }
        
        if (empty($fromName)) {
            $fromName = $customer->email;
        }
        
        foreach ($emails as $email) {
            $params = array(
                'to'        => $email,
                'fromName'  => $fromName,
                'subject'   => '[TEST TEMPLATE] '.$template->name,
                'body'      => $template->content,
            );
            
            $sent = false;
            for ($i = 0; $i < 3; ++$i) {
                if ($sent = $server->setDeliveryFor(DeliveryServer::DELIVERY_FOR_TEMPLATE_TEST)->setDeliveryObject($template)->sendEmail($params)) {
                    break;
                }
                $server = DeliveryServer::pickServer($server->server_id, $template);    
            }
            
            if (!$sent) {
                $notify->addError(Yii::t('email_templates',  'Unable to send the test email to {email}!', array(
                    '{email}' => CHtml::encode($email),
                )));
            } else {
                $notify->addSuccess(Yii::t('email_templates',  'Test email successfully sent to {email}!', array(
                    '{email}' => CHtml::encode($email),
                )));
            }
        }
        
        $this->redirect(array('templates/update', 'template_uid' => $template_uid));
    }
    
    /**
     * Delete existing template
     * Template files are also deleted
     */
    public function actionDelete($template_uid)
    {
        $template = $this->loadModel($template_uid);
        
        $template->delete();

        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        
        if (!$request->isAjaxRequest) {
            $notify->addSuccess(Yii::t('email_templates',  'Your template was successfully deleted!'));
            $this->redirect($request->getPost('returnUrl', array('templates/index')));
        }
    }
    
    /**
     * Helper method to load the email template AR model
     */
    public function loadModel($template_uid)
    {
        $model = CustomerEmailTemplate::model()->findByAttributes(array(
            'template_uid'  => $template_uid,
            'customer_id'   => (int)Yii::app()->customer->getId(),
        ));
        
        if($model === null) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        return $model;
    }
    
    /**
     * Callback to setup the editor for creating/updating the template
     */
    public function _setDefaultEditorForContent(CEvent $event)
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
            $options['height'] = 800;
            
            $event->params['htmlOptions']->add('wysiwyg_editor_options', $options);
        }
    }
}