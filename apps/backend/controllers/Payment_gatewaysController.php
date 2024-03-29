<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Payment_gatewaysController
 * 
 * Handles the actions for ip location services related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.4
 */
 
class Payment_gatewaysController extends Controller
{
    /**
     * Display available gateways
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;
        $model = new PaymentGatewaysList();
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . Yii::t('payment_gateways', 'Payment gateways'), 
            'pageHeading'       => Yii::t('payment_gateways', 'Payment gateways'),
            'pageBreadcrumbs'   => array(
                Yii::t('payment_gateways', 'Payment gateways'),
            ),
        ));
        
        $this->render('index', compact('model'));
    }

}