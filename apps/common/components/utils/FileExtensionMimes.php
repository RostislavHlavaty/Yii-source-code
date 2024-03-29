<?php if ( ! defined('MW_PATH')) exit('No direct script access allowed');

/**
 * FileExtensionMimes
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.4.2
 */
 
class FileExtensionMimes extends CApplicationComponent
{
    public $alias = '%s.config.mimes';
    
    protected $_mimes;
    
    /**
     * FileExtensionMimes::get()
     * 
     * @param string $extension
     * @return @CMap
     */
    public function get($extension)
    {
        if (!$this->getMimes()->contains($extension)) {
            $this->getMimes()->add($extension, new CMap());
        }
        $mimes = $this->getMimes()->itemAt($extension);
        if (empty($mimes) || !is_object($mimes) || !($mimes instanceof CMap)) {
            $mimes = new CMap($mimes);
            $this->getMimes()->add($extension, $mimes);
        }
        return $mimes;
    }
    
    /**
     * FileExtensionMimes::getMimes()
     * 
     * @return @CMap
     */
    protected function getMimes()
    {
        if ($this->_mimes !== null) {
            return $this->_mimes;
        }

        $fileData = new CMap((array)require(Yii::getPathOfAlias(sprintf($this->alias, 'common')) . '.php'));
        if (is_file($customFile = Yii::getPathOfAlias(sprintf($this->alias, 'common') .'-custom') . '.php')) {
            $fileData->mergeWith((array)require($customFile));
        }
        if (is_file($customFile = Yii::getPathOfAlias(sprintf($this->alias, MW_APP_NAME)) . '.php')) {
            $fileData->mergeWith((array)require($customFile));
        }
        if (is_file($customFile = Yii::getPathOfAlias(sprintf($this->alias, MW_APP_NAME) .'-custom') . '.php')) {
            $fileData->mergeWith((array)require($customFile));
        }
        
        return $this->_mimes = $fileData;
    }
    
}