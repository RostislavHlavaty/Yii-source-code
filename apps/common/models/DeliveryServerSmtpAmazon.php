<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * DeliveryServerSmtpAmazon
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class DeliveryServerSmtpAmazon extends DeliveryServerSmtp
{
    protected $serverType = 'smtp-amazon';
    
    public $from;
    
    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            array('password, port, timeout, from', 'required'),
            array('from', 'length', 'min' => 5, 'max' => 150),
            array('from', 'email'),
        );
        
        return CMap::mergeArray($rules, parent::rules());
    }
    
    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        $labels = array(
            'from' => Yii::t('servers', 'From')
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());
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
    
    protected function afterConstruct()
    {
        if (empty($this->hostname)) {
            $this->hostname = 'email-smtp.us-east-1.amazonaws.com';
        }
        $this->from = $this->getModelMetaData()->itemAt('from');
        parent::afterConstruct();
    }
    
    protected function afterFind()
    {
        $this->from = $this->getModelMetaData()->itemAt('from');
        parent::afterFind();
    }
    
    public function getDefaultParamsArray()
    {
        $params = parent::getDefaultParamsArray();
        if ($object = $this->getDeliveryObject()) {
            if (is_object($object) && $object instanceof Campaign) {
                $params['from'] = array($this->getFromEmail() => $object->from_name);
                $params['sender'] = array($this->getSenderEmail() => $object->from_name);
            }
            if (is_object($object) && $object instanceof Lists && !empty($object->default)) {
                $params['from'] = array($this->getFromEmail() => $object->default->from_name);
                $params['sender'] = array($this->getSenderEmail() => $object->default->from_name);
            }
        }
        return $params;
    }
    
    public function getFromEmail()
    {
        return $this->from;
    }
    
    public function getSenderEmail()
    {
        return $this->from;
    }
    
    protected function beforeSave()
    {
        $this->getModelMetaData()->add('from', $this->from);
        return parent::beforeSave();
    }
    
    public function attributeHelpTexts()
    {
        $texts = array(
            'hostname'    => Yii::t('servers', 'Your Amazon SES hostname, usually this is standard and looks like the following: email-smtp.us-east-1.amazonaws.com.'),
            'username'    => Yii::t('servers', 'Your Amazon SES SMTP username, something like: i.e: AKIAIYYYYYYYYYYUBBFQ.'),
            'password'    => Yii::t('servers', 'Your Amazon SES password.'),
            'port'        => Yii::t('servers', 'Amazon SES supports the following ports: 25, 465 or 587.'),
            'protocol'    => Yii::t('servers', 'There is no need to select a protocol for Amazon SES, but if you need a secure connection, TLS is supported.'),
            'from'        => Yii::t('servers', 'Your Amazon SES email address approved for sending emails.'),
        );
        
        return CMap::mergeArray(parent::attributeHelpTexts(), $texts);
    }
    
    public function attributePlaceholders()
    {
        $placeholders = array(
            'hostname'    => Yii::t('servers', 'i.e: email-smtp.us-east-1.amazonaws.com'),
            'from'        => Yii::t('servers', 'you@your-server.com'),
            'username'    => Yii::t('servers', 'i.e: AKIAIYYYYYYYYYYUBBFQ'),
            'password'    => Yii::t('servers', 'your smtp account password')
        );
        
        return CMap::mergeArray(parent::attributePlaceholders(), $placeholders);
    }
}
