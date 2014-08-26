<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Lists
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
/**
 * This is the model class for table "list".
 *
 * The followings are the available columns in table 'list':
 * @property integer $list_id
 * @property integer $customer_id
 * @property string $unique_id
 * @property string $name
 * @property string $description
 * @property string $visibility
 * @property string $opt_in
 * @property string $opt_out
 * @property string $status
 * @property string $date_added
 * @property string $last_updated
 *
 * The followings are the available model relations:
 * @property Campaign[] $campaigns
 * @property Campaign[] $campaignsCount
 * @property CampaignOpenActionListField[] $campaignOpenActionListFields
 * @property CampaignOpenActionSubscriber[] $campaignOpenActionSubscribers
 * @property CampaignTemplateUrlActionListField[] $campaignTemplateUrlActionListFields
 * @property CampaignTemplateUrlActionSubscriber[] $campaignTemplateUrlActionSubscribers
 * @property Customer $customer
 * @property ListCompany $company
 * @property ListCustomerNotification $customerNotification
 * @property ListDefault $default
 * @property ListField[] $fields
 * @property ListField[] $fieldsCount
 * @property ListPageType[] $pageTypes
 * @property ListPageType[] $pageTypesCount
 * @property ListSegment[] $segments
 * @property ListSegment[] $segmentsCount
 * @property ListSubscriber[] $subscribers
 * @property ListSubscriber[] $subscribersCount
 * @property ListSubscriber[] $confirmedSubscribers
 * @property ListSubscriber[] $confirmedSubscribersCount
 */
class Lists extends ActiveRecord
{
    const VISIBILITY_PUBLIC = 'public';
    
    const VISIBILITY_PRIVATE = 'private';
    
    const OPT_IN_SINGLE = 'single';
    
    const OPT_IN_DOUBLE = 'double';
    
    const OPT_OUT_SINGLE = 'single';
    
    const OPT_OUT_DOUBLE = 'double';
    
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{list}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            array('name, description, opt_in, opt_out', 'required'),
            
            array('name', 'length', 'min' => 2, 'max' => 100),
            array('description', 'length', 'min' => 2, 'max' => 255),
            array('visibility', 'in', 'range' => array(self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE)),
            array('opt_in', 'in', 'range' => array_keys($this->getOptInArray())),
            array('opt_out', 'in', 'range' => array_keys($this->getOptOutArray())),
        );
        
        return CMap::mergeArray($rules, parent::rules());
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        $relations = array(
            'campaigns' => array(self::HAS_MANY, 'Campaign', 'list_id'),
            'campaignsCount' => array(self::STAT, 'Campaign', 'list_id'),
            'campaignOpenActionListFields' => array(self::HAS_MANY, 'CampaignOpenActionListField', 'list_id'),
            'campaignOpenActionSubscribers' => array(self::HAS_MANY, 'CampaignOpenActionSubscriber', 'list_id'),
            'campaignTemplateUrlActionListFields' => array(self::HAS_MANY, 'CampaignTemplateUrlActionListField', 'list_id'),
            'campaignTemplateUrlActionSubscribers' => array(self::HAS_MANY, 'CampaignTemplateUrlActionSubscriber', 'list_id'),
            'customer' => array(self::BELONGS_TO, 'Customer', 'customer_id'),
            'company' => array(self::HAS_ONE, 'ListCompany', 'list_id'),
            'customerNotification' => array(self::HAS_ONE, 'ListCustomerNotification', 'list_id'),
            'default' => array(self::HAS_ONE, 'ListDefault', 'list_id'),
            'fields' => array(self::HAS_MANY, 'ListField', 'list_id', 'order' => 'sort_order ASC'),
            'fieldsCount' => array(self::STAT, 'ListField', 'list_id'),
            'pageTypes' => array(self::MANY_MANY, 'ListPageType', '{{list_page}}(list_id, type_id)'),
            'pageTypesCount' => array(self::STAT, 'ListPageType', '{{list_page}}(list_id, type_id)'),
            'segments' => array(self::HAS_MANY, 'ListSegment', 'list_id'),
            'segmentsCount' => array(self::STAT, 'ListSegment', 'list_id'),
            'subscribers' => array(self::HAS_MANY, 'ListSubscriber', 'list_id'),
            'subscribersCount' => array(self::STAT, 'ListSubscriber', 'list_id'),
            
            'confirmedSubscribers' => array(self::HAS_MANY, 'ListSubscriber', 'list_id', 'condition' => 't.status = :s', 'params' => array(':s' => ListSubscriber::STATUS_CONFIRMED)),
            'confirmedSubscribersCount' => array(self::STAT, 'ListSubscriber', 'list_id', 'condition' => 't.status = :s', 'params' => array(':s' => ListSubscriber::STATUS_CONFIRMED)),
        );
        
        return CMap::mergeArray($relations, parent::relations());
    }
    
    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        $labels = array(
            'list_id'       => Yii::t('lists', 'List'),
            'customer_id'   => Yii::t('lists', 'Customer'),
            'unique_id'     => Yii::t('lists', 'Unique'),
            'name'          => Yii::t('lists', 'Name'),
            'description'   => Yii::t('lists', 'Description'),
            'visibility'    => Yii::t('lists', 'Visibility'),
            'opt_in'        => Yii::t('lists', 'Opt in'),
            'opt_out'       => Yii::t('lists', 'Opt out'),
            'subscribers_count' => Yii::t('lists', 'Subscribers count'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());
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
    public function search()
    {
        $criteria = new CDbCriteria;
        $criteria->compare('customer_id', (int)$this->customer_id);

        return new CActiveDataProvider(get_class($this), array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => $this->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
            'sort'  => array(
                'defaultOrder'  => array(
                    'list_id'   => CSort::SORT_DESC,
                ),
            ),
        ));
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return Lists the static model class
     */
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    protected function beforeSave()
    {
        if ($this->isNewRecord && empty($this->list_uid)) {
            $this->list_uid = $this->generateUid();
        }
        
        return parent::beforeSave();
    }
    
    public function findByUid($list_uid)
    {
        return $this->findByAttributes(array(
            'list_uid' => $list_uid,
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

    public function getUid()
    {
        return $this->list_uid;
    }
    
    public function getVisibilityOptions()
    {
        return array(
            ''                          => Yii::t('app', 'Choose'),
            self::VISIBILITY_PUBLIC     => Yii::t('app', 'Public'),
            self::VISIBILITY_PRIVATE    => Yii::t('app', 'Private'),
        );
    }
    
    public function getOptInArray()
    {
        return array(
            self::OPT_IN_DOUBLE => Yii::t('lists', 'Double opt-in'),
            self::OPT_IN_SINGLE => Yii::t('lists', 'Single opt-in'),
        );
    }
    
    public function getOptOutArray()
    {
        return array(
            self::OPT_OUT_DOUBLE => Yii::t('lists', 'Double opt-out'),
            self::OPT_OUT_SINGLE => Yii::t('lists', 'Single opt-out'),
        );
    }
    
    public function attributeHelpTexts()
    {
        $texts = array(
            'name'          => Yii::t('lists', 'Your mail list verbose name. It will be shown in various sections of the website, subscription forms, campaigns, etc.'),
            'description'   => Yii::t('lists', 'Please use an accurate list description, but keep it brief.'),
            'visibility'    => Yii::t('lists', 'Public lists are shown on the website landing page, providing a way of getting new subscribers easily.'),
            'opt_in'        => Yii::t('lists', 'Double opt-in will send a confirmation email while single opt-in will not.'),
            'opt_out'       => Yii::t('lists', 'Double opt-out will send a confirmation email while single opt-out will not.'),
        );
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }
    
    public function attributePlaceholders()
    {
        $placeholders = array(
            'name'            => Yii::t('lists', 'List name, i.e: Newsletter subscribers.'),
            'description'     => Yii::t('lists', 'List detailed description, something your subscribers will easily recognize.'),
        );
        return CMap::mergeArray($placeholders, parent::attributePlaceholders());
    }
    
    public function copy()
    {
        $copied = false;
        
        if ($this->isNewRecord) {
            return $copied;
        }
        
        $transaction = Yii::app()->db->beginTransaction();
        
        try {
            
            $list = clone $this;
            $list->isNewRecord  = true;
            $list->list_id      = null;
            $list->list_uid     = $this->generateUid();
            $list->date_added   = new CDbExpression('NOW()');
            $list->last_updated = new CDbExpression('NOW()');

            if (preg_match('/\#(\d+)$/', $list->name, $matches)) {
                $counter = (int)$matches[1];
                $counter++;
                $list->name = preg_replace('/\#(\d+)$/', '#' . $counter, $list->name);
            } else {
                $list->name .= ' #1';
            }

            if (!$list->save(false)) {
                throw new CException($list->shortErrors->getAllAsString());
            }

            $listDefault = !empty($this->default) ? clone $this->default : new ListDefault();
            $listDefault->isNewRecord = true;
            $listDefault->list_id = $list->list_id;
            $listDefault->save(false);
            
            $listCompany = !empty($this->company) ? clone $this->company : new ListCompany();
            $listCompany->isNewRecord = true;
            $listCompany->list_id = $list->list_id;
            $listCompany->save(false);
            
            $listCustomerNotification = !empty($this->customerNotification) ? clone $this->customerNotification : new ListCustomerNotification();
            $listCustomerNotification->isNewRecord = true;
            $listCustomerNotification->list_id = $list->list_id;
            $listCustomerNotification->save(false);

            $fields = !empty($this->fields) ? $this->fields : array();
            foreach ($fields as $field) {
                $fieldOptions = !empty($field->options) ? $field->options : array();
                $field = clone $field;
                $field->isNewRecord = true;
                $field->field_id = null;
                $field->list_id = $list->list_id;
                $field->date_added   = new CDbExpression('NOW()');
                $field->last_updated = new CDbExpression('NOW()');
                if (!$field->save(false)) {
                    continue;
                }
                foreach ($fieldOptions as $option) {
                    $option = clone $option;
                    $option->isNewRecord = true;
                    $option->option_id = null;
                    $option->field_id = $field->field_id;
                    $option->date_added   = new CDbExpression('NOW()');
                    $option->last_updated = new CDbExpression('NOW()');
                    $option->save(false);
                }
            }
            
            $pages = ListPage::model()->findAllByAttributes(array('list_id' => $this->list_id));
            foreach ($pages as $page) {
                $page = clone $page;
                $page->isNewRecord = true;
                $page->list_id = $list->list_id;
                $page->date_added   = new CDbExpression('NOW()');
                $page->last_updated = new CDbExpression('NOW()');
                $page->save(false);
            }
            
            $transaction->commit();
            $copied = $list;
        } catch (Exception $e) {
            $transaction->rollBack();   
        }

        return $copied;
    }
}
