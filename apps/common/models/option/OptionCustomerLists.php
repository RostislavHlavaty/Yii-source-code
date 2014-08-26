<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * OptionCustomerLists
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.3
 */
 
class OptionCustomerLists extends OptionBase
{
    // settings category
    protected $_categoryName = 'system.customer_lists';
    
    // whether the customers are allowed to import
    public $can_import_subscribers = 'yes';
    
    // whether the customers are allowd to export
    public $can_export_subscribers = 'yes';
    
    // whether the customers are allowed to copy subscribers between the lists
    public $can_copy_subscribers = 'yes';
    
    // maximum number of lists a customer can have, -1 is unlimited
    public $max_lists = -1;
    
    // maximum number of subscribers, -1 is unlimited
    public $max_subscribers = -1 ;
    
    //maximum number of subscribers allowd into a list, -1 is unlimited
    public $max_subscribers_per_list = -1;
    
    //
    public $copy_subscribers_memory_limit;
    
    //
    public $copy_subscribers_at_once = 100;
    
    public function rules()
    {
        $rules = array(
            array('can_import_subscribers, can_export_subscribers, can_copy_subscribers, max_lists, max_subscribers, max_subscribers_per_list, copy_subscribers_at_once', 'required'),
            array('can_import_subscribers, can_export_subscribers, can_copy_subscribers', 'in', 'range' => array_keys($this->getYesNoOptions())),
            array('max_lists, max_subscribers, max_subscribers_per_list', 'numerical', 'integerOnly' => true, 'min' => -1),
            array('copy_subscribers_memory_limit', 'in', 'range' => array_keys($this->getMemoryLimitOptions())),
            array('copy_subscribers_at_once', 'numerical', 'integerOnly' => true, 'min' => 50, 'max' => 1000),
        );
        
        return CMap::mergeArray($rules, parent::rules());    
    }
    
    public function attributeLabels()
    {
        $labels = array(
            'can_import_subscribers'        => Yii::t('settings', 'Can import subscribers'),
            'can_export_subscribers'        => Yii::t('settings', 'Can export subscribers'),
            'can_copy_subscribers'          => Yii::t('settings', 'Can copy subscribers'),
            'max_lists'                     => Yii::t('settings', 'Max. lists'),
            'max_subscribers'               => Yii::t('settings', 'Max. subscribers'),
            'max_subscribers_per_list'      => Yii::t('settings', 'Max. subscribers per list'),
            'copy_subscribers_memory_limit' => Yii::t('settings', 'Copy subscribers memory limit'),
            'copy_subscribers_at_once'      => Yii::t('settings', 'Copy subscribers at once'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());    
    }
    
    public function attributePlaceholders()
    {
        $placeholders = array(
            'can_import_subscribers'        => '',
            'can_export_subscribers'        => '',
            'can_copy_subscribers'          => '',
            'max_lists'                     => '',
            'max_subscribers'               => '',
            'max_subscribers_per_list'      => '',
            'copy_subscribers_memory_limit' => '',
            'copy_subscribers_at_once'      => '',
        );
        
        return CMap::mergeArray($placeholders, parent::attributePlaceholders());
    }
    
    public function attributeHelpTexts()
    {
        $texts = array(
            'can_import_subscribers'        => Yii::t('settings', 'Whether customers are allowed to import subscribers'),
            'can_export_subscribers'        => Yii::t('settings', 'Whether customers are allowed to export subscribers'),
            'can_copy_subscribers'          => Yii::t('settings', 'Whether customers are allowed to copy subscribers from a list into another'),
            'max_lists'                     => Yii::t('settings', 'Maximum number of lists a customer can have, set to -1 for unlimited'),
            'max_subscribers'               => Yii::t('settings', 'Maximum number of subscribers a customer can have, set to -1 for unlimited'),
            'max_subscribers_per_list'      => Yii::t('settings', 'Maximum number of subscribers per list a customer can have, set to -1 for unlimited'),
            'copy_subscribers_memory_limit' => Yii::t('settings', 'Maximum memory the copy subscribers process is allowed to use'),
            'copy_subscribers_at_once'      => Yii::t('settings', 'How many subscribers to copy at once'),
        );
        
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }
}
