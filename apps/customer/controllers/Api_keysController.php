<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Api_keysController
 * 
 * Handles the actions for api keys related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @version 1.0
 * @since 1.0
 */
 
class Api_keysController extends Controller
{
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
     * List available api keys
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;
        $model = new CustomerApiKey('search');
        $model->attributes = (array)$request->getQuery($model->modelName, array());
        $model->customer_id = Yii::app()->customer->getId();
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('api_keys', 'Api keys'), 
            'pageHeading'       => Yii::t('api_keys', 'Api keys'),
            'pageBreadcrumbs'   => array(
                Yii::t('api_keys', 'Api keys') => $this->createUrl('api_keys/index'),
                Yii::t('app', 'View all')
            )
        ));
        
        $this->render('list', compact('model'));
    }
    
    /**
     * Generate a new api key
     */
    public function actionGenerate()
    {
        $model = new CustomerApiKey();
        $model->customer_id = Yii::app()->customer->getId();
        $model->save();
        
        Yii::app()->notify->addInfo(Yii::t('api_keys', 'A new API access has been added:<br />Public key: {public} <br />Private key: {private}', array(
            '{public}'  => $model->public,
            '{private}' => $model->private,
        )));
        
        $this->redirect(array('api_keys/index'));
    }
    
    /**
     * Delete existing api key
     */
    public function actionDelete($id)
    {
        $model = CustomerApiKey::model()->findByAttributes(array(
            'key_id'        => (int)$id,
            'customer_id'   => (int)Yii::app()->customer->getId(),
        ));
        
        if (empty($model)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        $model->delete();
        
        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('api_keys', 'Requested API access has been successfully removed!'));
            $this->redirect($request->getPost('returnUrl', array('api_keys/index')));
        }
    }
}