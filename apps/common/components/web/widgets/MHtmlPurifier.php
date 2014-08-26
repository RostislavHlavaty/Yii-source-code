<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * MHtmlPurifier
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class MHtmlPurifier extends CHtmlPurifier
{
    // inherited
    protected function createNewHtmlPurifierInstance()
    {
        $purifier = parent::createNewHtmlPurifierInstance();
        $this->adjustConfiguration($purifier);
        return $purifier;
    }
    
    protected function adjustConfiguration(HTMLPurifier $purifier)
    {
        $options = require Yii::getPathOfAlias('common.config.htmlpurifier').'.php';
        $config = $purifier->config;
        
        foreach ($options as $directive => $value) {
            $config->set($directive, $value);
        }
        
        $this->addBorderRadiusSupport($config);
        
        $this->onAdjustConfiguration(new CEvent($this, array(
            'config' => $config,
        )));
    }
    
    public function onAdjustConfiguration(CEvent $event)
    {
        $this->raiseEvent('onAdjustConfiguration', $event);
    }
    
    // see: http://htmlpurifier.org/phorum/read.php?2,6154,6154#msg-6154
    protected function addBorderRadiusSupport(HTMLPurifier_Config $config)
    {
        $cssDefinition = $config->getCSSDefinition();
        $info = array();
        
        // all browsers
        $borderRadius =
        $info['border-top-left-radius'] =
        $info['border-top-right-radius'] =
        $info['border-bottom-left-radius'] =
        $info['border-bottom-right-radius'] =
        new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_CSS_Length('0'), 
            new HTMLPurifier_AttrDef_CSS_Percentage(true)
        ));
        $info['border-radius'] = new HTMLPurifier_AttrDef_CSS_Multiple($borderRadius);
        
        // webkit specific
        $borderRadius =
        $info['-webkit-border-top-left-radius'] =
        $info['-webkit-border-top-right-radius'] =
        $info['-webkit-border-bottom-left-radius'] =
        $info['-webkit-border-bottom-right-radius'] =
        new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_CSS_Length('0'), 
            new HTMLPurifier_AttrDef_CSS_Percentage(true)
        ));
        $info['-webkit-border-radius'] = new HTMLPurifier_AttrDef_CSS_Multiple($borderRadius);
        
        // mozilla specific
        $borderRadius =
        $info['-moz-border-top-left-radius'] =
        $info['-moz-border-top-right-radius'] =
        $info['-moz-border-bottom-left-radius'] =
        $info['-moz-border-bottom-right-radius'] =
        new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_CSS_Length('0'), 
            new HTMLPurifier_AttrDef_CSS_Percentage(true)
        ));
        $info['-moz-border-radius'] = new HTMLPurifier_AttrDef_CSS_Multiple($borderRadius);

        // wrap all new attr-defs with decorator that handles !important                                                                                                                
        $allowImportant = $config->get('CSS.AllowImportant');
        foreach ($info as $k => $v) {
            $cssDefinition->info[$k] = new HTMLPurifier_AttrDef_CSS_ImportantDecorator($v, $allowImportant);
        }
    }
}