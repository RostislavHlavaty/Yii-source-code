<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CkeditorExt
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 */
 
class CkeditorExt extends ExtensionInit 
{
    // name of the extension as shown in the backend panel
    public $name = 'CKeditor';
    
    // description of the extension as shown in backend panel
    public $description = 'CKeditor for MailWizz EMA';
    
    // current version of this extension
    public $version = '1.2.2';
    
    // the author name
    public $author = 'Cristian Serban';
    
    // author website
    public $website = 'http://www.mailwizz.com/';
    
    // contact email address
    public $email = 'cristian.serban@mailwizz.com';
    
    // in which apps this extension is not allowed to run
    public $allowedApps = array('backend', 'customer');
    
    // can this extension be deleted? this only applies to core extensions.
    protected $_canBeDeleted = false;
    
    // can this extension be disabled? this only applies to core extensions.
    protected $_canBeDisabled = true;
    
    // the detected language
    protected $detectedLanguage = 'en';
    
    public function run()
    {
        // the callback to register the editor
        Yii::app()->hooks->addAction('wysiwyg_editor_instance', array($this, '_createNewEditorInstance'));
        
        // register the routes
        Yii::app()->urlManager->addRules(array(
            array('ext_ckeditor/index', 'pattern' => 'extensions/ckeditor'),
            array('ext_ckeditor/filemanager', 'pattern' => 'extensions/ckeditor/filemanager'),
            array('ext_ckeditor/filemanager_connector', 'pattern' => 'extensions/ckeditor/filemanager/connector'),
        ));
        
        // add the controller
        Yii::app()->controllerMap['ext_ckeditor'] = array(
            'class' => 'ext-ckeditor.controllers.Ext_ckeditorController',
        ); 
    }
    
    /**
     * Add the landing page for this extension (settings/general info/etc)
     */
    public function getPageUrl()
    {
        return Yii::app()->createUrl('ext_ckeditor/index');
    }
    
    public function _createNewEditorInstance($editorOptions)
    {
        $this->registerAssets();
        
        $defaultWysiwygOptions = $this->getDefaultEditorOptions();
        $wysiwygOptions = (array)Yii::app()->hooks->applyFilters('wysiwyg_editor_global_options', $defaultWysiwygOptions);
        $wysiwygOptions = CMap::mergeArray($wysiwygOptions, $editorOptions);

        if (!isset($wysiwygOptions['id'])) {
            return;
        }

        $editorId = CHtml::encode($wysiwygOptions['id']);
        $optionsVarName = 'wysiwygOptions'.($editorId);

        unset($wysiwygOptions['id']);
        
        $script  = $optionsVarName.' = ' . CJavaScript::encode($wysiwygOptions) . ';' . "\n";
        $script .= '$("#'.$editorId.'").ckeditor('.$optionsVarName.');';
        
        Yii::app()->clientScript->registerScript(md5(__FILE__.__LINE__.$editorId), $script);
    }
    
    public function getEditorToolbar()
    {
        return Yii::app()->hooks->applyFilters('wysiwyg_editor_toolbar', $this->getOption('default_toolbar', 'Default'));
    }
    
    public function getEditorToolbars()
    {
        return (array)Yii::app()->hooks->applyFilters('wysiwyg_editor_toolbars', array('Default', 'Simple', 'Full'));
    }
    
    protected function getDefaultEditorOptions()
    {
        $apps     = Yii::app()->apps;
        $toolbar  = $this->getEditorToolbar();
        $toolbars = $this->getEditorToolbars();
        
        if (empty($toolbar) || empty($toolbars) || !in_array($toolbar, $toolbars)) {
            $toolbar = 'Default';
        }
        
        $options = array(
            'toolbar'               => $toolbar,
            'language'              => $this->detectedLanguage,
            'contentsLanguage'      => Yii::app()->locale->getLanguageID($this->detectedLanguage),
            'contentsLangDirection' => Yii::app()->locale->orientation,
            'contentsCss'           => array(
                $apps->getBaseUrl('assets/css/bootstrap.min.css'),
                $apps->getBaseUrl('assets/css/font-awesome.min.css'),
                $apps->getBaseUrl('assets/css/ionicons.min.css'),
                $apps->getBaseUrl('assets/css/adminlte.css'),
                $apps->getBaseUrl('assets/css/skin-blue.css'),
                $apps->getBaseUrl('assets/css/common.css')
            ),
        );
        
        if (($this->isAppName('backend') && $this->getOption('enable_filemanager_user')) || ($this->isAppName('customer') && $this->getOption('enable_filemanager_customer'))) {
            $options['filebrowserBrowseUrl'] = Yii::app()->createUrl('ext_ckeditor/filemanager');
            // $options['filebrowserImageWindowWidth'] = 920;
            $options['filebrowserImageWindowHeight'] = 400;
        }
        
        return $options;
    }
    
    protected function registerAssets()
    {
        static $_assetsRegistered = false;
        if ($_assetsRegistered) {
            return;
        }
        $_assetsRegistered = true;
        
        // set a flag to know which editor is active.
        Yii::app()->params['wysiwyg'] = 'ckeditor';
        
        $assetsUrl = Yii::app()->assetManager->publish(dirname(__FILE__).'/assets', false, -1, MW_DEBUG);
        Yii::app()->clientScript->registerScriptFile($assetsUrl . '/ckeditor/ckeditor.js');
        Yii::app()->clientScript->registerScriptFile($assetsUrl . '/ckeditor/adapters/jquery.js');
        
        // find the language file, if any.
        $language       = str_replace('_', '-', Yii::app()->language);
        $languageFile   = null;
        
        if (is_file(dirname(__FILE__) . '/assets/ckeditor/lang/'.$language.'.js')) {
            $languageFile = $language.'.js';    
        }
        
        if ($languageFile === null && strpos($language, '-') !== false) {
            $language = explode('-', $language);
            $language = $language[0];
            if (is_file(dirname(__FILE__) . '/assets/ckeditor/lang/'.$language.'.js')) {
                $languageFile = $language.'.js';
            }
        }
        
        // if language found, register it.
        if ($languageFile !== null) {
            $this->detectedLanguage = $language;
            Yii::app()->clientScript->registerScriptFile($assetsUrl . '/ckeditor/lang/' . $languageFile);
        }
    }
}