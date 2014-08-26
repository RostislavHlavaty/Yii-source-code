<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * DeliveryServerPhpMail
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.2
 */
 
class DeliveryServerPhpMail extends DeliveryServer
{
    protected $serverType = 'php-mail';

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            array('username', 'email'),
        );
        
        return CMap::mergeArray($rules, parent::rules());
    }
    
    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        $labels = array(
            'username'      => Yii::t('servers', 'Email address (from)'),
        );
        
        return CMap::mergeArray(parent::attributeLabels(), $labels);
    }
    
    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return DeliveryServer the static model class
     */
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
    
    public function sendEmail(array $params = array())
    {
        $params = (array)Yii::app()->hooks->applyFilters('delivery_server_before_send_email', $params, $this);
        
        $sent = $this->getMailer()->send($this->getParamsArray($params));
        
        if ($sent) {
            $this->logUsage();
        }
        
        Yii::app()->hooks->doAction('delivery_server_after_send_email', $params, $this, $sent);
        
        return $sent;
    }
    
    public function getDefaultParamsArray()
    {
        $params = array(
            'transport'  => self::TRANSPORT_PHP_MAIL,
        );
        
        return CMap::mergeArray(parent::getDefaultParamsArray(), $params);
    }
    
    protected function beforeValidate()
    {
        $this->hostname = 'php-mail.local.host';
        $this->port     = null;
        $this->timeout  = null;

        return parent::beforeValidate();
    }

    public function attributeHelpTexts()
    {
        $texts = array(
            'username' => Yii::t('servers', 'The email address from where the emails are sent'),
        );
        
        return CMap::mergeArray(parent::attributeHelpTexts(), $texts);
    }
    
    public function attributePlaceholders()
    {
        $placeholders = array(
            'username' => Yii::t('servers', 'i.e: me@domain.com'),
        );
        
        return CMap::mergeArray(parent::attributePlaceholders(), $placeholders);
    }
    
}
