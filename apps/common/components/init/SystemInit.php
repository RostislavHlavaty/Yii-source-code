<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * SystemInit
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class SystemInit extends CApplicationComponent 
{
    protected $_hasRanOnBeginRequest = false;

    /**
     * SystemInit::init()
     * 
     * @return
     */
    public function init()
    {
        parent::init();
        Yii::app()->attachEventHandler('onBeginRequest', array($this, '_runOnBeginRequest'));
    }
    
    /**
     * SystemInit::_runOnBeginRequest()
     * 
     * @return
     */
    public function _runOnBeginRequest()
    {
        if ($this->_hasRanOnBeginRequest) {
            return;
        }

        $options = Yii::app()->options;
        $appName = Yii::app()->apps->getCurrentAppName();
        
        if (!MW_IS_CLI) {
            Yii::app()->hooks->addAction($appName . '_controller_before_action', array($this, '_reindexGetArray'));
        }
        
        if (!in_array($appName, array('backend', 'console')) && $options->get('system.common.site_status') === 'offline') {
            Yii::app()->hooks->addAction($appName . '_controller_before_action', array($this, '_setRedirectToOfflinePage'), -1000);
        }
        
        if (!MW_IS_CLI) {

            // clean the globals.
            Yii::app()->ioFilter->cleanGlobals();
            
            // nice urls
            if ($options->get('system.common.clean_urls')) {
                Yii::app()->urlManager->showScriptName = false;
            }
            
            // verify the stored urls.
            $this->checkAppsStoredUrls();

            // set the application display language
            $this->setApplicationLanguage();    
            
            if (!in_array($appName, array('api'))) {
                // check if we need to upgrade
                $this->checkUpgrade();    
            }
        }
        
        // load all extensions.
        Yii::app()->extensionsManager->loadAllExtensions();
        
        // setup theme or base view system if not cli mode
        if (!MW_IS_CLI && !in_array($appName, array('api'))) {

            // try to set the theme system
            if (Yii::app()->hasComponent('themeManager')) {
                Yii::app()->themeManager->setAppTheme();
            }
        }

        // and mark the event as completed.
        $this->_hasRanOnBeginRequest = true;
    }
    
    /**
     * SystemInit::checkAppsStoredUrls()
     * 
     * @return
     */
    protected function checkAppsStoredUrls()
    {
        // base urls (needed from cli mode since $_SERVER is not available there)
        $apps = Yii::app()->apps->getWebApps();
        foreach ($apps as $appName) {
            $storedBaseUrl = Yii::app()->options->get('system.urls.'.$appName.'_absolute_url');
            $baseUrl = Yii::app()->apps->getAppUrl($appName, null, true);
            $baseUrl = preg_replace('/^https/', 'http', $baseUrl);
            if ($storedBaseUrl != $baseUrl) {
                Yii::app()->options->set('system.urls.'.$appName.'_absolute_url', $baseUrl);
            }
        }
    }
    
    /**
     * SystemInit::setApplicationLanguage()
     * 
     * Will set the application language in cascade.
     * It will default to english if there is no default language or if the client/user do not have a language set!
     * 
     * @since 1.1
     */
    protected function setApplicationLanguage()
    {
        $apps = Yii::app()->apps;

        // multilanguage is available since 1.1 and the Language class does not exist prior to that version
        if (!version_compare(Yii::app()->options->get('system.common.version'), '1.1', '>=')) {
            return;    
        }
        
        $baseLanguageCode = $languageCode = Language::DEFAULT_LANGUAGE_CODE;
        
        $language               = Language::getDefaultLanguage();
        $loadCustomerLanguage   = Yii::app()->hasComponent('customer') && Yii::app()->customer->getId() > 0;
        $loadUserLanguage       = !$loadCustomerLanguage && Yii::app()->hasComponent('user') && Yii::app()->user->getId() > 0 ;
        
        if (!empty($language)) {
            $languageCode = $language->getLanguageAndLocaleCode();
        }
        
        if ($loadCustomerLanguage || $loadUserLanguage) {
            if ($loadCustomerLanguage && ($model = Yii::app()->customer->getModel())) {
                if (!empty($model->language_id)) {
                    $language = Language::model()->findByPk((int)$model->language_id);
                    $languageCode = $language->getLanguageAndLocaleCode();    
                }
            }
            
            if ($loadUserLanguage && ($model = Yii::app()->user->getModel())) {
                if (!empty($model->language_id)) {
                    $language = Language::model()->findByPk((int)$model->language_id);
                    $languageCode = $language->getLanguageAndLocaleCode();    
                }
            }
        }
        
        Yii::app()->setLanguage($languageCode);
    }
    
    /**
     * SystemInit::checkUpgrade()
     * 
     * Will check and see if the application needs upgrade.
     * If it needs, will put it in maintenance mode untill upgrade is done.
     * 
     * @since 1.1
     */
    protected function checkUpgrade()
    {
        $apps = Yii::app()->apps;
        if (!in_array($apps->getCurrentAppName(), array('backend', 'customer', 'frontend'))) {
            return;
        }
        
        $options     = Yii::app()->options;
        $fileVersion = MW_VERSION;
        $dbVersion   = $options->get('system.common.version');
        
        if (!version_compare($fileVersion, $dbVersion, '>')) {
            return;
        }

        $siteStatus = $options->get('system.common.site_status', 'online');
        if ($siteStatus == 'online') {
            $options->set('system.common.site_status', 'offline');
        }
        
        // only if the user is logged in
        if (Yii::app()->hasComponent('user') && Yii::app()->user->getId() > 0) {
            $appName = $apps->getCurrentAppName();
            Yii::app()->hooks->addAction($appName . '_controller_init', array($this, '_setRedirectToUpdatePage'));    
        }
    }
    
    /**
     * SystemInit::_setRedirectToUpdatePage
     * 
     * Called in all controllers init() method, will redirect to update page.
     */
    public function _setRedirectToUpdatePage()
    {
        $apps = Yii::app()->apps;
        $controller = Yii::app()->getController();
        
        // leave the error page alone
        if (stripos(Yii::app()->errorHandler->errorAction, $controller->route) !== false) {
            return;
        }
        
        if (!$apps->isAppName('backend') || $controller->id != 'update') {
            Yii::app()->request->redirect($apps->getAppUrl('backend', 'update/index', true));
        }
    }
    
    /**
     * SystemInit::_setRedirectToOfflinePage()
     * 
     * @param mixed $action
     * @return
     */
    public function _setRedirectToOfflinePage($action)
    {
        $apps = Yii::app()->apps;
        $controller = Yii::app()->getController();
        $controllerHandler = 'site';

        $isErrorPage = $controller->id == $controllerHandler && $controller->action->id == 'error';
        $isOfflinePage = $controller->id == $controllerHandler && $controller->action->id == 'offline';
        if (!$isErrorPage && !$isOfflinePage) {
            Yii::app()->request->redirect($apps->getAppUrl('frontend', $controllerHandler . '/offline', true));
        }
    }
    
    /**
     * SystemInit::_reindexGetArray()
     * 
     * @return
     */
    public function _reindexGetArray()
    {
        if (empty($_GET)) {
            return;
        }
        Yii::app()->params['GET']->mergeWith($_GET);
        $_GET = Yii::app()->ioFilter->stripClean($_GET);
    }
}