<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Console application main configuration file
 * 
 * This file should not be altered in any way!
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */

return array(
    'basePath' => Yii::getPathOfAlias('console'),
    
    'preload' => array(
        'consoleSystemInit'
    ),
    
    'import' => array(      
        'console.components.*',
        'console.components.db.*',
        'console.components.db.ar.*',
    ),
    
    'commandMap' => array(
        'migrate' => array(
            'class'             => 'system.cli.commands.MigrateCommand',
            'migrationPath'     => 'console.migrations',
            'migrationTable'    => '{{migration}}',
            'connectionID'      => 'db',
        ),
        'hello' => array(
            'class' => 'console.commands.HelloCommand'
        ),
        'send-campaigns' => array(
            'class' => 'console.commands.SendCampaignsCommand'
        ),
        'bounce-handler' => array(
            'class' => 'console.commands.BounceHandlerCommand'
        ),
        'process-delivery-and-bounce-log' => array(
            'class' => 'console.commands.ProcessDeliveryAndBounceLogCommand'
        ),
        // this command is deprecated since 1.3.3.1 in favor of daily command
        'process-subscribers' => array(
            'class' => 'console.commands.ProcessSubscribersCommand'
        ),
        'option' => array(
            'class' => 'console.commands.OptionCommand'
        ),
        'feedback-loop-handler' => array(
            'class' => 'console.commands.FeedbackLoopHandlerCommand'
        ),
        'send-transactional-emails' => array(
            'class' => 'console.commands.SendTransactionalEmailsCommand'
        ),
        'daily' => array(
            'class' => 'console.commands.DailyCommand'
        ),
    ),
    
    'components' => array(
        'consoleSystemInit' => array(
            'class' => 'console.components.init.ConsoleSystemInit',
        ),
    ),
);