<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * AppsBehavior
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class AppsBehavior extends CBehavior
{
    private $_availableApps = array();
    
    private $_webApps = array();
    
    private $_notWebApps = array();
    
    private $_currentAppName;
    
    private $_currentAppIsWeb;
    
    private $_appsUrls = array();
    
    /**
     * AppsBehavior::setAvailableApps()
     * 
     * @param array $apps
     * @return AppsBehavior
     */
    public function setAvailableApps(array $apps)
    {
        if (!empty($this->_availableApps)) {
            return $this;
        }
        $this->_availableApps = $apps;
        return $this;
    }
    
    /**
     * AppsBehavior::getAvailableApps()
     * 
     * @return array
     */
    public function getAvailableApps()
    {
        return $this->_availableApps;
    }
    
    /**
     * AppsBehavior::setWebApps()
     * 
     * @param array $apps
     * @return  AppsBehavior
     */
    public function setWebApps(array $apps)
    {
        if (!empty($this->_webApps)) {
            return $this;
        }
        $this->_webApps = $apps;
        return $this;
    }
    
    /**
     * AppsBehavior::getWebApps()
     * 
     * @return array
     */
    public function getWebApps()
    {
        return $this->_webApps;
    }
    
    /**
     * AppsBehavior::setNotWebApps()
     * 
     * @param array $apps
     * @return AppsBehavior
     */
    public function setNotWebApps(array $apps)
    {
        if (!empty($this->_notWebApps)) {
            return $this;
        }
        $this->_notWebApps = $apps;
        return $this;
    }
    
    /**
     * AppsBehavior::getNotWebApps()
     * 
     * @return array
     */
    public function getNotWebApps()
    {
        return $this->_notWebApps;
    }
    
    /**
     * AppsBehavior::setCurrentAppName()
     * 
     * @param string $appName
     * @return AppsBehavior
     */
    public function setCurrentAppName($appName)
    {
        if ($this->_currentAppName !== null) {
            return $this;
        }
        $this->_currentAppName = $appName;
        return $this;
    }
    
    /**
     * AppsBehavior::getCurrentAppName()
     * 
     * @return string
     */
    public function getCurrentAppName()
    {
        return $this->_currentAppName;
    }
    
    /**
     * AppsBehavior::setCurrentAppIsWeb()
     * 
     * @param mixed $isWeb
     * @return mixed
     */
    public function setCurrentAppIsWeb($isWeb)
    {
        if ($this->_currentAppIsWeb !== null) {
            return $this->_currentAppIsWeb;
        }
        $this->_currentAppIsWeb = (bool)$isWeb;
        return $this;
    }
    
    /**
     * AppsBehavior::getCurrentAppIsWeb()
     * 
     * @return bool
     */
    public function getCurrentAppIsWeb()
    {
        return $this->_currentAppIsWeb;
    }
    
    /**
     * AppsBehavior::isAppName()
     * 
     * @param string $appName
     * @return bool
     */
    public function isAppName($appName)
    {
        return strtolower($appName) === strtolower($this->getCurrentAppName());
    }
    
    /**
     * AppsBehavior::getAppBaseUrl()
     * 
     * @param mixed $appName
     * @param bool $absolute
     * @param bool $hideScriptName
     * @return string
     */
    public function getAppBaseUrl($appName = null, $absolute = false, $hideScriptName = false)
    {
        if (empty($appName)) {
            $appName = $this->getCurrentAppName();
        }
        
        if (!in_array($appName, $this->getWebApps())) {
            return false;
        }
        
        $currentApp = $this->getCurrentAppName();
        $baseUrl    = $this->owner->getBaseUrl($absolute);
        $baseUrl    = preg_replace('/(\/frontend)$/ix', '', $baseUrl);
        
        if ($appName == 'frontend') {
            $appName = null;
        }

        $url = preg_replace('/\/('.preg_quote($currentApp, '/').')$/ix', '', $baseUrl) . (!empty($appName) ? '/' . ltrim($appName, '/') : '') . '/';
        
        $showScriptName = $this->owner->urlManager->showScriptName;
        
        if (!$hideScriptName && $showScriptName) {
            $url .= 'index.php/';
        }
        
        return $url;
    }
    
    /**
     * AppsBehavior::getAppUrl()
     * 
     * @param mixed $appName
     * @param mixed $uri
     * @param bool $absolute
     * @param bool $hideScriptName
     * @return mixed
     */
    public function getAppUrl($appName = null, $uri = null, $absolute = false, $hideScriptName = false)
    {
        if (!($base = $this->getAppBaseUrl($appName, $absolute, $hideScriptName))) {
            return false;
        }
        
        return $base . ltrim($uri, '/');
    }
    
    /**
     * AppsBehavior::getBaseUrl()
     * 
     * @param mixed $appendThis
     * @param bool $absolute
     * @return string
     */
    public function getBaseUrl($appendThis = null, $absolute = false)
    {
        $relative = $this->owner->getBaseUrl();
        $baseUrl  = preg_replace('/\/?' . preg_quote($this->getCurrentAppName(), '/') . '\/?$/', '', $relative);
        $baseUrl  = '/' . trim($baseUrl, '/') . '/' . trim($appendThis, '/');
        $baseUrl  = str_replace('//', '/', $baseUrl);
        
        if ($absolute) {
            $absolute = $this->owner->getBaseUrl(true);
            $absolute = str_replace($relative, '', $absolute);
            $baseUrl  = $absolute . $baseUrl;
            $baseUrl  = str_replace('//', '/', $baseUrl);
        }
        
        return $baseUrl;
    }
}