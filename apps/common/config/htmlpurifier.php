<?php if ( ! defined('YII_PATH')) exit('No direct script access allowed');

/**
 * Html purifier configuration file
 * 
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */

return array(

    // core related
    'Core.Encoding'             => Yii::app()->charset,
    'Core.EscapeInvalidTags'    => false,
    
    // uri related
    'URI.Base'              => null,
    'URI.AllowedSchemes'    => array(
        'http'      => true,
        'https'     => true,
        'mailto'    => true,
        'ftp'       => true,
        'nntp'      => true,
        'news'      => true,
        '['         => true, // pretty weird this works...
    ),
    
    // html related
    'HTML.Trusted'    => false, // true on own risk
    
    // attributes 
    'Attr.EnableID'             => true,
    'Attr.AllowedRel'           => array('noindex', 'nofollow'),
    'Attr.AllowedFrameTargets'  => array('_blank', '_self', '_parent', '_top'), 
    
    // css
    'CSS.AllowTricky'      => true,
    'CSS.AllowImportant'   => true,
    'CSS.Proprietary'      => true,    
    'CSS.Trusted'          => false, // true on own risk
);