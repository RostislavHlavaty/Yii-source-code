<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ExtensionsController
 * 
 * Handles the actions for extensions related tasks
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class ExtensionsController extends Controller
{
    /**
     * Define the filters for various controller actions
     * Merge the filters with the ones from parent implementation
     */
    public function filters()
    {
        $filters = array(
            'postOnly + delete', // we only allow deletion via POST request
        );
        
        return CMap::mergeArray($filters, parent::filters());
    }
    
    /**
     * List all available extensions
     */
    public function actionIndex()
    {
        $model = new ExtensionHandlerForm('upload');
        
        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('extensions', 'View extensions'),
            'pageHeading'       => Yii::t('extensions', 'View extensions'),
            'pageBreadcrumbs'   => array(
                Yii::t('extensions', 'Extensions') => $this->createUrl('extensions/index'),
                Yii::t('app', 'View all')
            )
        ));
        
        $this->render('index', compact('model'));
    }
    
    /**
     * Upload a new extensions
     */
    public function actionUpload()
    {
        $request = Yii::app()->request;
        $notify = Yii::app()->notify;
        $model = new ExtensionHandlerForm('upload');
        
        if ($request->isPostRequest && $request->getPost($model->modelName)) {
            $model->archive = CUploadedFile::getInstance($model, 'archive');
               if (!$model->upload()) {
                   $notify->addError($model->shortErrors->getAllAsString());
               } else {
                   $notify->addSuccess(Yii::t('extensions', 'Your extension has been successfully uploaded!'));
               }
               $this->redirect(array('extensions/index'));
          }
          
          $notify->addError(Yii::t('extensions', 'Please select an extension archive for upload!'));
          $this->redirect(array('extensions/index'));
    }
    
    /**
     * Enable extension
     */
    public function actionEnable($id)
    {
        $notify = Yii::app()->notify;
        $manager = Yii::app()->extensionsManager;

        if (!$manager->enableExtension($id)) {
            $notify->clearAll()->addError($manager->getErrors());
        } else {
            $message = Yii::t('extensions', 'The extension "{name}" has been successfully enabled!', array(
                '{name}' => CHtml::encode($manager->getExtensionInstance($id)->name),
            ));
            $notify->clearAll()->addSuccess($message);
        }
        
        $this->redirect(array('extensions/index'));
    }
    
    /**
     * Disable extension
     */
    public function actionDisable($id)
    {
        $notify = Yii::app()->notify;
        $manager = Yii::app()->extensionsManager;
        
        if (!$manager->disableExtension($id)) {
            $notify->clearAll()->addError($manager->getErrors());
        } else {
            $message = Yii::t('extensions', 'The extension "{name}" has been successfully disabled!', array(
                '{name}' => CHtml::encode($manager->getExtensionInstance($id)->name),
            ));
            $notify->clearAll()->addSuccess($message);
        }
        
        $this->redirect(array('extensions/index'));
    }
    
    /**
     * Delete extension
     * All the extension data will be removed
     */
    public function actionDelete($id)
    {
        $notify     = Yii::app()->notify;
        $manager    = Yii::app()->extensionsManager;
        $request    = Yii::app()->request;

        if (!$manager->deleteExtension($id)) {
            $notify->clearAll()->addError($manager->getErrors());
        } else {
            $message = Yii::t('extensions', 'The extension "{name}" has been successfully deleted!', array(
                '{name}' => CHtml::encode($manager->getExtensionInstance($id)->name),
            ));
            $notify->clearAll()->addSuccess($message);
            if (!$request->isAjaxRequest) {
                $this->redirect(array('extensions/index'));
            }
        }
        
        if (!$request->isAjaxRequest) {
            $this->redirect(array('extensions/index'));
        }       
    }

}