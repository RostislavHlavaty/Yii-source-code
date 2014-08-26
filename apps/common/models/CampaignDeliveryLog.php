<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CampaignDeliveryLog
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
/**
 * This is the model class for table "campaign_delivery_log".
 *
 * The followings are the available columns in table 'campaign_delivery_log':
 * @property string $log_id
 * @property integer $campaign_id
 * @property integer $subscriber_id
 * @property string $message
 * @property string $processed
 * @property string $status
 * @property string $date_added
 *
 * The followings are the available model relations:
 * @property ListSubscriber $subscriber
 * @property Campaign $campaign
 */
class CampaignDeliveryLog extends ActiveRecord
{
    const STATUS_SUCCESS = 'success';
    
    const STATUS_ERROR = 'error';
    
    const STATUS_FATAL_ERROR = 'fatal-error';
    
    const STATUS_TEMPORARY_ERROR = 'temporary-error';

    const STATUS_BLACKLISTED = 'blacklisted';
    
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{campaign_delivery_log}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            array('status', 'safe', 'on' => 'customer-search'),
        );

        return CMap::mergeArray($rules, parent::rules());
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        $relations = array(
            'subscriber' => array(self::BELONGS_TO, 'ListSubscriber', 'subscriber_id'),
            'campaign' => array(self::BELONGS_TO, 'Campaign', 'campaign_id'),
        );
        
        return CMap::mergeArray($relations, parent::relations());
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        $labels = array(
            'log_id'        => Yii::t('campaigns', 'Log'),
            'campaign_id'   => Yii::t('campaigns', 'Campaign'),
            'subscriber_id' => Yii::t('campaigns', 'Subscriber'),
            'message'       => Yii::t('campaigns', 'Message'),
            'processed'     => Yii::t('campaigns', 'Processed'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());
    }
    
    protected function beforeSave()
    {
        if ($this->status == self::STATUS_TEMPORARY_ERROR) {
            $this->retries++;
            if ($this->retries >= $this->max_retries) {
                $this->status = self::STATUS_ERROR;
            }
        }
        return parent::beforeSave();
    }
    
    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * Typical usecase:
     * - Initialize the model fields with values from filter form.
     * - Execute this method to get CActiveDataProvider instance which will filter
     * models according to data in model fields.
     * - Pass data provider to CGridView, CListView or any similar widget.
     *
     * @return CActiveDataProvider the data provider that can return the models
     * based on the search/filter conditions.
     */
    public function customerSearch()
    {
        $criteria=new CDbCriteria;
        $criteria->compare('campaign_id', (int)$this->campaign_id);
        $criteria->compare('status', $this->status);
        
        return new CActiveDataProvider(get_class($this), array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => $this->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
            'sort'  => array(
                'defaultOrder'  => array(
                    'log_id'    => CSort::SORT_DESC,
                ),
            ),
        ));
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return CampaignDeliveryLog the static model class
     */
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
    
    public function getStatusesArray()
    {
        return array(
            self::STATUS_SUCCESS        => Yii::t('campaigns', self::STATUS_SUCCESS),
            self::STATUS_ERROR          => Yii::t('campaigns', self::STATUS_ERROR),
            self::STATUS_TEMPORARY_ERROR=> Yii::t('campaigns', self::STATUS_TEMPORARY_ERROR),
            self::STATUS_FATAL_ERROR    => Yii::t('campaigns', self::STATUS_FATAL_ERROR),
            self::STATUS_BLACKLISTED    => Yii::t('campaigns', self::STATUS_BLACKLISTED),
        );
    }
}
