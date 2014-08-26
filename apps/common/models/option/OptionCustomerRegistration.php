<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * OptionCustomerRegistration
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.3
 */
 
class OptionCustomerRegistration extends OptionBase
{
    // settings category
    protected $_categoryName = 'system.customer_registration';
    
    // is customer registration allowed?
    public $enabled = 'no';
    
    // default group after registration
    public $default_group;
    
    // remove unconfirmed after x days
    public $unconfirm_days_removal = 7;
    
    // if customers must be approved after registration confirmation
    public $require_approval = 'no';
    
    // whether company info is required
    public $company_required = 'no';
    
    // terms and conditions url
    public $tc_url;

    public function rules()
    {
        $rules = array(
            array('enabled, unconfirm_days_removal, require_approval, company_required', 'required'),
            array('enabled, require_approval, company_required', 'in', 'range' => array_keys($this->getYesNoOptions())),
            array('unconfirm_days_removal', 'numerical', 'integerOnly' => true, 'min' => 1, 'max' => 365),
            array('default_group', 'exist', 'className' => 'CustomerGroup', 'attributeName' => 'group_id'),
            array('tc_url', 'url'),
        );
        
        return CMap::mergeArray($rules, parent::rules());    
    }
    
    protected function beforeValidate()
    {
        if ($this->enabled == self::TEXT_NO) {
            $this->default_group = '';
        }
        
        return parent::beforeValidate();
    }
    
    public function attributeLabels()
    {
        $labels = array(
            'enabled'                   => Yii::t('settings', 'Enabled'),
            'unconfirm_days_removal'    => Yii::t('settings', 'Unconfirmed removal days'),
            'default_group'             => Yii::t('settings', 'Default group'),
            'require_approval'          => Yii::t('settings', 'Require approval'),
            'company_required'          => Yii::t('settings', 'Require company info'),
            'tc_url'                    => Yii::t('settings', 'Terms and conditions url'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());    
    }
    
    public function attributePlaceholders()
    {
        $placeholders = array(
            'enabled'                => '',
            'unconfirm_days_removal' => '',
            'default_group'          => '',
            'require_approval'       => '',
            'company_required'       => '',
            'tc_url'                 => '',
        );
        
        return CMap::mergeArray($placeholders, parent::attributePlaceholders());
    }
    
    public function attributeHelpTexts()
    {
        $texts = array(
            'enabled'                => Yii::t('settings', 'Whether the customer registration is enabled'),
            'unconfirm_days_removal' => Yii::t('settings', 'How many days to keep the unconfirmed customers in the system before permanent removal'),
            'default_group'          => Yii::t('settings', 'Default group for customer after registration'),
            'require_approval'       => Yii::t('settings', 'Whether customers must be approved after they have confirmed the registration'),
            'company_required'       => Yii::t('settings', 'Whether the company basic info is required'),
            'tc_url'                 => Yii::t('settings', 'The url for terms and conditions for the customer to read before signup'),
        );
        
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }

    public function getGroupsList()
    {
        static $options;
        if ($options !== null) {
            return $options;
        }
        
        $options = array();
        $groups  = CustomerGroup::model()->findAll();
        
        foreach ($groups as $group) {
            $options[$group->group_id] = $group->name;
        }
        
        return $options;
    }
}
