<?php defined('MW_APP_NAME') || exit('No direct script access allowed');

/**
 * Bootstrap file
 * 
 * This file needs to be included by the init script of each application.
 * Please do not alter this file in any way, otherwise bad things can happen.
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
// if debug mode is forced then go with it
if (defined('MW_FORCE_DEBUG_MODE') && MW_FORCE_DEBUG_MODE) {
    error_reporting(-1);
    ini_set('display_errors', 1);
    define('MW_CACHE_TTL', 300);
    define('YII_DEBUG', true);
    define('YII_TRACE_LEVEL', 3);  
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    define('MW_CACHE_TTL', 60 * 60 * 24 * 365);    
}

// a few base mw constants
define('MW_NAME', 'MailWizz EMA');
define('MW_VERSION', '1.3.4.5'); // never remove or alter this constant, never!
define('MW_PATH', realpath(dirname(__FILE__).'/..'));
define('MW_ROOT_PATH', MW_PATH);
define('MW_APPS_PATH', MW_PATH.'/apps');

// mark if the app in debug mode.
define('MW_DEBUG', defined('YII_DEBUG')); 

// easier access to see if cli
// define('MW_IS_CLI', php_sapi_name() == 'cli');
define('MW_IS_CLI', php_sapi_name() == 'cli' || (!isset($_SERVER['SERVER_SOFTWARE']) && !empty($_SERVER['argv'])));

// mark if the incoming request is an ajax request.
define('MW_IS_AJAX', !MW_IS_CLI && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// mark if APC exists on the host.
define('MW_APC_EXISTS', extension_loaded('apc'));

// if apc exists, decide if load the yii lite version of the framework.
defined('MW_USE_APC_BOOTSTRAP') or define('MW_USE_APC_BOOTSTRAP', true);

// fcgi doesn't have STDIN nor STDOUT defined by default.
defined('STDIN') or define('STDIN', fopen('php://stdin', 'r'));
defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'w'));

// again, for some fcgi installs
if (empty($_SERVER['SCRIPT_FILENAME'])) {
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
}

// misc ini settings
ini_set('file_uploads', 'On');

// forced memory limit
if (defined('MW_MEMORY_LIMIT')) {
    ini_set('memory_limit', MW_MEMORY_LIMIT);
}

// forced post max size
if (defined('MW_POST_MAX_SIZE')) {
    ini_set('post_max_size', MW_POST_MAX_SIZE);
}

// forced upload size
if (defined('MW_UPLOAD_MAX_FILESIZE')) {
    ini_set('upload_max_filesize', MW_UPLOAD_MAX_FILESIZE);
}

setlocale(LC_ALL, 'en_US.UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
// seems mb_regex_encoding is missing in some cases even if the mb extension is installed
if (function_exists('mb_regex_encoding')) {
    mb_regex_encoding('UTF-8');
}

// define the path to the YII framework
$yii = MW_APPS_PATH.'/common/framework/yii.php';
if (!MW_DEBUG && MW_APC_EXISTS && defined('MW_USE_APC_BOOTSTRAP') && MW_USE_APC_BOOTSTRAP) {
    $yii = MW_APPS_PATH.'/common/framework/yiilite.php';
}

// make sure the YII bootstrap file exists and can be loaded
if (!is_file($yii)) {
    throw new Exception('Invalid framework bootstrap file.');
}

// require the framework
require_once($yii); 

// set the main paths of alias
Yii::setPathOfAlias('root', realpath(dirname(__FILE__).'/..'));  
Yii::setPathOfAlias('common', Yii::getPathOfAlias('root.apps.common'));

// check to see if the app type exists.
if (MW_APP_NAME === 'common') {
    throw new Exception('The "common" application name is restricted.');
} elseif (MW_IS_CLI && !is_dir(dirname(__FILE__).'/'.MW_APP_NAME)) {
    throw new Exception('Invalid application.');
} elseif (!MW_IS_CLI && (!is_dir(dirname(__FILE__).'/'.MW_APP_NAME) || !is_dir(realpath(dirname(__FILE__).'/../'.MW_APP_NAME)))) {
    throw new Exception('Invalid application.');
}

// require a few helpers to help things out.
require_once(Yii::getPathOfAlias('common.components.helpers.FileSystemHelper').'.php');
require_once(Yii::getPathOfAlias('common.components.helpers.AppInitHelper').'.php');

// list of available apps.
$availableApps = FileSystemHelper::getDirectoryNames(dirname(__FILE__));
$webApps = array();
foreach ($availableApps as $appName) {
    if (file_exists(MW_PATH . '/' . $appName) && is_dir(MW_PATH . '/' . $appName)) {
        $webApps[] = $appName;
    }
}
$notWebApps = array_diff($availableApps, $webApps);

// set path alias for apps
foreach ($availableApps as $appName) {
    Yii::setPathOfAlias($appName, Yii::getPathOfAlias('root.apps.'.$appName));
}

if (!MW_IS_CLI) {
    AppInitHelper::fixRemoteAddress();    
    AppInitHelper::noMagicQuotes();
}

// load main configuration file and also check to see if there is a custom one to load that too
$commonConfig = require_once(Yii::getPathOfAlias('common.config.main') . '.php');
if (is_file($customConfigFile = Yii::getPathOfAlias('common.config.main-custom') . '.php')) {
    $commonConfig = CMap::mergeArray($commonConfig, require_once($customConfigFile));
}

// load the config file for the current app and also check to see if there is a custom one to load that too
$appConfig = require_once(Yii::getPathOfAlias(MW_APP_NAME . '.config.main') . '.php');
if (is_file($customConfigFile = Yii::getPathOfAlias(MW_APP_NAME . '.config.main-custom') . '.php')) {
    $appConfig = CMap::mergeArray($appConfig, require_once($customConfigFile));
}
 
// merge the app config with the base config
$appConfig = CMap::mergeArray($commonConfig, $appConfig);

// create the application instance.
if (!MW_IS_CLI) {
    $app = Yii::createWebApplication($appConfig);
} else {
    $webSpecific = array('defaultController', 'modules', 'controllerNamespace');
    foreach ($webSpecific AS $prop) {
        if (isset($appConfig[$prop])) {
            unset($appConfig[$prop]);
        }
    }
    $app = Yii::createConsoleApplication($appConfig);
}

Yii::setPathOfAlias('extensions', Yii::getPathOfAlias('root.apps.extensions'));

// set apps data behavior for easier data access!
$app->attachBehavior('apps', array(
    'class'             => 'common.components.behaviors.AppsBehavior',
    'availableApps'     => $availableApps,
    'webApps'           => $webApps,
    'notWebApps'        => $notWebApps,
    'currentAppName'    => MW_APP_NAME,
    'currentAppIsWeb'   => in_array(MW_APP_NAME, $webApps),
));

// unset all the created variables since the party just starts and we don't want them around anymore.
unset($yii, $commonConfig, $customConfigFile, $appConfig, $availableApps, $webApps, $notWebApps, $appName, $webSpecific);

// add the ability to return the app instance instead of running it.
if (defined('MW_RETURN_APP_INSTANCE') && MW_RETURN_APP_INSTANCE) {
    return $app;
}

// and run the application
$app->run();