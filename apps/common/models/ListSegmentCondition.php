<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ListSegmentCondition
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
/**
 * This is the model class for table "list_segment_condition".
 *
 * The followings are the available columns in table 'list_segment_condition':
 * @property integer $condition_id
 * @property integer $segment_id
 * @property integer $operator_id
 * @property integer $field_id
 * @property string $value
 * @property string $date_added
 * @property string $last_updated
 *
 * The followings are the available model relations:
 * @property ListSegmentOperator $operator
 * @property ListSegment $segment
 * @property ListField $field
 */
class ListSegmentCondition extends ActiveRecord
{
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{list_segment_condition}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            array('field_id, operator_id, value', 'required'),
            array('field_id, operator_id', 'numerical', 'integerOnly' => true),
            array('value', 'length', 'max'=>255),
        );
        
        return CMap::mergeArray($rules, parent::rules());
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        $relations = array(
            'operator'  => array(self::BELONGS_TO, 'ListSegmentOperator', 'operator_id'),
            'segment'   => array(self::BELONGS_TO, 'ListSegment', 'segment_id'),
            'field'     => array(self::BELONGS_TO, 'ListField', 'field_id'),
        );
        
        return CMap::mergeArray($relations, parent::relations());
    }
    
    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        $labels = array(
            'condition_id'  => Yii::t('list_segments', 'Condition'),
            'segment_id'    => Yii::t('list_segments', 'Segment'),
            'operator_id'   => Yii::t('list_segments', 'Operator'),
            'field_id'      => Yii::t('list_segments', 'Field'),
            'value'         => Yii::t('list_segments', 'Value'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return ListSegmentCondition the static model class
     */
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
    
    public function getOperatorsDropDownArray()
    {
        static $_options = array();
        if (!empty($_options)) {
            return $_options;
        }
        
        $operators = ListSegmentOperator::model()->findAll();
        foreach ($operators as $operator) {
            $_options[$operator->operator_id] = Yii::t('list_segments', $operator->name);
        }
        
        return $_options;
    }
}
