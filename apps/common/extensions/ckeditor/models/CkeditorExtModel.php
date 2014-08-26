<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CkeditorExtModel
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 */
 
class CkeditorExtModel extends FormModel
{
    public $enable_filemanager_user = 0;
    
    public $enable_filemanager_customer = 0;
    
    public $default_toolbar = 'Default';
    
    public function rules()
    {
        $instance = Yii::app()->extensionsManager->getExtensionInstance('ckeditor');
        $rules = array(
            array('enable_filemanager_user, enable_filemanager_customer, default_toolbar', 'required'),
            array('enable_filemanager_user, enable_filemanager_customer', 'in', 'range' => array(0, 1)),
            array('default_toolbar', 'in', 'range' => $instance->getEditorToolbars())
        );
        
        return CMap::mergeArray($rules, parent::rules());    
    }
    
    public function attributeLabels()
    {
        $labels = array(
            'enable_filemanager_user'       => Yii::t('ext_ckeditor', 'Enable filemanager for users'),
            'enable_filemanager_customer'   => Yii::t('ext_ckeditor', 'Enable filemanager for customers'),
            'default_toolbar'               => Yii::t('ext_ckeditor', 'Default toolbar'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());    
    }
    
    public function attributePlaceholders()
    {
        $placeholders = array();
        
        return CMap::mergeArray($placeholders, parent::attributePlaceholders());
    }
    
    public function attributeHelpTexts()
    {
        $texts = array(
            'enable_filemanager_user'       => Yii::t('ext_ckeditor', 'Whether to enable the filemanager for users'),
            'enable_filemanager_customer'   => Yii::t('ext_ckeditor', 'Whether to enable the filemanager for customers'),
            'default_toolbar'               => Yii::t('ext_ckeditor', 'Default toolbar for all editor instances'),
        );
        
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }

    public function getOptionsDropDown()
    {
        return array(
            0 => Yii::t('app', 'No'),
            1 => Yii::t('app', 'Yes'),
        );
    }
    
    public function getToolbarsDropDown()
    {
        $instance = Yii::app()->extensionsManager->getExtensionInstance('ckeditor');
        $toolbars = $instance->getEditorToolbars();
        return array_combine($toolbars, $toolbars);
    }
    
    public function populate($extensionInstance)
    {
        $this->enable_filemanager_user      = $extensionInstance->getOption('enable_filemanager_user', $this->enable_filemanager_user);
        $this->enable_filemanager_customer  = $extensionInstance->getOption('enable_filemanager_customer', $this->enable_filemanager_customer);
        $this->default_toolbar              = $extensionInstance->getOption('default_toolbar', $this->default_toolbar);
        return $this;
    }
    
    public function save($extensionInstance)
    {
        $extensionInstance->setOption('enable_filemanager_user', $this->enable_filemanager_user);
        $extensionInstance->setOption('enable_filemanager_customer', $this->enable_filemanager_customer);
        $extensionInstance->setOption('default_toolbar', $this->default_toolbar);
        return $this;
    }
}
