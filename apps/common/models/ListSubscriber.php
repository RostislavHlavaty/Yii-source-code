<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ListSubscriber
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
/**
 * This is the model class for table "list_subscriber".
 *
 * The followings are the available columns in table 'list_subscriber':
 * @property integer $subscriber_id
 * @property integer $list_id
 * @property string $unique_id
 * @property string $email
 * @property string $source
 * @property string $status
 * @property string $ip_address
 * @property string $date_added
 * @property string $last_updated
 *
 * The followings are the available model relations:
 * @property CampaignBounceLog[] $bounceLogs
 * @property CampaignDeliveryLog[] $deliveryLogs
 * @property CampaignTrackOpen[] $trackOpens
 * @property CampaignTrackUnsubscribe[] $trackUnsubscribes
 * @property CampaignTrackUrl[] $trackUrls
 * @property EmailBlacklist $emailBlacklist
 * @property ListFieldValue[] $fieldValues
 * @property Lists $list
 */
class ListSubscriber extends ActiveRecord
{
    const STATUS_CONFIRMED = 'confirmed';
    
    const STATUS_UNCONFIRMED = 'unconfirmed';
    
    const STATUS_UNSUBSCRIBED = 'unsubscribed';
    
    const STATUS_BLACKLISTED = 'blacklisted';
    
    const SOURCE_WEB = 'web';
    
    const SOURCE_API = 'api';
    
    const SOURCE_IMPORT = 'import';
    
    const BULK_SUBSCRIBE = 'subscribe';
    
    const BULK_UNSUBSCRIBE = 'unsubscribe';
    
    const BULK_DELETE = 'delete';
    
    const BULK_BLACKLIST = 'blacklist';
    
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{list_subscriber}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            array('status', 'in', 'range' => array_keys($this->getStatusesList())),
        );
        return CMap::mergeArray($rules, parent::rules());
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        $relations = array(
            'bounceLogs' => array(self::HAS_MANY, 'CampaignBounceLog', 'subscriber_id'),
            'deliveryLogs' => array(self::HAS_MANY, 'CampaignDeliveryLog', 'subscriber_id'),
            'trackOpens' => array(self::HAS_MANY, 'CampaignTrackOpen', 'subscriber_id'),
            'trackUnsubscribes' => array(self::HAS_MANY, 'CampaignTrackUnsubscribe', 'subscriber_id'),
            'trackUrls' => array(self::HAS_MANY, 'CampaignTrackUrl', 'subscriber_id'),
            'emailBlacklist' => array(self::HAS_ONE, 'EmailBlacklist', 'subscriber_id'),
            'fieldValues' => array(self::HAS_MANY, 'ListFieldValue', 'subscriber_id'),
            'list' => array(self::BELONGS_TO, 'Lists', 'list_id'),
        );

        return CMap::mergeArray($relations, parent::relations());
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        $labels = array(
            'subscriber_id' => Yii::t('list_subscribers', 'Subscriber'),
            'list_id'       => Yii::t('list_subscribers', 'List'),
            'unique_id'     => Yii::t('list_subscribers', 'Unique id'),
            'email'         => Yii::t('list_subscribers', 'Email'),
            'source'        => Yii::t('list_subscribers', 'Source'),
            'ip_address'    => Yii::t('list_subscribers', 'Ip Address'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return ListSubscriber the static model class
     */
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
    
    protected function beforeSave()
    {
        if (empty($this->subscriber_uid)) {
            $this->subscriber_uid = $this->generateUid();
        }
        
        return parent::beforeSave();
    }
    
    public function findByUid($subscriber_uid)
    {
        return $this->findByAttributes(array(
            'subscriber_uid' => $subscriber_uid,
        ));    
    }
    
    public function generateUid()
    {
        $unique = StringHelper::uniqid();
        $exists = $this->findByUid($unique);
        
        if (!empty($exists)) {
            return $this->generateUid();
        }
        
        return $unique;
    }
    
    public function getIsBlacklisted()
    {
        return EmailBlacklist::isBlacklisted($this->email);
    }
    
    public function addToBlacklist($reason)
    {
        if ($added = EmailBlacklist::addToBlacklist($this, $reason)) {
            $this->status = self::STATUS_BLACKLISTED;
            $this->save(false);
        }
        return $added;
    }
    
    public function getCanBeConfirmed()
    {
        return !in_array($this->status, array(self::STATUS_CONFIRMED, self::STATUS_BLACKLISTED));
    }
    
    public function getCanBeUnsubscribed()
    {
        return !in_array($this->status, array(self::STATUS_BLACKLISTED));
    }
    
    public function getCanBeDeleted()
    {
        return true;
    }
    
    public function getUid()
    {
        return $this->subscriber_uid;
    }
    
    public function getStatusesList()
    {
        return array(
            self::STATUS_CONFIRMED      => Yii::t('list_subscribers', self::STATUS_CONFIRMED),
            self::STATUS_UNCONFIRMED    => Yii::t('list_subscribers', self::STATUS_UNCONFIRMED),
            self::STATUS_UNSUBSCRIBED   => Yii::t('list_subscribers', self::STATUS_UNSUBSCRIBED),
        );
    }
    
    public function getFilterStatusesList()
    {
        return array_merge($this->getStatusesList(), array(
            self::STATUS_BLACKLISTED => Yii::t('list_subscribers', self::STATUS_BLACKLISTED),
        ));
    }
    
    public function getBulkActionsList()
    {
        return array(
            self::BULK_SUBSCRIBE    => Yii::t('list_subscribers', ucfirst(self::BULK_SUBSCRIBE)),
            self::BULK_UNSUBSCRIBE  => Yii::t('list_subscribers', ucfirst(self::BULK_UNSUBSCRIBE)),
            self::BULK_DELETE       => Yii::t('list_subscribers', ucfirst(self::BULK_DELETE)),
        );
    }
    
    public function copyToList($listId, $doTransaction = true)
    {
        
        $listId = (int)$listId;
        if (empty($listId) || $listId == $this->list_id) {
            return false;
        }
        
        static $targetLists      = array();
        static $cacheFieldModels = array();
        
        if (isset($targetLists[$listId]) || array_key_exists($listId, $targetLists)) {
            $targetList = $targetLists[$listId];
        } else {
            $targetList = $targetLists[$listId] = Lists::model()->findByPk($listId);
        }
        
        if (empty($targetList)) {
            return false;
        }
        
        $subscriber = self::model()->findByAttributes(array(
            'list_id' => $targetList->list_id, 
            'email'   => $this->email
        ));
        
        // already there
        if (!empty($subscriber)) {
            return $subscriber;
        }
        
        $subscriber = clone $this;
        $subscriber->isNewRecord    = true;
        $subscriber->subscriber_id  = null;
        $subscriber->list_id        = $targetList->list_id;
        $subscriber->date_added     = new CDbExpression('NOW()');
        $subscriber->last_updated   = new CDbExpression('NOW()');
        $subscriber->subscriber_uid = $this->generateUid();
        $subscriber->addRelatedRecord('list', $targetList, false);
        
        if ($doTransaction) {
            $transaction = Yii::app()->getDb()->beginTransaction();
        }

        try {
            
            if (!$subscriber->save()) {
                throw new Exception(CHtml::errorSummary($subscriber));
            }
            
            
            $cacheListsKey = $this->list_id . '|' . $targetList->list_id;
            if (!isset($cacheFieldModels[$cacheListsKey])) {
                // the custom fields for source list
                $sourceFields = ListField::model()->findAllByAttributes(array(
                    'list_id' => $this->list_id,
                ));
                
                // the custom fields for target list
                $targetFields = ListField::model()->findAllByAttributes(array(
                    'list_id' => $targetList->list_id,
                ));
                
                // get only the same fields
                $_fieldModels = array();
                foreach ($sourceFields as $srcIndex => $sourceField) {
                    foreach ($targetFields as $trgIndex => $targetField) {
                        if ($sourceField->tag == $targetField->tag && $sourceField->type_id == $targetField->type_id) {
                            $_fieldModels[] = array($sourceField, $targetField);
                            unset($sourceFields[$srcIndex], $targetFields[$trgIndex]);
                            break;
                        }
                    }
                }
                $cacheFieldModels[$cacheListsKey] = $_fieldModels;
                unset($sourceFields, $targetFields, $_fieldModels);
            }
            $fieldModels = $cacheFieldModels[$cacheListsKey];
            
            if (empty($fieldModels)) {
                throw new Exception('No field models found, something went wrong!');
            }
            
            foreach ($fieldModels as $index => $models) {
                list($source, $target) = $models;
                $sourceValues = ListFieldValue::model()->findAllByAttributes(array(
                    'subscriber_id' => $this->subscriber_id,
                    'field_id'      => $source->field_id,
                ));
                foreach ($sourceValues as $sourceValue) {
                    $sourceValue = clone $sourceValue;
                    $sourceValue->value_id      = null;
                    $sourceValue->field_id      = $target->field_id;
                    $sourceValue->subscriber_id = $subscriber->subscriber_id;
                    $sourceValue->isNewRecord   = true;
                    $sourceValue->date_added    = new CDbExpression('NOW()');
                    $sourceValue->last_updated  = new CDbExpression('NOW()');
                    if (!$sourceValue->save()) {
                        throw new Exception(CHtml::errorSummary($sourceValue));
                    }
                }
                unset($models, $source, $target, $sourceValues, $sourceValue);
            }
            unset($fieldModels);

            if ($doTransaction) {
                $transaction->commit();
            }
        } catch (Exception $e) {
            if ($doTransaction) {
                $transaction->rollBack();
            } elseif (!empty($subscriber->subscriber_id)) {
                $subscriber->delete();
            }
            $subscriber = false;
        }
        
        return $subscriber;
    }
}
