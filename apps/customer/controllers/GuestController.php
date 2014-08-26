<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * GuestController
 * 
 * Handles the actions for guest related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class GuestController extends Controller
{
    public $layout = 'guest';
    
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('guest.js')));
        parent::init();    
    }
    
    /**
     * Display the login form
     */
    public function actionIndex()
    {
        $model   = new CustomerLogin();
        $request = Yii::app()->request;
        $options = Yii::app()->options;
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($model->modelName, array()))) {
            $model->attributes = $attributes;
            if ($model->validate()) {
                $this->redirect(Yii::app()->customer->returnUrl);
            }
        }
        
        $registrationEnabled = $options->get('system.customer_registration.enabled', 'no') == 'yes';
        
        $this->setData(array(
            'pageMetaTitle' => $this->data->pageMetaTitle . ' | '. Yii::t('customers', 'Please login'), 
            'pageHeading'   => Yii::t('customers', 'Please login'),
        ));
        
        $this->render('login', compact('model', 'registrationEnabled'));
    }
    
    /**
     * Display the registration form
     */
    public function actionRegister()
    {
        $options = Yii::app()->options;
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $model   = new Customer('register');
        $company = new CustomerCompany('register');
        
        if ($options->get('system.customer_registration.enabled', 'no') != 'yes') {
            $this->redirect(array('guest/index'));
        }
        
        $companyRequired = $options->get('system.customer_registration.company_required', 'no') == 'yes';

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($model->modelName, array()))) {
            $model->attributes = $attributes;
            $model->status = Customer::STATUS_PENDING_CONFIRM;
            
            $transaction = Yii::app()->getDb()->beginTransaction();
            
            try {
                if (!$model->save()) {
                    throw new Exception(CHtml::errorSummary($model));
                }
                if ($companyRequired) {
                    $company->attributes  = (array)$request->getPost($company->modelName, array());
                    $company->customer_id = $model->customer_id;
                    if (!$company->save()) {
                        throw new Exception(CHtml::errorSummary($company));
                    }
                }
                $this->_sendRegistrationConfirmationEmail($model, $company);
                if ($notify->isEmpty) {
                    $notify->addSuccess(Yii::t('customers', 'Congratulations, your account has been created, please check your email address for confirmation!'));
                }
                $transaction->commit();
                $this->redirect(array('guest/index'));
            } catch (Exception $e) {
                $transaction->rollBack();
            }
        }
        
        $this->setData(array(
            'pageMetaTitle' => $this->data->pageMetaTitle . ' | '. Yii::t('customers', 'Please register'), 
            'pageHeading'   => Yii::t('customers', 'Please register'),
        ));
        
        $this->render('register', compact('model', 'company', 'companyRequired'));
    }
    
    public function actionConfirm_registration($key)
    {
        $options = Yii::app()->options;
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        
        $model = Customer::model()->findByAttributes(array(
            'confirmation_key' => $key,
            'status'           => Customer::STATUS_PENDING_CONFIRM,
        ));
        
        if (empty($model)) {
            $this->redirect(array('guest/index'));
        }
        
        if (($defaultGroup = (int)$options->get('system.customer_registration.default_group')) > 0) {
            $group = CustomerGroup::model()->findByPk((int)$defaultGroup);
            if (!empty($group)) {
                $model->group_id = $group->group_id;
            }
        }
        
        $requireApproval = $options->get('system.customer_registration.require_approval', 'no') == 'yes';
        $model->status   = !$requireApproval ? Customer::STATUS_ACTIVE : Customer::STATUS_PENDING_ACTIVE;
        if (!$model->save(false)) {
            $this->redirect(array('guest/index'));
        }
        
        if ($requireApproval) {
            $notify->addSuccess(Yii::t('customers', 'Congratulations, you have successfully confirmed your account.'));
            $notify->addSuccess(Yii::t('customers', 'You will be able to login once an administrator will approve it.'));
            $this->redirect(array('guest/index'));
        }
        
        $identity = new CustomerIdentity($model->email, $model->password);
        $identity->setId($model->customer_id)->setAutoLoginToken($model);
        
        if (!Yii::app()->customer->login($identity, 3600 * 24 * 30)) {
            $this->redirect(array('guest/index'));
        }
        
        $notify->addSuccess(Yii::t('customers', 'Congratulations, your account is now ready to use.'));
        $notify->addSuccess(Yii::t('customers', 'Please start by filling your account and company info.'));
        $this->redirect(array('account/index'));
    }
    
    /**
     * Display the "Forgot password" form
     */
    public function actionForgot_password()
    {
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $model      = new CustomerPasswordReset();
        
        if ($request->isPostRequest && ($attributes = (array)$request->getPost($model->modelName, array()))) {
            $model->attributes = $attributes;
            if (!$model->validate()) {
                $notify->addError(Yii::t('app', 'Please fix your form errors!'));
            } else {
                $options = Yii::app()->options;
                $customer = Customer::model()->findByAttributes(array('email' => $model->email));
                $model->customer_id = $customer->customer_id;
                $model->save(false);

                $emailTemplate    = $options->get('system.email_templates.common');
                $emailBody        = $this->renderPartial('_email-reset-key', compact('model', 'customer'), true);
                $emailTemplate    = str_replace('[CONTENT]', $emailBody, $emailTemplate);
                
                $email = new TransactionalEmail();
                $email->to_name     = $customer->getFullName();
                $email->to_email    = $customer->email;
                $email->from_name   = $options->get('system.common.site_name', 'Marketing website');
                $email->subject     = Yii::t('customers', 'Password reset request!');
                $email->body        = $emailTemplate;
                $email->save();
        
                $notify->addSuccess(Yii::t('app', 'Please check your email address.'));
                $model->unsetAttributes();
                $model->email = null;
            }
        }
        
        $this->setData(array(
            'pageMetaTitle' => $this->data->pageMetaTitle . ' | '. Yii::t('customers', 'Retrieve a new password for your account.'),
            'pageHeading'   => Yii::t('customers', 'Retrieve a new password for your account.'), 
        ));

        $this->render('forgot_password', compact('model'));
    }
    
    /**
     * Reached from email, will reset the password for given user and send a new one via email.
     */
    public function actionReset_password($reset_key)
    {
        $model = CustomerPasswordReset::model()->findByAttributes(array(
            'reset_key' => $reset_key,
            'status'    => CustomerPasswordReset::STATUS_ACTIVE,
        ));
        
        if (empty($model)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $randPassword = StringHelper::random();
        $hashedPassword = Yii::app()->passwordHasher->hash($randPassword);
        
        Customer::model()->updateByPk((int)$model->customer_id, array('password' => $hashedPassword));
        $model->status = CustomerPasswordReset::STATUS_USED;
        $model->save();
        
        $options    = Yii::app()->options;
        $request    = Yii::app()->request;
        $notify     = Yii::app()->notify;
        $customer   = Customer::model()->findByPk($model->customer_id);
        $currentPassword = $customer->password;

        $emailTemplate  = $options->get('system.email_templates.common');
        $emailBody      = $this->renderPartial('_email-new-login', compact('model', 'customer', 'randPassword'), true);
        $emailTemplate  = str_replace('[CONTENT]', $emailBody, $emailTemplate);
        
        $email = new TransactionalEmail();
        $email->to_name     = $customer->getFullName();
        $email->to_email    = $customer->email;
        $email->from_name   = $options->get('system.common.site_name', 'Marketing website');
        $email->subject     = Yii::t('app', 'Your new login info!');
        $email->body        = $emailTemplate;
        $email->save();
        
        $notify->addSuccess(Yii::t('app', 'Your new login has been successfully sent to your email address.'));
        $this->redirect(array('guest/index'));
    }
    
    /**
     * Display country zones
     */
    public function actionZones_by_country()
    {
        $request = Yii::app()->request;
        if (!$request->isAjaxRequest) {
            $this->redirect(array('guest/index'));
        }
        
        $criteria = new CDbCriteria();
        $criteria->select = 'zone_id, name';
        $criteria->compare('country_id', (int)$request->getQuery('country_id'));
        $models = Zone::model()->findAll($criteria);
        
        $zones = array();
        foreach ($models as $model) {
            $zones[] = array(
                'zone_id'  => $model->zone_id, 
                'name'     => $model->name
            );
        }
        return $this->renderJson(array('zones' => $zones));
    }
    
    /**
     * Callback after success registration to send the confirmation email
     */
    protected function _sendRegistrationConfirmationEmail(Customer $customer, CustomerCompany $company)
    {
        $options  = Yii::app()->options;
        $notify   = Yii::app()->notify;
        
        if ($options->get('system.customer_registration.company_required', 'no') == 'yes' && $company->isNewRecord) {
            return;
        }
  
        $emailTemplate  = $options->get('system.email_templates.common');
        $emailBody      = $this->renderPartial('_email-registration-key', compact('customer'), true);
        $emailTemplate  = str_replace('[CONTENT]', $emailBody, $emailTemplate);
        
        $email = new TransactionalEmail();
        $email->to_name     = $customer->getFullName();
        $email->to_email    = $customer->email;
        $email->from_name   = $options->get('system.common.site_name', 'Marketing website');
        $email->subject     = Yii::t('customers', 'Please confirm your account!');
        $email->body        = $emailTemplate;
        $email->save();
    }
    
    /**
     * Called when the application is offline
     */
    public function actionOffline()
    {
        if (Yii::app()->options->get('system.common.site_status') !== 'offline') {
            $this->redirect(array('dashboard/index'));
        }
        
        throw new CHttpException(503, Yii::app()->options->get('system.common.site_offline_message'));
    }
    
    /**
     * The error handler
     */
    public function actionError()
    {
        if ($error = Yii::app()->errorHandler->error) {
            if (Yii::app()->request->isAjaxRequest) {
                echo CHtml::encode($error['message']);
            } else {
                $this->setData(array(
                    'pageMetaTitle' => Yii::t('app', 'Error {code}!', array('{code}' => $error['code'])), 
                ));
                $this->render('error', $error) ;
            }    
        }
    }
}