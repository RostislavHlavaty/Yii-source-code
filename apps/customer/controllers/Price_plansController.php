<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Price_plansController
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
 
class Price_plansController extends Controller
{
    public function init()
    {
        if (Yii::app()->options->get('system.monetization.monetization.enabled', 'no') == 'no') {
            $this->redirect(array('dashboard/index'));
        }
        
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('price-plans.js')));
        parent::init();
    }
    
    /**
     * List all available price plans
     */
    public function actionIndex()
    {
        $session = Yii::app()->session;
        $session->remove('payment_gateway');
        $session->remove('plan_uid');
        $session->remove('currency_code');
        $session->remove('promo_code');
        
        $pricePlans = PricePlan::model()->findAllByAttributes(array(
            'status' => PricePlan::STATUS_ACTIVE
        ));
        
        $customer       = Yii::app()->customer->getModel();
        $paymentMethods = array('' => Yii::t('app', 'Choose'));
        $paymentMethods = (array)Yii::app()->hooks->applyFilters('customer_price_plans_payment_methods_dropdown', $paymentMethods);
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('price_plans', 'View price plans'),
            'pageHeading'       => Yii::t('price_plans', 'View price plans'),
            'pageBreadcrumbs'   => array(
                Yii::t('price_plans', 'Price plans') => $this->createUrl('price_plans/index'),
                Yii::t('app', 'View all')
            )
        ));

        $this->render('list', compact('pricePlans', 'customer', 'paymentMethods'));
    }
    
    public function actionOrders()
    {
        $request = Yii::app()->request;
        $order   = new PricePlanOrder('customer-search');
        
        $order->unsetAttributes();
        $order->attributes = (array)$request->getQuery($order->modelName, array());
        $order->customer_id = (int)Yii::app()->customer->getId();
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('orders', 'View your orders'),
            'pageHeading'       => Yii::t('orders', 'View your orders'),
            'pageBreadcrumbs'   => array(
                Yii::t('price_plans', 'Price plans') => $this->createUrl('price_plans/index'),
                Yii::t('orders', 'Orders') => $this->createUrl('price_plans/orders'),
                Yii::t('app', 'View all')
            )
        ));

        $this->render('orders', compact('order'));
    }
    
    public function actionOrder_detail($order_uid)
    {
        $request = Yii::app()->request;
        $order   = PricePlanOrder::model()->findByAttributes(array(
            'order_uid'   => $order_uid,
            'customer_id' => Yii::app()->customer->getId(),
        ));
        
        if (empty($order)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
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
        
        $this->render('order_detail', compact('order', 'note', 'transaction'));
    }
    
    public function actionPayment()
    {
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $session = Yii::app()->session;

        $planUid        = $request->getPost('plan_uid', $session->itemAt('plan_uid'));
        $paymentGateway = $request->getPost('payment_gateway', $session->itemAt('payment_gateway'));

        if (empty($planUid) || empty($paymentGateway)) {
            $this->redirect(array('price_plans/index'));
        }
        
        $extensionsManager = Yii::app()->extensionsManager;
        $extensionInstance = $extensionsManager->getExtensionInstance('payment-gateway-' . $paymentGateway);
        
        if (empty($extensionInstance)) {
            $notify->addError(Yii::t('price_plans', 'Unable to load the payment gateway!'));
            $this->redirect(array('price_plans/index'));
        }

        if (!method_exists($extensionInstance, 'getPaymentHandler')) {
            $notify->addError(Yii::t('price_plans', 'Invalid payment gateway setup!'));
            $this->redirect(array('price_plans/index'));
        }
        
        $paymentHandler = $extensionInstance->getPaymentHandler();
        if (!is_object($paymentHandler) || !($paymentHandler instanceof PaymentHandlerAbstract)) {
            $notify->addError(Yii::t('price_plans', 'Invalid payment gateway setup!'));
            $this->redirect(array('price_plans/index'));
        } 
        $paymentHandler->controller = $this;
        $paymentHandler->extension  = $extensionInstance;
        
        $pricePlan = PricePlan::model()->findByAttributes(array(
            'plan_uid' => $planUid,
            'status'   => PricePlan::STATUS_ACTIVE,
        ));
        
        if (empty($pricePlan)) {
            $notify->addError(Yii::t('price_plans', 'The specified price plan is invalid!'));
            $this->redirect(array('price_plans/index'));
        }

        $currency = Currency::model()->findDefault();
        if (empty($currency)) {
            $notify->addError(Yii::t('price_plans', 'Unable to set a correct curency!'));
            $this->redirect(array('price_plans/index'));
        }

        $session->add('payment_gateway', $paymentGateway);
        $session->add('plan_uid', $pricePlan->plan_uid);
        $session->add('currency_code', $currency->code);
        
        $promoCode = $session->itemAt('promo_code');
        if (!empty($promoCode)) {
            $promoCodeModel = PricePlanPromoCode::model()->findByAttributes(array('code' => $promoCode));
        }
        
        $note     = new PricePlanOrderNote();
        $customer = Yii::app()->customer->getModel();
        
        $this->setData(array(
            'extension'         => $extensionInstance,
            'customer'          => $customer,
            'paymentGateway'    => $paymentGateway,
            'paymentHandler'    => $paymentHandler,
            'promoCode'         => $promoCode,
            'note'              => $note,
        ));

        $order = new PricePlanOrder();
        $order->customer_id     = $customer->customer_id;
        $order->plan_id         = $pricePlan->plan_id;
        $order->promo_code_id   = !empty($promoCodeModel) ? $promoCodeModel->promo_code_id : null;
        $order->currency_id     = $currency->currency_id;
        
        $order->addRelatedRecord('customer', $customer, false);
        $order->addRelatedRecord('plan', $pricePlan, false);
        $order->addRelatedRecord('currency', $currency, false);
        if ($order->promo_code_id) {
            $order->addRelatedRecord('promoCode', $promoCodeModel, false);
        }

        $this->setData('order', $order->calculate());

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('price_plans', 'Price plans payment'),
            'pageHeading'       => Yii::t('price_plans', 'Price plans payment'),
            'pageBreadcrumbs'   => array(
                Yii::t('price_plans', 'Price plans') => $this->createUrl('price_plans/index'),
                Yii::t('app', 'Payment')
            )
        ));
        
        $this->render('payment');
    }
    
    public function actionOrder()
    {
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $session = Yii::app()->session;
        
        if (!$request->isPostRequest) {
            $this->redirect(array('price_plans/payment'));
        }
        
        if (!$session->contains('payment_gateway') || !$session->contains('plan_uid') || !$session->contains('currency_code')) {
            $message = Yii::t('price_plans', 'Unable to load payment data!');
            if ($request->isAjaxRequest) {
                return $this->renderJson(array(
                    'result'  => 'error', 
                    'message' => $message,
                ));
            }
            $notify->addError($message);
            $this->redirect(array('price_plans/payment'));
        }
        
        $paymentGateway = $session->itemAt('payment_gateway');
        $planUid        = $session->itemAt('plan_uid');
        $currencyCode   = $session->itemAt('currency_code');
        $promoCode      = $session->itemAt('promo_code');

        $extensionsManager = Yii::app()->extensionsManager;
        $extensionInstance = $extensionsManager->getExtensionInstance('payment-gateway-' . $paymentGateway);
        
        if (empty($extensionInstance)) {
            $message = Yii::t('price_plans', 'Unable to load the payment gateway!');
            if ($request->isAjaxRequest) {
                return $this->renderJson(array(
                    'result'  => 'error', 
                    'message' => $message,
                ));
            }
            $notify->addError($message);
            $this->redirect(array('price_plans/payment'));
        }

        if (!method_exists($extensionInstance, 'getPaymentHandler')) {
            $message = Yii::t('price_plans', 'Invalid payment gateway setup!');
            if ($request->isAjaxRequest) {
                return $this->renderJson(array(
                    'result'  => 'error', 
                    'message' => $message,
                ));
            }
            $notify->addError($message);
            $this->redirect(array('price_plans/payment'));
        }
        
        $reflection = new ReflectionMethod($extensionInstance, 'getPaymentHandler');
        if (!$reflection->isPublic()) {
            $message = Yii::t('price_plans', 'Invalid payment gateway setup!');
            if ($request->isAjaxRequest) {
                return $this->renderJson(array(
                    'result'  => 'error', 
                    'message' => $message,
                ));
            }
            $notify->addError($message);
            $this->redirect(array('price_plans/payment'));
        }
        
        $paymentHandler = $extensionInstance->getPaymentHandler();
        if (!is_object($paymentHandler) || !($paymentHandler instanceof PaymentHandlerAbstract)) {
            $message = Yii::t('price_plans', 'Invalid payment gateway setup!');
            if ($request->isAjaxRequest) {
                return $this->renderJson(array(
                    'result'  => 'error', 
                    'message' => $message,
                ));
            }
            $notify->addError($message);
            $this->redirect(array('price_plans/payment'));
        } 
        $paymentHandler->controller = $this;
        $paymentHandler->extension  = $extensionInstance;
        
        $pricePlan = PricePlan::model()->findByUid($planUid);
        if (empty($pricePlan)) {
            $message = Yii::t('price_plans', 'The specified price plan is invalid!');
            if ($request->isAjaxRequest) {
                return $this->renderJson(array(
                    'result'  => 'error', 
                    'message' => $message,
                ));
            }
            $notify->addError($message);
            $this->redirect(array('price_plans/payment'));
        }

        $currency = Currency::model()->findByCode($currencyCode);
        if (empty($currency)) {
            $message = Yii::t('price_plans', 'Invalid currency specified!');
            if ($request->isAjaxRequest) {
                return $this->renderJson(array(
                    'result'  => 'error', 
                    'message' => $message,
                ));
            }
            $notify->addError($message);
            $this->redirect(array('price_plans/payment'));
        }

        $customer    = Yii::app()->customer->getModel();
        $order       = new PricePlanOrder();
        $transaction = new PricePlanOrderTransaction();
        
        $note             = new PricePlanOrderNote();
        $note->attributes = (array)$request->getPost($note->modelName, array());
        
        $order->customer_id = $customer->customer_id;
        $order->plan_id     = $pricePlan->plan_id;
        $order->currency_id = $currency->currency_id;
        
        $order->addRelatedRecord('customer', $customer, false);
        $order->addRelatedRecord('plan', $pricePlan, false);
        $order->addRelatedRecord('currency', $currency, false);
  
        if (!empty($promoCode)) {
            $promoCodeModel = PricePlanPromoCode::model()->findByAttributes(array('code' => $promoCode));
            if (!empty($promoCodeModel)) {
                $order->promo_code_id = $promoCodeModel->promo_code_id;
                $order->addRelatedRecord('promoCode', $promoCodeModel, false);
            }
        }

        $this->setData(array(
            'extension'         => $extensionInstance,
            'customer'          => $customer,
            'paymentGateway'    => $paymentGateway,
            'paymentHandler'    => $paymentHandler,
            'promoCode'         => $promoCode,
            'order'             => $order,
            'transaction'       => $transaction,
            'note'              => $note,
        ));

        if (!$order->calculate()->save(false)) {
            $message = Yii::t('price_plans', 'Cannot save your order!');
            if ($request->isAjaxRequest) {
                return $this->renderJson(array(
                    'result'  => 'error', 
                    'message' => $message,
                ));
            }
            $notify->addError($message);
            $this->redirect(array('price_plans/payment'));
        }

        $transaction->order_id             = $order->order_id;
        $transaction->payment_gateway_name = $paymentGateway;
        $transaction->save(false);
        
        $note->order_id = $order->order_id;
        if (!empty($note->note)) {
            $note->customer_id = $order->customer_id;
            $note->save();
        }
        
        $order->onAfterSave = array($this, '_sendOrderNotifications');
        $paymentHandler->processOrder();

        if ($request->isAjaxRequest) {
            return $this->renderJson();
        }
        $this->redirect(array('price_plans/index'));
    }
    
    public function actionPromo()
    {
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $session = Yii::app()->session;
        
        $session->remove('promo_code');
        
        if (!$request->isPostRequest) {
            $this->redirect(array('price_plans/payment'));
        }
        
        if (!$session->contains('plan_uid')) {
            $this->redirect(array('price_plans/payment'));
        }
        
        $promoCode = $request->getPost('promo_code');
        if (empty($promoCode)) {
            $this->redirect(array('price_plans/payment'));
        }

        $criteria = new CDbCriteria();
        $criteria->compare('code', $promoCode);
        $criteria->compare('status', PricePlanPromoCode::STATUS_ACTIVE);
        $criteria->addCondition('date_start <= NOW() AND date_end >= NOW()');
        $promoCodeModel = PricePlanPromoCode::model()->find($criteria);
        
        if (empty($promoCodeModel)) {
            $notify->addError(Yii::t('price_plans', 'The provided promotional code does not exists anymore!'));
            $this->redirect(array('price_plans/payment'));
        }

        $planUid   = $session->itemAt('plan_uid');
        $pricePlan = PricePlan::model()->findByUid($planUid);
        
        if (empty($pricePlan)) {
            $this->redirect(array('price_plans/payment'));
        }
        
        if ($promoCodeModel->total_amount > 0 && $pricePlan->price < $promoCodeModel->total_amount) {
            $notify->addError(Yii::t('price_plans', 'This promo code requires that select a price plan that costs at least {amount}!', array(
                '{amount}' => $promoCodeModel->getFormattedTotalAmount(),
            )));
            $this->redirect(array('price_plans/payment'));
        }
        
        $customer = Yii::app()->customer->getModel();
        if ($promoCodeModel->customer_usage > 0) {
            $usedByThisCustomer = PricePlanOrder::model()->countByAttributes(array(
                'promo_code_id' => $promoCodeModel->promo_code_id,
                'customer_id'   => $customer->customer_id,
            ));
            if ($usedByThisCustomer >= $promoCodeModel->customer_usage) {
                $notify->addError(Yii::t('price_plans', 'You have reached the maximum usage times for this promo code!'));
                $this->redirect(array('price_plans/payment'));
            }
        }
        
        if ($promoCodeModel->total_usage > 0) {
            $usedTimes = PricePlanOrder::model()->countByAttributes(array(
                'promo_code_id' => $promoCodeModel->promo_code_id,
            ));
            if ($usedTimes >= $promoCodeModel->total_usage) {
                $notify->addError(Yii::t('price_plans', 'This promo code has reached the maximum usage times!'));
                $this->redirect(array('price_plans/payment'));
            }
        }
        $session->add('promo_code', $promoCodeModel->code);
        
        $notify->addSuccess(Yii::t('price_plans', 'The promo code has been successfully applied!'));
        $this->redirect(array('price_plans/payment'));
    }
    
    public function _sendOrderNotifications($event)
    {
        $order = $event->sender;
        if (!$order->isComplete) {
            return;
        }
        
        $users = User::model()->findAll(array(
            'select'    => 'first_name, last_name, email',
            'condition' => '`status` = "active"',
        ));
        
        $options = Yii::app()->options;
        
        $emailTemplate  = $options->get('system.email_templates.common');
        $emailBody      = $this->renderPartial('email/notify-user', compact('order', 'options'), true);
        $emailTemplate  = str_replace('[CONTENT]', $emailBody, $emailTemplate);
        
        foreach ($users as $user) {
            $searchReplace = array(
                '{fname}' => $user->first_name,
                '{lname}' => $user->last_name,
                '{name}'  => $user->fullName,
                '{email}' => $user->email,
            );
            $email = new TransactionalEmail();
            $email->to_name     = $user->fullName;
            $email->to_email    = $user->email;
            $email->from_name   = $options->get('system.common.site_name', 'Marketing website');
            $email->subject     = Yii::t('orders', 'A new order has been placed!');
            $email->body        = str_replace(array_keys($searchReplace), array_values($searchReplace), $emailTemplate);
            $email->save();
        }
        
        $customer       = $order->customer;
        $emailTemplate  = $options->get('system.email_templates.common');
        $emailBody      = $this->renderPartial('email/notify-customer', compact('order', 'options'), true);
        $emailTemplate  = str_replace('[CONTENT]', $emailBody, $emailTemplate);
        
        $searchReplace = array(
            '{fname}' => $customer->first_name,
            '{lname}' => $customer->last_name,
            '{name}'  => $customer->fullName,
            '{email}' => $customer->email,
        );
        $email = new TransactionalEmail();
        $email->to_name     = $customer->fullName;
        $email->to_email    = $customer->email;
        $email->from_name   = $options->get('system.common.site_name', 'Marketing website');
        $email->subject     = Yii::t('orders', 'Your order details!');
        $email->body        = str_replace(array_keys($searchReplace), array_values($searchReplace), $emailTemplate);
        $email->save();
        
    }
}