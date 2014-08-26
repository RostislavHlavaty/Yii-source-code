<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ListImportAbstract
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.5
 */
 
abstract class ListImportAbstract extends FormModel
{
    public $rows_count = 0;
    
    public $current_page = 1;
    
    public $is_first_batch = 1;
    
    public function rules()
    {
        $rules = array(
            array('rows_count, current_page, is_first_batch', 'numerical', 'integerOnly' => true),
        );
        
        return CMap::mergeArray($rules, parent::rules());
    }
}