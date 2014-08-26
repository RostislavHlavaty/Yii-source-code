<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * AppInitHelper
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class AppInitHelper 
{
    private static $_entryScriptUrl;
    
    private static $_baseUrl;
    
    /**
     * AppInitHelper::getEntryScriptUrl()
     * 
     * Inspired from Yii
     * 
     * @return string
     */
    public static function getEntryScriptUrl()
    {
        if(self::$_entryScriptUrl === null)
        {
            $scriptName = basename($_SERVER['SCRIPT_FILENAME']);
            
            if (basename($_SERVER['SCRIPT_NAME']) === $scriptName) {
                self::$_entryScriptUrl = $_SERVER['SCRIPT_NAME'];
            } elseif (basename($_SERVER['PHP_SELF']) === $scriptName) {
                self::$_entryScriptUrl = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $scriptName) {
                self::$_entryScriptUrl = $_SERVER['ORIG_SCRIPT_NAME'];
            } elseif (($pos = strpos($_SERVER['PHP_SELF'],'/'.$scriptName)) !== false) {
                self::$_entryScriptUrl = substr($_SERVER['SCRIPT_NAME'],0,$pos).'/'.$scriptName;
            } elseif (isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT']) === 0) {
                self::$_entryScriptUrl = str_replace('\\','/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']));
            } else {
                throw new Exception('Unable to determine the entry script URL.');
            }   
        }
        return self::$_entryScriptUrl;
    }
    
    /**
     * AppInitHelper::getBaseUrl()
     * 
     * @param mixed $appendThis
     * @return string
     */
    public static function getBaseUrl($appendThis = null)
    {
        if(self::$_baseUrl === null) {
            self::$_baseUrl = rtrim(dirname(self::getEntryScriptUrl()),'\\/');
        }
        return self::$_baseUrl . (!empty($appendThis) ? '/' . trim($appendThis, '/') : null);
    }
    
    /**
     * AppInitHelper::noMagicQuotes()
     * 
     * @return
     */
    public static function noMagicQuotes()
    {
        static $hasRan = false;

        if ($hasRan || !get_magic_quotes_gpc()) {
            return;
        }
        
        $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
        while (list($key, $val) = each($process)) {
            foreach ($val as $k => $v) {
                unset($process[$key][$k]);
                if (is_array($v)) {
                    $process[$key][stripslashes($k)] = $v;
                    $process[] = &$process[$key][stripslashes($k)];
                } else {
                    $process[$key][stripslashes($k)] = stripslashes($v);
                }
            }
        }
        unset($process);
        $hasRan = true;
    }
    
    /**
     * AppInitHelper::fixRemoteAddress()
     * 
     * @return
     */
    public static function fixRemoteAddress()
    {
        static $hasRan = false;
        if ($hasRan) {
            return;
        }
        
        if (empty($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['HTTP_X_REAL_IP'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (empty($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
    
        $hasRan = true;
    }
    
    /**
     * AppInitHelper::isModRewriteEnabled()
     * 
     * @return bool
     */
    public static function isModRewriteEnabled()
    {
        return CommonHelper::functionExists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules());
    }
    
    /**
     * AppInitHelper::isSecureConnection()
     * 
     * @return bool
     */
    public static function isSecureConnection()
    {
        return !empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'off');
    }
    
    /**
     * AppInitHelper::getWithCurl()
     * 
     * @param string $requestUrl
     * @return array
     */
    public static function simpleCurlPost($requestUrl, array $postData = array(), $timeout = 30) 
    {
        if (!CommonHelper::functionExists('curl_init')) {
            return array('status' => 'error', 'message' => 'cURL not available, please install cURL and try again!');
        }
        
        $ch = curl_init($requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_AUTOREFERER , true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);    
        
        curl_setopt($ch, CURLOPT_POST, count($postData));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData, '', '&'));
        
        $body           = curl_exec($ch);
        $curlCode       = curl_errno($ch);
        $curlMessage    = curl_error($ch);
    
        curl_close($ch);
        
        if ($curlCode !== 0) {
            return array('status' => 'error', 'message' => $curlMessage);
        }
        
        return array('status' => 'success', 'message' => $body);
    }
    
    /**
     * AppInitHelper::getWithCurl()
     * 
     * @since 1.2
     * @param string $requestUrl
     * @return array
     */
    public static function simpleCurlGet($requestUrl, $timeout = 30) 
    {
        if (!CommonHelper::functionExists('curl_init')) {
            return array('status' => 'error', 'message' => 'cURL not available, please install cURL and try again!');
        }
        
        $ch = curl_init($requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_AUTOREFERER , true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);    

        $body           = curl_exec($ch);
        $curlCode       = curl_errno($ch);
        $curlMessage    = curl_error($ch);
    
        curl_close($ch);
        
        if ($curlCode !== 0) {
            return array('status' => 'error', 'message' => $curlMessage);
        }
        
        return array('status' => 'success', 'message' => $body);
    }
    
    /**
     * AppInitHelper::findPhpCliPath()
     * 
     * @since 1.3.3.1
     * @return string
     */
    public static function findPhpCliPath()
    {
        return CommonHelper::findPhpCliPath();
    }
}