<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * OptionCustomerCampaigns
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.3
 */
 
class OptionCustomerCampaigns extends OptionBase
{
    // settings category
    protected $_categoryName = 'system.customer_campaigns';

    // maximum number of campaigns a customer can have, -1 is unlimited
    public $max_campaigns = -1;
    
    // multiple lists
    public $send_to_multiple_lists = 'no';
    
    // email footer
    public $email_footer;

    public function rules()
    {
        $rules = array(
            array('max_campaigns, send_to_multiple_lists', 'required'),
            array('max_campaigns', 'numerical', 'integerOnly' => true, 'min' => -1),
            array('send_to_multiple_lists', 'in', 'range' => array_keys($this->getYesNoOptions())),
            array('email_footer', 'safe'),
        );
        
        return CMap::mergeArray($rules, parent::rules());    
    }
    
    public function attributeLabels()
    {
        $labels = array(
            'max_campaigns'          => Yii::t('settings', 'Max. campaigns'),
            'send_to_multiple_lists' => Yii::t('settings', 'Send to multiple lists'),
            'email_footer'           => Yii::t('settings', 'Email footer'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());    
    }
    
    public function attributePlaceholders()
    {
        $placeholders = array(
            'max_campaigns' => '',
        );
        
        return CMap::mergeArray($placeholders, parent::attributePlaceholders());
    }
    
    public function attributeHelpTexts()
    {
        $texts = array(
            'max_campaigns'          => Yii::t('settings', 'Maximum number of campaigns a customer can have, set to -1 for unlimited'),
            'send_to_multiple_lists' => Yii::t('settings', 'Whether customers are allowed to select multiple lists when creating a campaign'),
            'email_footer'           => Yii::t('settings', 'The email footer that should be appended to each campaign. It will be inserted exactly before the ending body tag and it can also contain template tags, which will pe parsed. Make sure you style it accordingly'),
        );
        
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }
}
