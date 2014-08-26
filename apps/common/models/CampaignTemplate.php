<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CampaignTemplate
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
/**
 * This is the model class for table "campaign_template".
 *
 * The followings are the available columns in table 'campaign_template':
 * @property integer $template_id
 * @property integer $campaign_id
 * @property integer $customer_template_id
 * @property string $content
 * @property string $inline_css
 * @property string $minify
 * @property string $plain_text
 * @property string $only_plain_text
 * @property string $auto_plain_text
 *
 * The followings are the available model relations:
 * @property Campaign $campaign
 * @property CustomerEmailTemplate $customerTemplate
 * @property CampaignTemplateUrlActionListField[] $urlActionListFields
 * @property CampaignTemplateUrlActionSubscriber[] $urlActionSubscribers
 */
class CampaignTemplate extends ActiveRecord
{
    // enable importing from url
    public $from_url;
    
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{campaign_template}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            array('content, inline_css, minify, auto_plain_text, only_plain_text', 'required'),
            array('content', 'customer.components.validators.CampaignTemplateValidator'),
            array('inline_css, only_plain_text, auto_plain_text, minify', 'in', 'range' => array_keys($this->getYesNoOptions())),
            array('plain_text, from_url', 'safe'),
        );
        
        return CMap::mergeArray($rules, parent::rules());
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        $relations = array(
            'campaign'              => array(self::BELONGS_TO, 'Campaign', 'campaign_id'),
            'customerTemplate'      => array(self::BELONGS_TO, 'CustomerEmailTemplate', 'customer_template_id'),
            'urlActionListFields'   => array(self::HAS_MANY, 'CampaignTemplateUrlActionListField', 'template_id'),
            'urlActionSubscribers'  => array(self::HAS_MANY, 'CampaignTemplateUrlActionSubscriber', 'template_id'),
        );
        
        return CMap::mergeArray($relations, parent::relations());
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        $labels = array(
            'campaign_id'       => Yii::t('campaigns', 'Campaign'),
            'content'           => Yii::t('campaigns', 'Content'),
            'inline_css'        => Yii::t('campaigns', 'Inline css'),
            'minify'            => Yii::t('campaigns', 'Minify'),
            'plain_text'        => Yii::t('campaigns', 'Plain text'),
            'only_plain_text'   => Yii::t('campaigns', 'Only plain text'),
            'auto_plain_text'   => Yii::t('campaigns', 'Auto plain text'),
            'from_url'          => Yii::t('campaigns', 'From url'),
        );
        
        return CMap::mergeArray($labels, parent::attributeLabels());
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return CampaignTemplate the static model class
     */
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
    
    public function getInlineCssArray()
    {
        return $this->getYesNoOptions();
    }
    
    public function getAutoPlainTextArray()
    {
        return $this->getYesNoOptions();
    }
    
    public function attributePlaceholders()
    {
        $placeholders = array(
            'content'           => '',
            'inline_css'        => '',
            'minify'            => '',
            'plain_text'        => '',
            'only_plain_text'   => '',
            'auto_plain_text'   => '',
            'from_url'          => '',
        );    
        
        return CMap::mergeArray($placeholders, parent::attributePlaceholders());
    }
    
    public function attributeHelpTexts()
    {
        $texts = array(
            'content'           => '',
            'inline_css'        => Yii::t('campaigns', 'Whether the parser should extract the css from the head of the document and inline it for each matching attribute found in the document body.'),
            'minify'            => Yii::t('campaigns', 'Whether the parser should minify the template to reduce size.'),
            'plain_text'        => Yii::t('campaigns', 'This is the plain text version of the html template. If left empty and autogenerate option is set to "yes" then this will be created based on your html template.'),
            'only_plain_text'   => Yii::t('campaigns', 'Whether the template contains only plain text and should be treated like so by all parsers.'),
            'auto_plain_text'   => Yii::t('campaigns', 'Whether the plain text version of the html template should be auto generated.'),
            'from_url'          => Yii::t('campaigns', 'Enter url to fetch as a template'),
        );
        
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }
    
    // see also CampaignHelper class
    public function getAvailableTags()
    {
        $tags = array(
            array('tag' => '[UNSUBSCRIBE_URL]', 'required' => true),
            array('tag' => '[COMPANY_FULL_ADDRESS]', 'required' => true),
            array('tag' => '[UPDATE_PROFILE_URL]', 'required' => false),
            array('tag' => '[WEB_VERSION_URL]', 'required' => false),
            array('tag' => '[CAMPAIGN_URL]', 'required' => false),
            
            array('tag' => '[LIST_NAME]', 'required' => false),
            array('tag' => '[LIST_SUBJECT]', 'required' => false),
            array('tag' => '[LIST_DESCRIPTION]', 'required' => false),
            array('tag' => '[LIST_FROM_NAME]', 'required' => false),
            array('tag' => '[LIST_FROM_EMAIL]', 'required' => false),
            
            array('tag' => '[CURRENT_YEAR]', 'required' => false),
            array('tag' => '[CURRENT_MONTH]', 'required' => false),
            array('tag' => '[CURRENT_DAY]', 'required' => false),
            array('tag' => '[CURRENT_DATE]', 'required' => false),

            array('tag' => '[COMPANY_NAME]', 'required' => false),
            array('tag' => '[COMPANY_ADDRESS_1]', 'required' => false),
            array('tag' => '[COMPANY_ADDRESS_2]', 'required' => false),
            array('tag' => '[COMPANY_CITY]', 'required' => false),
            array('tag' => '[COMPANY_ZONE]', 'required' => false),
            array('tag' => '[COMPANY_ZIP]', 'required' => false),
            array('tag' => '[COMPANY_COUNTRY]', 'required' => false),
            array('tag' => '[COMPANY_PHONE]', 'required' => false),
            
            array('tag' => '[CAMPAIGN_SUBJECT]', 'required' => false),
            array('tag' => '[CAMPAIGN_TO_NAME]', 'required' => false),
            array('tag' => '[CAMPAIGN_FROM_NAME]', 'required' => false),
            array('tag' => '[CAMPAIGN_FROM_EMAIL]', 'required' => false),
            array('tag' => '[CAMPAIGN_REPLY_TO]', 'required' => false),
            array('tag' => '[CAMPAIGN_UID]', 'required' => false),
            array('tag' => '[SUBSCRIBER_UID]', 'required' => false),
        );

        if (!empty($this->campaign) && !empty($this->campaign->list)) {
            $fields = $this->campaign->list->fields;
            foreach ($fields as $field) {
                $tags[] = array('tag' => '['.$field->tag.']', 'required' => false);
            }
        }
        
        $tags = (array)Yii::app()->hooks->applyFilters('campaign_template_available_tags_list', $tags);
        
        $optionTags = (array)Yii::app()->options->get('system.campaign.template_tags.template_tags', array());
        foreach ($optionTags as $optionTagInfo) {
            if (!isset($optionTagInfo['tag'], $optionTagInfo['required'])) {
                continue;
            }
            foreach ($tags as $index => $tag) {
                if ($tag['tag'] == $optionTagInfo['tag']) {
                    $tags[$index]['required'] = (bool)$optionTagInfo['required'];
                    break;
                }   
            }
        }
        
        return $tags;
    }
    
    public function getContentUrls()
    {
        return CampaignHelper::extractTemplateUrls($this->content);
    }
    
    public function getIsOnlyPlainText()
    {
        return $this->only_plain_text == self::TEXT_YES;
    }
}
