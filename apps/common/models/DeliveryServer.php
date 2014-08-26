<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * DeliveryServer
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
/**
 * This is the model class for table "delivery_server".
 *
 * The followings are the available columns in table 'delivery_server':
 * @property integer $server_id
 * @property integer $customer_id
 * @property integer $bounce_server_id
 * @property string $type
 * @property string $hostname
 * @property string $username
 * @property string $password
 * @property integer $port
 * @property string $protocol
 * @property integer $timeout
 * @property integer $probability
 * @property integer $hourly_quota
 * @property string $meta_data
 * @property string $confirmation_key
 * @property string $locked
 * @property string $status
 * @property string $date_added
 * @property string $last_updated
 * 
 * The followings are the available model relations:
 * @property Campaign[] $campaigns
 * @property BounceServer $bounceServer
 * @property Customer $customer
 * @property DeliveryServerUsageLog[] $usageLogs
 * @property DeliveryServerDomainPolicy[] $domainPolicies
 */
class DeliveryServer extends ActiveRecord
{
    const TRANSPORT_SMTP = 'smtp';
    
    const TRANSPORT_SMTP_AMAZON = 'smtp-amazon';
    
    const TRANSPORT_SENDMAIL = 'sendmail';
    
    const TRANSPORT_PHP_MAIL = 'php-mail';
    
    const TRANSPORT_PICKUP_DIRECTORY = 'pickup-directory';
    
    const DELIVERY_FOR_SYSTEM = 'system';
    
    const DELIVERY_FOR_CAMPAIGN_TEST = 'campaign-test';
    
    const DELIVERY_FOR_TEMPLATE_TEST = 'template-test';
    
    const DELIVERY_FOR_CAMPAIGN = 'campaign';
    
    const DELIVERY_FOR_LIST = 'list';
    
    const DELIVERY_FOR_TRANSACTIONAL = 'transactional';
    
    const STATUS_IN_USE = 'in-use';
    
    const STATUS_HIDDEN = 'hidden';
    
    const TEXT_NO = 'no';
    
    const TEXT_YES = 'yes';
    
    protected $serverType = 'smtp';
    
    // flag to mark what kind of delivery we are making
    protected $_deliveryFor = 'system';
    
    // what do we deliver
    protected $_deliveryObject;
    
    // mailer object
    protected $_mailer;
    
    // list of additional headers to send for this server
    public $additional_headers = array();

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{delivery_server}}';
    }
    
    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            array('hostname, username, probability, hourly_quota', 'required'),
            
            array('hostname, username, password', 'length', 'min' => 3, 'max'=>150),
            array('port, probability, timeout', 'numerical', 'integerOnly'=>true),
            array('port', 'length', 'min'=> 2, 'max' => 5),
            array('probability', 'length', 'min'=> 1, 'max' => 3),
            array('probability', 'in', 'range' => array_keys($this->getProbabilityArray())),
            array('timeout', 'numerical', 'min' => 5, 'max' => 120),
            array('protocol', 'in', 'range' => array_keys($this->getProtocolsArray())),
            array('hourly_quota', 'numerical', 'integerOnly' => true, 'min' => 0),
            array('hourly_quota', 'length', 'max' => 11),
            array('bounce_server_id', 'exist', 'className' => 'BounceServer', 'attributeName' => 'server_id', 'allowEmpty' => true),
            array('hostname, username, type, status, customer_id', 'safe', 'on' => 'search'),
            array('additional_headers', '_validateAdditionalHeaders'),
            array('customer_id', 'exist', 'className' => 'Customer', 'attributeName' => 'customer_id', 'allowEmpty' => true),
            array('locked', 'in', 'range' => array_keys($this->getYesNoOptions())),
        );
        
        return CMap::mergeArray($rules, parent::rules());
    }
    
    /**
     * @return array relational rules.
     */
    public function relations()
    {
        $relations = array(
            'campaigns'         => array(self::MANY_MANY, 'Campaign', '{{campaign_to_delivery_server}}(server_id, campaign_id)'),
            'bounceServer'      => array(self::BELONGS_TO, 'BounceServer', 'bounce_server_id'),
            'customer'          => array(self::BELONGS_TO, 'Customer', 'customer_id'),
            'usageLogs'         => array(self::HAS_MANY, 'DeliveryServerUsageLog', 'server_id'),
            'domainPolicies'    => array(self::HAS_MANY, 'DeliveryServerDomainPolicy', 'server_id'),
        );
        
        return CMap::mergeArray($relations, parent::relations());
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        $labels = array(
            'server_id'                     => Yii::t('servers', 'Server'),
            'customer_id'                   => Yii::t('servers', 'Customer'),
            'bounce_server_id'              => Yii::t('servers', 'Bounce server'),
            'type'                          => Yii::t('servers', 'Type'),
            'hostname'                      => Yii::t('servers', 'Hostname'),
            'username'                      => Yii::t('servers', 'Username'),
            'password'                      => Yii::t('servers', 'Password'),
            'port'                          => Yii::t('servers', 'Port'),
            'protocol'                      => Yii::t('servers', 'Protocol'),
            'timeout'                       => Yii::t('servers', 'Timeout'),
            'probability'                   => Yii::t('servers', 'Probability'),
            'hourly_quota'                  => Yii::t('servers', 'Hourly quota'),
            'meta_data'                     => Yii::t('servers', 'Meta data'),
            'additional_headers'            => Yii::t('servers', 'Additional headers'),
            'locked'                        => Yii::t('servers', 'Locked'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());
    }
    
    /**
    * Retrieves a list of models based on the current search/filter conditions.
    * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
    */
    public function search()
    {
        $criteria=new CDbCriteria;
        
        if (!empty($this->customer_id)) {
            if (is_numeric($this->customer_id)) {
                $criteria->compare('t.customer_id', $this->customer_id);
            } else {
                $criteria->with = array(
                    'customer' => array(
                        'joinType'  => 'INNER JOIN',
                        'condition' => 'CONCAT(customer.first_name, " ", customer.last_name) LIKE :name',
                        'params'    => array(
                            ':name'    => '%' . $this->customer_id . '%',
                        ),
                    )
                );
            }
        }
        $criteria->compare('t.hostname', $this->hostname, true);
        $criteria->compare('t.username', $this->username, true);
        $criteria->compare('t.type', $this->type);
        $criteria->compare('t.status', $this->status);
        
        $criteria->addNotInCondition('t.status', array(self::STATUS_HIDDEN));
    
        return new CActiveDataProvider(get_class($this), array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => (int)$this->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
            'sort'=>array(
                'defaultOrder'  => array(
                    'server_id' => CSort::SORT_DESC,
                ),
            ),
        ));
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
        $this->additional_headers = (array)$this->getModelMetaData()->itemAt('additional_headers');
        $this->_deliveryFor = self::DELIVERY_FOR_SYSTEM;
        parent::afterConstruct();
    }
    
    protected function afterFind()
    {
        $this->additional_headers = (array)$this->getModelMetaData()->itemAt('additional_headers');
        $this->_deliveryFor = self::DELIVERY_FOR_SYSTEM;
        parent::afterFind();
    }
    
    // send method
    public function sendEmail(array $params = array())
    {
        return false;
    }
    
    public function getMailer()
    {
        if ($this->_mailer === null) {
            $this->_mailer = clone Yii::app()->mailer;
        }
        return $this->_mailer;
    }
    
    protected function afterValidate()
    {
        if (!$this->isNewRecord && !MW_IS_CLI) {
            if (empty($this->customer_id)) {
                $this->locked = self::TEXT_NO;
            }
        
            $model = self::model()->findByPk((int)$this->server_id);
            $keys = array('hostname', 'username', 'password', 'port', 'protocol');
            if (!empty($this->bounce_server_id)) {
                array_push($keys, 'bounce_server_id');
            }
            foreach ($keys as $key) {
                if ($model->$key !== $this->$key) {
                    $this->status = self::STATUS_INACTIVE;
                    break;
                }
            }
        }        
        return parent::afterValidate();
    }
    
    protected function beforeSave()
    {
        $this->getModelMetaData()->add('additional_headers', (array)$this->additional_headers);
        $this->type = $this->serverType;
        
        return parent::beforeSave();
    }
    
    protected function beforeDelete()
    {
        if (!$this->getCanBeDeleted()) {
            return false;
        }
        
        return parent::beforeDelete();
    }
    
    public function attributeHelpTexts()
    {
        $texts = array(
            'bounce_server_id'  => Yii::t('servers', 'The server that will handle bounce emails for this SMTP server.'),
            'hostname'          => Yii::t('servers', 'The hostname of your SMTP server, usually something like smtp.domain.com.'),
            'username'          => Yii::t('servers', 'The username of your SMTP server, usually something like you@domain.com.'),
            'password'          => Yii::t('servers', 'The password of your SMTP server, used in combination with your username to authenticate your request.'),
            'port'              => Yii::t('servers', 'The port of your SMTP server, usually this is 25, but 465 and 587 are also valid choices for some of the servers depending on the security protocol they are using. If unsure leave it to 25.'),
            'protocol'          => Yii::t('servers', 'The security protocol used to access this server. If unsure, leave it blank or select TLS if blank does not work for you.'),
            'timeout'           => Yii::t('servers', 'The maximum number of seconds we should wait for the server to respond to our request. 30 seconds is a proper value.'),
            'probability'       => Yii::t('servers', 'When having multiple servers from where you send, the probability helps to choose one server more than another. This is useful if you are using servers with various quota limits. A lower probability means a lower sending rate using this server.'),
            'hourly_quota'      => Yii::t('servers', 'In case there are limits that apply for sending with this server, you can set a hourly quota for it and it will only send in one hour as many emails as you set here. Set it to 0 in order to not apply any hourly limit.'),
            'locked'            => Yii::t('servers', 'Whether this server is locked and assigned customer cannot change or delete it'),
        );
        
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }
    
    public function getBounceServersArray()
    {
        static $_options = array();
        if (!empty($_options)) {
            return $_options;
        }
        
        $criteria = new CDbCriteria();
        $criteria->select = 'server_id, hostname, username, service';
        
        if ($this->customer_id) {
            $criteria->compare('customer_id', (int)$this->customer_id);
        }
        
        $criteria->addInCondition('status', array(BounceServer::STATUS_ACTIVE));
        $criteria->order = 'server_id DESC';
        $models = BounceServer::model()->findAll($criteria);
        
        $_options[''] = Yii::t('app', 'Choose');
        foreach ($models as $model) {
            $_options[$model->server_id] = sprintf('%s - %s(%s)', strtoupper($model->service), $model->hostname, $model->username);
        }
        
        return $_options;
    }
    
    public function getDisplayBounceServer()
    {
        if (empty($this->bounceServer)) {
            return;
        }
        
        $model = $this->bounceServer;
        
        return sprintf('%s - %s(%s)', strtoupper($model->service), $model->hostname, $model->username);
    }
    
    public function getProtocolsArray()
    {
        return array(
            ''          => Yii::t('app', 'Choose'),
            'tls'       => 'TLS',
            'ssl'       => 'SSL',
            'starttls'  => 'STARTTLS',
        );
    }
    
    public function getProtocolName()
    {
        $protocols = $this->getProtocolsArray();
        return !empty($this->protocol) && !empty($protocols[$this->protocol]) ? $protocols[$this->protocol] : Yii::t('app', 'Default');
    }
    
    public function getProbabilityArray()
    {
        $options = array('' => Yii::t('app', 'Choose'));
        for ($i = 5; $i <= 100; ++$i) {
            if ($i % 5 == 0) {
                $options[$i] = $i . ' %';
            }
        }
        return $options;
    }
    
    public function getDefaultParamsArray()
    {
        $params = array(
            'transport'     => self::TRANSPORT_SMTP,
            'hostname'      => null,
            'username'      => null,
            'password'      => null,
            'port'          => 25,
            'timeout'       => 30,
            'protocol'      => null,
            'probability'   => 100,
            'headers'       => (array)$this->additional_headers,
            'from'          => $this->getFromEmail(),
            'sender'        => $this->getSenderEmail(),
            'returnPath'    => $this->getSenderEmail(),
            'replyTo'       => $this->getFromEmail(),
            'to'            => null,
            'subject'       => null,
            'body'          => null,
        );
        
        if ($object = $this->getDeliveryObject()) {
            if (is_object($object) && $object instanceof Campaign) {
                $params['from']    = array($object->from_email => $object->from_name);
                $params['replyTo'] = array($object->reply_to   => $object->from_name);
            }
            if (is_object($object) && $object instanceof Lists && !empty($object->default)) {
                $params['from']    = array($object->default->from_email => $object->default->from_name);
                $params['replyTo'] = array($object->default->from_email => $object->default->from_name);
            }
        }
        
        if (!empty($this->bounce_server_id) && !empty($this->bounceServer)) {
            $params['returnPath'] = $this->bounceServer->username;
        }

        return $params;
    }
    
    public function getParamsArray(array $params = array())
    {
        // avoid merging arrays recursive ending up with multiple arrays when we expect only one.
        $_params    = CMap::mergeArray($this->getDefaultParamsArray(), $this->attributes);
        $uniqueKeys = array('from', 'sender', 'returnPath', 'replyTo', 'to');
        foreach ($uniqueKeys as $key) {
            if (array_key_exists($key, $params) && array_key_exists($key, $_params)) {
                unset($_params[$key]);
            }
        }
        return CMap::mergeArray($_params, $params);
    }
    
    public function getFromEmail()
    {
        return $this->username;
    }
    
    public function getSenderEmail()
    {
        return $this->username;
    }
    
    /**
     * Can be used in order to do checks against missing requirements!
     * If must return false if all requirements are fine, otherwise a message about missing requirements!
     */
    public function requirementsFailed()
    {
        return false;
    }
    
    public static function getNameByType($type)
    {
        $mapping = self::getTypesMapping();
        if (!isset($mapping[$type])) {
            return null;
        }
        return ucwords(str_replace(array('-'), ' ', Yii::t('servers', $type)));
    }
    
    public static function getTypesMapping()
    {
        static $mapping;
        if ($mapping !== null) {
            return (array)$mapping;
        }
        
        $mapping = array(
            self::TRANSPORT_SMTP              => 'DeliveryServerSmtp',
            self::TRANSPORT_SMTP_AMAZON       => 'DeliveryServerSmtpAmazon',
            self::TRANSPORT_SENDMAIL          => 'DeliveryServerSendmail',
            self::TRANSPORT_PHP_MAIL          => 'DeliveryServerPhpMail',
            self::TRANSPORT_PICKUP_DIRECTORY  => 'DeliveryServerPickupDirectory',
        );
        
        return $mapping;
    }
    
    public static function getCustomerTypesMapping(Customer $customer = null)
    {
        static $mapping;
        if ($mapping !== null) {
            return (array)$mapping;
        }
        
        $mapping = self::getTypesMapping();
        if (!$customer) {
            $allowed = (array)Yii::app()->options->get('system.customer_servers.allowed_server_types', array());
        } else {
            $allowed = (array)$customer->getGroupOption('servers.allowed_server_types', array());
        }
        
        foreach ($mapping as $type => $name) {
            if (!in_array($type, $allowed)) {
                unset($mapping[$type]);
                continue;
            }
            if (self::model($name)->requirementsFailed()) {
                unset($mapping[$type]);
            }
        }
        
        return $mapping;
    }
    
    public function getStatusesList()
    {
        return array(
            self::STATUS_ACTIVE     => ucfirst(Yii::t('app', self::STATUS_ACTIVE)),
            self::STATUS_IN_USE     => ucfirst(Yii::t('app', self::STATUS_IN_USE)),
            self::STATUS_INACTIVE   => ucfirst(Yii::t('app', self::STATUS_INACTIVE)),
        );
    }
    
    public static function getTypesList()
    {
        $list = array();
        foreach (self::getTypesMapping() as $key => $value) {
            $list[$key] = self::getNameByType($key);
        }
        return $list;
    }
    
    public static function getCustomerTypesList()
    {
        $list = array();
        foreach (self::getCustomerTypesMapping() as $key => $value) {
            $list[$key] = self::getNameByType($key);
        }
        return $list;
    }
    
    public function setDeliveryObject($object)
    {
        $this->_deliveryObject = $object;
        return $this;   
    }
    
    public function getDeliveryObject()
    {
        return $this->_deliveryObject;
    }
    
    public function setDeliveryFor($deliveryFor)
    {
        $this->_deliveryFor = $deliveryFor;
        return $this;
    }
    
    public function getDeliveryFor()
    {
        return $this->_deliveryFor;
    }
    
    public function isDeliveryFor($for)
    {
        return $this->_deliveryFor == $for;
    }
    
    /**
     * This is deprecated and must be removed in future
     */
    public function markHourlyUsage($refresh = true)
    {
        return $this;
    }
    
    public function logUsage()
    {
        $log = new DeliveryServerUsageLog();
        $log->server_id = (int)$this->server_id;
        
        if ($customer = $this->getCustomerByDeliveryObject()) {
            $log->customer_id = (int)$customer->customer_id;
            if (!$this->getDeliveryIsCountableForCustomer()) {
                $log->customer_countable = DeliveryServerUsageLog::TEXT_NO;
            }
        }
        
        $log->delivery_for = $this->getDeliveryFor();
        $log->save(false);
        
        return $this;
    }
    
    public function getDeliveryIsCountableForCustomer()
    {
        if (!($deliveryObject = $this->getDeliveryObject())) {
            return false;
        }
        
        if (!($customer = $this->getCustomerByDeliveryObject())) {
            return false;
        }
        
        $trackableDeliveryFor = array(self::DELIVERY_FOR_CAMPAIGN, self::DELIVERY_FOR_CAMPAIGN_TEST, self::DELIVERY_FOR_TEMPLATE_TEST, self::DELIVERY_FOR_LIST);
        if (!in_array($this->getDeliveryFor(), $trackableDeliveryFor)) {
            return false;
        }

        if($deliveryObject instanceof Campaign) {
            if ($this->isDeliveryFor(self::DELIVERY_FOR_CAMPAIGN) && $customer->getGroupOption('quota_counters.campaign_emails', self::TEXT_YES) == self::TEXT_YES) {
                return true;
            }
            if ($this->isDeliveryFor(self::DELIVERY_FOR_CAMPAIGN_TEST) && $customer->getGroupOption('quota_counters.campaign_test_emails', self::TEXT_YES) == self::TEXT_YES) {
                return true;
            }
            return false;
        }
        
        if($deliveryObject instanceof CustomerEmailTemplate) {
            if ($this->isDeliveryFor(self::DELIVERY_FOR_TEMPLATE_TEST) && $customer->getGroupOption('quota_counters.template_test_emails', self::TEXT_YES) == self::TEXT_YES) {
                return true;
            }
            return false;
        }
        
        if($deliveryObject instanceof Lists) {
            if ($this->isDeliveryFor(self::DELIVERY_FOR_LIST) && $customer->getGroupOption('quota_counters.list_emails', self::TEXT_YES) == self::TEXT_YES) {
                return true;
            }
            return false;
        }
        
        if($deliveryObject instanceof TransactionalEmail) {
            if ($this->isDeliveryFor(self::DELIVERY_FOR_TRANSACTIONAL) && $customer->getGroupOption('quota_counters.transactional_emails', self::TEXT_YES) == self::TEXT_YES) {
                return true;
            }
            return false;
        }
        
        return false;
    }
    
    public function countHourlyUsage()
    {
        $criteria = new CDbCriteria();
        $criteria->compare('server_id', (int)$this->server_id);
        $criteria->addCondition('`date_added` BETWEEN DATE_FORMAT(NOW(), "%Y-%m-%d %H:00:00") AND DATE_FORMAT(NOW() + INTERVAL 1 HOUR, "%Y-%m-%d %H:00:00")');
        return DeliveryServerUsageLog::model()->count($criteria);
    }
    
    public function getCanHaveHourlyQuota()
    {
        return !$this->isNewRecord && $this->hourly_quota > 0;
    }
    
    public function getIsOverQuota()
    {
        if ($this->isNewRecord) {
            return false;
        }
        return $this->getCanHaveHourlyQuota() && $this->countHourlyUsage() >= $this->hourly_quota;
    }
    
    public function getCanBeDeleted()
    {
        return !in_array($this->status, array(self::STATUS_IN_USE));
    }
    
    public function getCanBeUpdated()
    {
        return !in_array($this->status, array(self::STATUS_IN_USE, self::STATUS_HIDDEN));
    }
    
    public function setIsInUse($refresh = true)
    {
        if ($this->getIsInUse()) {
            return $this;
        }
        
        $this->status = self::STATUS_IN_USE;
        $this->save(false);
        
        if ($refresh) {
            $this->refresh();
        }
        
        return $this;
    }
    
    public function setIsNotInUse($refresh = true)
    {
        if (!$this->getIsInUse()) {
            return $this;
        }
        
        $this->status = self::STATUS_ACTIVE;
        $this->save(false);
        
        if ($refresh) {
            $this->refresh();
        }
        
        return $this;
    }
    
    public function getIsInUse()
    {
        return $this->status === self::STATUS_IN_USE;
    }
    
    public function getIsLocked()
    {
        return $this->locked === self::TEXT_YES;
    }
    
    public function canSendToDomainOf($emailAddress)
    {
        return DeliveryServerDomainPolicy::canSendToDomainOf($this->server_id, $emailAddress);
    }
    
    public function getNeverAllowedHeaders()
    {
        $neverAllowed = array(
            'From', 'To', 'Subject', 'Date', 'Return-Path', 'Sender', 
            'Reply-To', 'Message-Id', 'List-Unsubscribe', 
            'Content-Type', 'Content-Transfer-Encoding', 'Content-Length', 'MIME-Version',
            'X-Sender', 'X-Receiver',
        );
        
        $neverAllowed = (array)Yii::app()->hooks->applyFilters('delivery_server_never_allowed_headers', $neverAllowed);
        return $neverAllowed;
    }
    
    public function getCustomerByDeliveryObject()
    {
        return self::parseDeliveryObjectForCustomer($this->getDeliveryObject());
    }
    
    public static function parseDeliveryObjectForCustomer($deliveryObject)
    {
        $customer = null;
        if ($deliveryObject && is_object($deliveryObject)) {
            if ($deliveryObject instanceof Customer) {
                $customer = $deliveryObject;
            } elseif ($deliveryObject instanceof Campaign) {
                $customer = !empty($deliveryObject->list) && !empty($deliveryObject->list->customer) ? $deliveryObject->list->customer : null;
            } elseif ($deliveryObject instanceof Lists) {
                $customer = !empty($deliveryObject->customer) ? $deliveryObject->customer : null;
            } elseif ($deliveryObject instanceof CustomerEmailTemplate) {
                $customer = !empty($deliveryObject->customer) ? $deliveryObject->customer : null;
            } elseif ($deliveryObject instanceof TransactionalEmail && !empty($deliveryObject->customer_id)) {
                $customer = !empty($deliveryObject->customer) ? $deliveryObject->customer : null;
            }
        }
        if (!$customer && Yii::app()->hasComponent('customer') && Yii::app()->customer->getId() > 0) {
            $customer = Yii::app()->customer->getModel();
        }
        return $customer;
    }
    
    public function _validateAdditionalHeaders($attribute, $params)
    {
        $headers = $this->$attribute;
        if (empty($headers) || !is_array($headers)) {
            $headers = array();
        }

        $this->$attribute   = array();
        $_headers           = array();
        
        $notAllowedHeaders  = (array)$this->getNeverAllowedHeaders();
        $notAllowedHeaders  = array_map('strtolower', $notAllowedHeaders);
        
        // try to be a bit restrictive
        $namePattern        = '/([a-z0-9\-\_])*/i';
        $valuePattern       = '/.*/i';
        
        foreach ($headers as $index => $header) {
            
            if (!is_array($header) || !isset($header['name'], $header['value'])) {
                unset($headers[$index]);
                continue;
            }
            
            $name = preg_replace('/:\s/', '', trim($header['name']));
            $value = preg_replace('/:/', '', trim($header['value']));

            if (empty($name) || in_array(strtolower($name), $notAllowedHeaders) || stripos($name, 'X-Mw-') === 0 || !preg_match($namePattern, $name)) {
                unset($headers[$index]);
                continue;
            }
            
            if (empty($value) || !preg_match($valuePattern, $value)) {
                unset($headers[$index]);
                continue;
            }
            
            $_headers[$name] = $value;
        }
        
        $this->$attribute = $_headers;
    }

    // main entry point to pick a delivery server.
    public static function pickServer($currentServerId = 0, $deliveryObject = null)
    {
        $logTableName = DeliveryServerUsageLog::model()->tableName();
        $options      = Yii::app()->options;
        
        if ($customer = self::parseDeliveryObjectForCustomer($deliveryObject)) {
            if ($customer->getIsOverQuota()) {
                return false;
            }
            
            $condition = 't.customer_id = :customer_id AND (
            `t`.`hourly_quota` = 0 OR `t`.`hourly_quota` > (
                SELECT COUNT(*) FROM `'.$logTableName.'` 
                    WHERE server_id = `t`.`server_id` AND 
                    `date_added` BETWEEN DATE_FORMAT(NOW(), "%Y-%m-%d %H:00:00") AND DATE_FORMAT(NOW() + INTERVAL 1 HOUR, "%Y-%m-%d %H:00:00")
                )
            )';

            $criteria = new CDbCriteria();
            $criteria->condition = $condition;
            $criteria->params[':customer_id'] = (int)$customer->customer_id;
            
            $server = self::processPickServerCriteria($criteria, $currentServerId, $deliveryObject);
            if (!empty($server)) {
                return $server;
            }

            if ($customer->getGroupOption('servers.can_send_from_system_servers', 'yes') != 'yes') {
                return false;
            }
        }
        
        $condition = 't.customer_id IS NULL AND (
        `t`.`hourly_quota` = 0 OR `t`.`hourly_quota` > (
            SELECT COUNT(*) FROM `'.$logTableName.'` 
                WHERE server_id = `t`.`server_id` AND 
                `date_added` BETWEEN DATE_FORMAT(NOW(), "%Y-%m-%d %H:00:00") AND DATE_FORMAT(NOW() + INTERVAL 1 HOUR, "%Y-%m-%d %H:00:00")
            )
        )';

        $criteria = new CDbCriteria();
        $criteria->condition = $condition;
        return self::processPickServerCriteria($criteria, $currentServerId, $deliveryObject);
    }

    protected static function processPickServerCriteria(CDbCriteria $criteria, $currentServerId = 0, $deliveryObject = null)
    {
        $campaignServers = array();
        if (!empty($deliveryObject) && $deliveryObject instanceof Campaign) {
            $list      = $deliveryObject->list;
            $customer  = $list->customer;
            $canSelect = $customer->getGroupOption('servers.can_select_delivery_servers_for_campaign', 'no') == 'yes';

            if ($canSelect) {
                $_campaignServers = CampaignToDeliveryServer::model()->findAllByAttributes(array(
                    'campaign_id' => $deliveryObject->campaign_id,
                ));
                foreach ($_campaignServers as $mdl) {
                    $_criteria = new CDbCriteria();
                    $_criteria->select = 'server_id, hourly_quota';
                    $_criteria->compare('server_id', (int)$mdl->server_id);
                    $_criteria->addInCondition('status', array(self::STATUS_ACTIVE, self::STATUS_IN_USE));
                    $server = self::model()->find($_criteria);
                    if (!empty($server) && !$server->getIsOverQuota()) {
                        $campaignServers[] = $server->server_id;
                    }
                }
                // if there are campaign servers specified but there are no valid servers, we stop!
                // note, not a final decision, maybe just allow this case after all.
                if (count($_campaignServers) > 0 && empty($campaignServers)) {
                    return false;
                }
                unset($_campaignServers);    
            }
        }
        
        $_criteria = new CDbCriteria();
        $_criteria->select = 't.server_id, t.type';
        if (!empty($campaignServers)) {
            $_criteria->addInCondition('t.server_id', $campaignServers);
        }
        $_criteria->addInCondition('t.status', array(self::STATUS_ACTIVE, self::STATUS_IN_USE));
        $_criteria->order = 't.probability DESC';
        $_criteria->mergeWith($criteria);

        $_servers = self::model()->findAll($_criteria);
        if (empty($_servers)) {
            return false;
        }
        
        $mapping = self::getTypesMapping();
        foreach ($_servers as $index => $srv) {
            if (!isset($mapping[$srv->type])) {
                unset($_servers[$index]);
                continue;
            }
            $_servers[$index] = self::model($mapping[$srv->type])->findByPk($srv->server_id);
        }

        if (empty($_servers)) {
            return false;
        }
        
        $probabilities  = array();
        foreach ($_servers as $srv) {
            if (!isset($probabilities[$srv->probability])) {
                $probabilities[$srv->probability] = array();
            }
            $probabilities[$srv->probability][] = $srv;
        }
        
        $server                 = null;
        $probabilitySum         = array_sum(array_keys($probabilities));
        $probabilityPercentage  = array();
        $cumulative             = array();
        
        foreach ($probabilities as $probability => $probabilityServers) {
            $probabilityPercentage[$probability] = ($probability / $probabilitySum) * 100;
        }
        asort($probabilityPercentage);
        
        foreach ($probabilityPercentage as $probability => $percentage) {
            $cumulative[$probability] = end($cumulative) + $percentage;
        }
        asort($cumulative);
        
        $lowest      = floor(current($cumulative));
        $probability = rand($lowest, 100);

        foreach($cumulative as $key => $value) {
            if ($value > $probability)  {
                $rand   = array_rand(array_keys($probabilities[$key]), 1);
                $server = $probabilities[$key][$rand];
                break;
            }
        }

        if (empty($server)) {
            $rand   = array_rand(array_keys($_servers), 1);
            $server = $_servers[$rand];
        }
        
        if (count($_servers) > 1 && $currentServerId > 0 && $server->server_id == $currentServerId) {
            return self::processPickServerCriteria($criteria, $server->server_id, $deliveryObject);
        }
        
        $server->getMailer()->reset();
        
        if (empty($deliveryObject)) {
            $server->setDeliveryFor(self::DELIVERY_FOR_SYSTEM);
        } elseif ($deliveryObject instanceof Campaign) {
            $server->setDeliveryFor(self::DELIVERY_FOR_CAMPAIGN);
        } elseif ($deliveryObject instanceof Lists) {
            $server->setDeliveryFor(self::DELIVERY_FOR_LIST);
        } elseif ($deliveryObject instanceof CustomerEmailTemplate) {
            $server->setDeliveryFor(self::DELIVERY_FOR_TEMPLATE_TEST);
        }
        
        return $server;
    }
}
