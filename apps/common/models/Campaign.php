<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Campaign
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
/**
 * This is the model class for table "campaign".
 *
 * The followings are the available columns in table 'campaign':
 * @property integer $campaign_id
 * @property string $campaign_uid
 * @property integer $customer_id
 * @property integer $list_id
 * @property integer $segment_id
 * @property integer $group_id
 * @property string $type
 * @property string $name
 * @property string $from_name
 * @property string $from_email
 * @property string $to_name
 * @property string $reply_to
 * @property string $subject
 * @property string $send_at
 * @property string $status
 * @property string $date_added
 * @property string $last_updated
 *
 * The followings are the available model relations:
 * @property CampaignGroup $group
 * @property Customer $customer
 * @property List $list
 * @property ListSegment $segment
 * @property CampaignBounceLog[] $bounceLogs
 * @property CampaignDeliveryLog[] $deliveryLogs
 * @property CampaignOpenActionListField[] $openActionListFields
 * @property CampaignOpenActionSubscriber[] $openActionSubscribers
 * @property CampaignTemplateUrlActionListField[] $urlActionListFields
 * @property CampaignTemplateUrlActionSubscriber[] $urlActionSubscribers
 * @property CampaignTemporarySource[] $temporarySources
 * @property CampaignLink[] $links
 * @property CampaignOption $option
 * @property CampaignTemplate $template
 * @property CampaignTemplate[] $templates
 * @property CampaignAttachment[] $attachments
 * @property DeliveryServer[] $deliveryServers
 * @property CampaignTrackOpen[] $trackOpens
 * @property CampaignTrackUnsubscribe[] $trackUnsubscribes
 * @property CampaignUrl[] $urls
 */
class Campaign extends ActiveRecord
{
    const STATUS_DRAFT = 'draft';
    
    const STATUS_PENDING_SENDING = 'pending-sending';
    
    const STATUS_SENDING = 'sending';
    
    const STATUS_SENT = 'sent';
    
    const STATUS_PROCESSING = 'processing';
    
    const STATUS_PAUSED = 'paused';
    
    const TYPE_REGULAR = 'regular';
    
    const TYPE_AUTORESPONDER = 'autoresponder';

    protected $_displaySendAt;
    
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{campaign}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            array('name, list_id', 'required', 'on' => 'step-name, step-confirm'),
            array('from_name, from_email, subject, reply_to, to_name', 'required', 'on' => 'step-setup, step-confirm'),
            array('send_at', 'required', 'on' => 'step-confirm'),
            
            array('list_id, segment_id, group_id', 'numerical', 'integerOnly' => true),
            array('list_id', 'exist', 'className' => 'Lists'),
            array('segment_id', 'exist', 'className' => 'ListSegment'),
            array('group_id', 'exist', 'className' => 'CampaignGroup'),
            array('name, to_name, subject', 'length', 'max'=>255),
            array('from_name, from_email, reply_to', 'length', 'max'=>100),
            array('from_email, reply_to', 'email', 'allowEmpty' => true),
            array('type', 'in', 'range' => array_keys($this->getTypesList())),
            array('send_at', 'date', 'format' => 'yyyy-mm-dd hh:mm:ss', 'on' => 'step-confirm'),
            
            array('displaySendAt', 'safe'),
            
            // The following rule is used by search().
            array('group_id, name, type, status', 'safe', 'on'=>'search'),
        );
        
        return CMap::mergeArray($rules, parent::rules());
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        $relations = array(
            'group'                 => array(self::BELONGS_TO, 'CampaignGroup', 'group_id'),
            'list'                  => array(self::BELONGS_TO, 'Lists', 'list_id'),
            'segment'               => array(self::BELONGS_TO, 'ListSegment', 'segment_id'),
            'bounceLogs'            => array(self::HAS_MANY, 'CampaignBounceLog', 'campaign_id'),
            'deliveryLogs'          => array(self::HAS_MANY, 'CampaignDeliveryLog', 'campaign_id'),
            'openActionListFields'  => array(self::HAS_MANY, 'CampaignOpenActionListField', 'campaign_id'),
            'openActionSubscribers' => array(self::HAS_MANY, 'CampaignOpenActionSubscriber', 'campaign_id'),
            'urlActionListFields'   => array(self::HAS_MANY, 'CampaignTemplateUrlActionListField', 'campaign_id'),
            'urlActionSubscribers'  => array(self::HAS_MANY, 'CampaignTemplateUrlActionSubscriber', 'campaign_id'),
            'temporarySources'      => array(self::HAS_MANY, 'CampaignTemporarySource', 'campaign_id'),
            'customer'              => array(self::BELONGS_TO, 'Customer', 'customer_id'),
            'links'                 => array(self::HAS_MANY, 'CampaignLink', 'campaign_id'),
            'option'                => array(self::HAS_ONE, 'CampaignOption', 'campaign_id'),
            'template'              => array(self::HAS_ONE, 'CampaignTemplate', 'campaign_id'),
            'templates'             => array(self::HAS_MANY, 'CampaignTemplate', 'campaign_id'),
            'attachments'           => array(self::HAS_MANY, 'CampaignAttachment', 'campaign_id'),
            'deliveryServers'       => array(self::MANY_MANY, 'DeliveryServer', '{{campaign_to_delivery_server}}(campaign_id, server_id)'),
            'trackOpens'            => array(self::HAS_MANY, 'CampaignTrackOpen', 'campaign_id'),
            'trackUnsubscribes'     => array(self::HAS_MANY, 'CampaignTrackUnsubscribe', 'campaign_id'),
            'urls'                  => array(self::HAS_MANY, 'CampaignUrl', 'campaign_id'),
        );
        
        return CMap::mergeArray($relations, parent::relations());
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        $labels = array(
            'campaign_id'           => Yii::t('campaigns', 'Campaign'),
            'campaign_uid'          => Yii::t('campaigns', 'Campaign uid'),
            'customer_id'           => Yii::t('campaigns', 'Customer'),
            'list_id'               => Yii::t('campaigns', 'List'),
            'segment_id'            => Yii::t('campaigns', 'Segment'),
            'group_id'              => Yii::t('campaigns', 'Group'),
            'name'                  => Yii::t('campaigns', 'Campaign name'),
            'type'                  => Yii::t('campaigns', 'Type'),
            'from_name'             => Yii::t('campaigns', 'From name'),
            'from_email'            => Yii::t('campaigns', 'From email'),
            'to_name'               => Yii::t('campaigns', 'To name'),
            'reply_to'              => Yii::t('campaigns', 'Reply to'),
            'confirmed_reply_to'    => Yii::t('campaigns', 'Confirmed reply to'),
            'confirmation_code'     => Yii::t('campaigns', 'Confirmation code'),
            'subject'               => Yii::t('campaigns', 'Subject'),
            'send_at'               => Yii::t('campaigns', 'Send at'),
            
            'lastOpen'              => Yii::t('campaigns', 'Last open'),
        );
        
        if ($this->getIsAutoresponder()) {
            $labels['send_at'] = Yii::t('campaigns', 'Activate at');
        }
        
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
        $criteria=new CDbCriteria;
        $criteria->compare('customer_id', (int)$this->customer_id);
        $criteria->compare('list_id', $this->list_id);
        $criteria->compare('segment_id', $this->segment_id);
        $criteria->compare('group_id', $this->group_id);
        $criteria->compare('name', $this->name, true);
        $criteria->compare('type', $this->type);
        $criteria->compare('status', $this->status);
        
        return new CActiveDataProvider(get_class($this), array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => $this->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
            'sort'  => array(
                'defaultOrder'  => array(
                    'campaign_id'   => CSort::SORT_DESC,
                ),
            ),
        ));
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return Campaign the static model class
     */
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
    
    protected function beforeValidate()
    {
        if ($this->scenario == 'step-setup') {
            $tags = $this->getSubjectToNameAvailableTags();
            $hasErrors = false;
            $attributes = array('subject', 'to_name');
            
            foreach ($attributes as $attribute) {
                $content = CHtml::decode($this->$attribute);
                $missingTags = array();
                foreach ($tags as $tag) {
                    if (!isset($tag['tag']) || !isset($tag['required']) || !$tag['required']) {
                        continue;
                    }
                    if (!isset($tag['pattern']) && strpos($content, $tag['tag']) === false) {
                        $missingTags[] = $tag['tag'];
                    } elseif (isset($tag['pattern']) && !preg_match($tag['pattern'], $content)) {
                        $missingTags[] = $tag['tag'];
                    }
                } 
                if (!empty($missingTags)) {
                    $missingTags = array_unique($missingTags);
                    $this->addError($attribute, Yii::t('campaigns', 'The following tags are required but were not found in your content: {tags}', array(
                        '{tags}' => implode(', ', $missingTags),
                    )));
                    $hasErrors = true;
                }   
            }
            
            if ($hasErrors) {
                return false;
            }    
        }

        return parent::beforeValidate();
    }
    
    protected function beforeSave()
    {
        if (empty($this->campaign_uid)) {
            $this->campaign_uid = $this->generateUid();
        }

        return parent::beforeSave();
    }
    
    protected function beforeDelete()
    {
        // only drafts are allowed to be deleted
        if (!$this->getRemovable()) {
            return false;
        }
        
        return parent::beforeDelete();
    }
    
    protected function afterFind()
    {
        if ($this->send_at == '0000-00-00 00:00:00') {
            $this->send_at = null;
        }
        
        if (empty($this->send_at)) {
            $this->send_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        }
        
        parent::afterFind();
    }
    
    protected function afterConstruct()
    {
        if (empty($this->send_at)) {
            $this->send_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        }
        parent::afterConstruct();
    }
    
    protected function afterDelete()
    {
        // clean campaign files, if any.
        $storagePath = Yii::getPathOfAlias('root.frontend.assets.gallery');
        $campaignFiles = $storagePath.'/cmp'.$this->campaign_uid;
        if (file_exists($campaignFiles) && is_dir($campaignFiles)) {
            FileSystemHelper::deleteDirectoryContents($campaignFiles, true, 1);
        }
        
        // attachments
        $attachmentsPath = Yii::getPathOfAlias('root.frontend.assets.files.campaign-attachments.'.$this->campaign_uid);
        if (file_exists($attachmentsPath) && is_dir($attachmentsPath)) {
            FileSystemHelper::deleteDirectoryContents($attachmentsPath, true, 1);
        }
        
        parent::afterDelete();
    }
    
    public function findByUid($campaign_uid)
    {
        return $this->findByAttributes(array(
            'campaign_uid' => $campaign_uid,
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

    public function attributeHelpTexts()
    {
        $texts = array(
            'type'          => Yii::t('campaigns', 'The type of this campaign, either a regular one or autoresponder'),
            'name'          => Yii::t('campaigns', 'The campaign name, this is used internally so that you can differentiate between the campaigns. Will not be shown to subscribers.'),
            'list_id'       => Yii::t('campaigns', 'The list from where we will pick the subscribers. We will send to all the confirmed subscribers if no segment is specified.'),
            'segment_id'    => Yii::t('campaigns', 'Narrow the subscribers to a specific defined segment. If you have no segment so far, feel free to go ahead and create one to be used here.'),
            'send_at'       => Yii::t('campaigns', 'Uses your account timezone in "{format}" format. Set the date in past for immediate sending.', array('{format}' => $this->getDateTimeFormat() )),
            
            'from_name' => Yii::t('campaigns', 'This is the name of the "From" header used in campaigns, use a name that your subscribers will easily recognize, like your website name or company name.'),
            'from_email'=> Yii::t('campaigns', 'This is the email of the "From" header used in campaigns, use a name that your subscribers will easily recognize, containing your website name or company name.'),
            'subject'   => Yii::t('campaigns', 'Campaign subject. There are a few available tags for customization.'),
            'reply_to'  => Yii::t('campaigns', 'If a subscriber replies to your campaign, this is the email address where the reply will go.'),
            'to_name'   => Yii::t('campaigns', 'This is the "To" header shown in the campaign. There are a few available tags for customization.'),
        );
        
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }
    
    public function attributePlaceholders()
    {
        $placeholders = array(
            'name'          => Yii::t('campaigns', 'I.E: Weekly digest subscribers'),
            'list_id'       => null,
            'segment_id'    => null,
            'send_at'       => $this->getDateTimeFormat(),
            
            'from_name' => Yii::t('campaigns', 'My Super Company INC'),
            'from_email'=> Yii::t('campaigns', 'newsletter@my-super-company.com'),
            'subject'   => Yii::t('campaigns', 'Weekly newsletter'),
            'reply_to'  => Yii::t('campaigns', 'reply@my-super-company.com'),
            'to_name'   => Yii::t('campaigns', '[FNAME] [LNAME]'),
        );
        
        return CMap::mergeArray($placeholders, parent::attributePlaceholders());
    }
    
    public function pause()
    {
        if (!$this->getCanBePaused()) {
            return false;
        }
        $this->status = self::STATUS_PAUSED;
        return $this->save(false);
    }
    
    public function unpause()
    {
        if (!$this->getIsPaused()) {
            return false;
        }
        $this->status = self::STATUS_SENDING;
        return $this->save(false);
    }
    
    public function pauseUnpause()
    {
        if ($this->getIsPaused()) {
            $this->unpause();
        } elseif ($this->getCanBePaused()) {
            $this->pause();
        }
        return $this;
    }
    
    public function copy()
    {
        $copied = false;
        
        if ($this->isNewRecord) {
            return $copied;
        }
        
        $transaction = Yii::app()->db->beginTransaction();
        
        try {
            
            $campaign = clone $this;
            $campaign->isNewRecord  = true;
            $campaign->campaign_id  = null;
            $campaign->campaign_uid = $campaign->generateUid();
            $campaign->send_at      = null;
            $campaign->date_added   = new CDbExpression('NOW()');
            $campaign->last_updated = new CDbExpression('NOW()');
            $campaign->status       = self::STATUS_DRAFT;

            if (preg_match('/\#(\d+)$/', $campaign->name, $matches)) {
                $counter = (int)$matches[1];
                $counter++;
                $campaign->name = preg_replace('/\#(\d+)$/', '#' . $counter, $campaign->name);
            } else {
                $campaign->name .= ' #1';
            }

            if (!$campaign->save(false)) {
                throw new CException($campaign->shortErrors->getAllAsString());
            }
            
            // campaign options
            $option = !empty($this->option) ? clone $this->option : new CampaignOption();
            $option->isNewRecord = true;
            $option->campaign_id = $campaign->campaign_id;
            
            if (!$option->save()) {
                throw new Exception($option->shortErrors->getAllAsString());
            }
            
            // actions on open
            $openActions = CampaignOpenActionSubscriber::model()->findAllByAttributes(array(
                'campaign_id'   => $this->campaign_id,
            ));
            foreach ($openActions as $action) {
                $action = clone $action;
                $action->isNewRecord  = true;
                $action->action_id    = null;
                $action->campaign_id  = $campaign->campaign_id;
                $action->date_added   = new CDbExpression('NOW()');
                $action->last_updated = new CDbExpression('NOW()');
                $action->save(false);
            }
            
            // actions on open against custom fields
            $openListFieldActions = CampaignOpenActionListField::model()->findAllByAttributes(array(
                'campaign_id'   => $this->campaign_id,
            ));
            foreach ($openListFieldActions as $action) {
                $action = clone $action;
                $action->isNewRecord  = true;
                $action->action_id    = null;
                $action->campaign_id  = $campaign->campaign_id;
                $action->date_added   = new CDbExpression('NOW()');
                $action->last_updated = new CDbExpression('NOW()');
                $action->save(false);
            }

            // template related
            $templateClickActions = array();
            $templateClickActionsListFields = array();
            if (!empty($this->template)) {
                $templateClickActions = CampaignTemplateUrlActionSubscriber::model()->findAllByAttributes(array(
                    'campaign_id' => $this->campaign_id,
                    'template_id' => $this->template->template_id,
                ));
                $templateClickActionsListFields = CampaignTemplateUrlActionListField::model()->findAllByAttributes(array(
                    'campaign_id' => $this->campaign_id,
                    'template_id' => $this->template->template_id,
                ));
                $template = clone $this->template;
            } else {
                $template = new CampaignTemplate();
            }
            
            // campaign template
            $template->isNewRecord = true;
            $template->template_id = null;
            $template->campaign_id = $campaign->campaign_id;

            $storagePath = Yii::getPathOfAlias('root.frontend.assets.gallery');
            $oldCampaignFilesPath = $storagePath.'/cmp'.$this->campaign_uid;
            $newCampaignFilesPath = $storagePath.'/cmp'.$campaign->campaign_uid;
            $canSaveTemplate = true;
            
            if (file_exists($oldCampaignFilesPath) && is_dir($oldCampaignFilesPath)) {
                if (!@mkdir($newCampaignFilesPath, 0777)) {
                    $canSaveTemplate = false;
                }
                
                if ($canSaveTemplate && !FileSystemHelper::copyOnlyDirectoryContents($oldCampaignFilesPath, $newCampaignFilesPath)) {
                    $canSaveTemplate = false;
                }
            }
            
            if (!$canSaveTemplate) {
                throw new Exception(Yii::t('campaigns', 'Campaign template could not be saved while copying campaign!'));
            }

            $template->content = str_replace('cmp'.$this->campaign_uid, 'cmp'.$campaign->campaign_uid, $template->content);

            if (!$template->save(false)) {
                if (file_exists($newCampaignFilesPath) && is_dir($newCampaignFilesPath)) {
                    FileSystemHelper::deleteDirectoryContents($newCampaignFilesPath, true, 1);
                }
                throw new Exception($template->shortErrors->getAllAsString());
            }
            
            // template click actions
            if (!empty($templateClickActions) || !empty($templateClickActionsListFields)) {
                $templateUrls = $template->getContentUrls();
                foreach ($templateClickActions as $clickAction) {
                    if (!in_array($clickAction->url, $templateUrls)) {
                        continue;
                    }
                    $clickAction = clone $clickAction;
                    $clickAction->isNewRecord  = true;
                    $clickAction->url_id       = null;
                    $clickAction->campaign_id  = $campaign->campaign_id;
                    $clickAction->template_id  = $template->template_id;
                    $clickAction->date_added   = new CDbExpression('NOW()');
                    $clickAction->last_updated = new CDbExpression('NOW()');
                    $clickAction->save(false);
                }
                foreach ($templateClickActionsListFields as $clickAction) {
                    if (!in_array($clickAction->url, $templateUrls)) {
                        continue;
                    }
                    $clickAction = clone $clickAction;
                    $clickAction->isNewRecord  = true;
                    $clickAction->url_id       = null;
                    $clickAction->campaign_id  = $campaign->campaign_id;
                    $clickAction->template_id  = $template->template_id;
                    $clickAction->date_added   = new CDbExpression('NOW()');
                    $clickAction->last_updated = new CDbExpression('NOW()');
                    $clickAction->save(false);
                }
            }
            
            // delivery servers - start
            if (!empty($this->deliveryServers)) {
                foreach ($this->deliveryServers as $server) {
                    $campaignToServer = new CampaignToDeliveryServer();
                    $campaignToServer->server_id    = $server->server_id;
                    $campaignToServer->campaign_id  = $campaign->campaign_id;
                    $campaignToServer->save();
                }
            }
            // delivery servers - end
            
            // attachments - start
            if (!empty($this->attachments)) {
                $copiedAttachments = false;
                $attachmentsPath = Yii::getPathOfAlias('root.frontend.assets.files.campaign-attachments');
                $oldAttachments  = $attachmentsPath . '/' . $this->campaign_uid;
                $newAttachments  = $attachmentsPath . '/' . $campaign->campaign_uid;
                if (file_exists($oldAttachments) && is_dir($oldAttachments) && @mkdir($newAttachments, 0777, true)) {
                    $copiedAttachments = FileSystemHelper::copyOnlyDirectoryContents($oldAttachments, $newAttachments);
                }
                if ($copiedAttachments) {
                    foreach ($this->attachments as $attachment) {
                        $attachment = clone $attachment;
                        $attachment->isNewRecord    = true;
                        $attachment->attachment_id  = null;
                        $attachment->campaign_id    = $campaign->campaign_id;
                        $attachment->date_added     = null;
                        $attachment->last_updated   = null;
                        $attachment->save(false);
                    }    
                }
            }
            // attachments - end
            
            $transaction->commit();
            $copied = $campaign;
        } catch (Exception $e) {
            $transaction->rollBack();   
        }

        return $copied;
    }

    public function getListsDropDownArray()
    {
        static $_options = array();
        if (!empty($_options)) {
            return $_options;
        }
        
        $criteria = new CDbCriteria();
        $criteria->compare('customer_id', (int)$this->customer_id);
        $criteria->order = 'list_id DESC';
        $lists = Lists::model()->findAll($criteria);
        
        foreach ($lists as $list) {
            $_options[$list->list_id] = $list->name . ' ('. Yii::t('campaigns', '{subscribersCount} subscribers', array(
                '{subscribersCount}' => Yii::app()->format->formatNumber($list->confirmedSubscribersCount)
            )).')';
        }
        
        return $_options;
    }
    
    public function getSegmentsDropDownArray()
    {
        static $_options = array();
        if (!empty($_options)) {
            return $_options;
        }
        
        if (empty($this->list_id)) {
            return $_options = array();
        }
        
        $segments = ListSegment::model()->findAllByListId($this->list_id);
        foreach ($segments as $segment) {
            $_options[$segment->segment_id] = $segment->name . ' ('. Yii::t('campaigns', '{subscribersCount} subscribers', array(
                '{subscribersCount}' => Yii::app()->format->formatNumber($segment->countSubscribers())
            )).')'; 
        }
        
        return $_options;
    }
    
    public function getGroupsDropDownArray()
    {
        static $_options = array();
        if (!empty($_options)) {
            return $_options;
        }
        
        $criteria = new CDbCriteria();
        $criteria->compare('customer_id', (int)$this->customer_id);
        $criteria->order = 'group_id DESC';
        $models = CampaignGroup::model()->findAll($criteria);
        
        foreach ($models as $model) {
            $_options[$model->group_id] = $model->name . ' ('. Yii::t('campaigns', '{campaignsCount} campaigns', array(
                '{campaignsCount}' => Yii::app()->format->formatNumber($model->campaignsCount)
            )).')';
        }
        
        return $_options;
    }
    
    public function getRemovable()
    {
        return in_array($this->status, array(self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_PENDING_SENDING, self::STATUS_PAUSED));
    }
    
    /**
     * Paused status introduced in 1.3.4.2
     */
    public function getEditable()
    {
        return in_array($this->status, array(self::STATUS_DRAFT, self::STATUS_PENDING_SENDING, self::STATUS_PAUSED));
    }
    
    public function getAccessOverview()
    {
        return !in_array($this->status, array(self::STATUS_DRAFT, self::STATUS_PENDING_SENDING));
    }
    
    public function getCanBePaused()
    {
        return in_array($this->status, array(self::STATUS_SENDING, self::STATUS_PROCESSING));
    }
    
    public function getIsPaused()
    {
        return in_array($this->status, array(self::STATUS_PAUSED));
    }
    
    public function getCanBeResumed()
    {
        return in_array($this->status, array(self::STATUS_PROCESSING));
    }
    
    public function getIsProcessing()
    {
        return $this->status == self::STATUS_PROCESSING;
    }
    
    public function getIsSending()
    {
        return $this->status == self::STATUS_SENDING;
    }
    
    public function getIsPendingSending()
    {
        return $this->status == self::STATUS_PENDING_SENDING;
    }
    
    public function getSendAt()
    {
        return $this->dateTimeFormatter->formatLocalizedDateTime($this->send_at);
    }
    
    public function getLastOpen()
    {
        if ($this->isNewRecord) {
            return;
        }
        
        $criteria = new CDbCriteria();
        $criteria->select = 'date_added';
        $criteria->compare('campaign_id', $this->campaign_id);
        $criteria->order = 'id DESC';
        $criteria->limit = 1;
        
        $lastOpen = CampaignTrackOpen::model()->find($criteria);
        if (empty($lastOpen)) {
            return;
        }
        
        return $lastOpen->dateAdded;
    }
    
    public function getUid()
    {
        return $this->campaign_uid;
    }
    
    public function getSubjectToNameAvailableTags()
    {
        $tags = array(
            array('tag' => '[LIST_NAME]', 'required' => false),
        );
        
        if (!empty($this->list)) {
            $fields = $this->list->fields;
            foreach ($fields as $field) {
                $tags[] = array('tag' => '['.$field->tag.']', 'required' => false);
            }
        }
        
        return $tags;
    }
    
    public function setDisplaySendAt($dateTime)
    {
        $this->_displaySendAt = $dateTime;
        return $this;
    }
    
    public function getDisplaySendAt()
    {
        if ($this->_displaySendAt !== null) {
            return $this->_displaySendAt;
        }
        
        $dateTime = new DateTime(date('Y-m-d H:i:s', strtotime($this->send_at))); 

        if (!empty($this->list_id) && !empty($this->list) && !empty($this->list->customer) && !empty($this->list->customer->timezone)) {
            $dateTime->setTimezone(new DateTimeZone($this->list->customer->timezone));
        }

        $timestamp = CDateTimeParser::parse($dateTime->format('Y-m-d H:i:s'), 'yyyy-MM-dd HH:mm:ss');
        return $this->_displaySendAt = Yii::app()->dateFormatter->formatDateTime($timestamp, 'short', 'short');
    }
    
    public function getDateTimeFormat()
    {
        $locale = Yii::app()->locale;
        $searchReplace = array(
            '{1}' => $locale->getDateFormat('short'),
            '{0}' => $locale->getTimeFormat('short'),
        );
        
        return str_replace(array_keys($searchReplace), array_values($searchReplace), $locale->getDateTimeFormat());
    }
    
    public function getStatusesList()
    {
        return array(
            self::STATUS_DRAFT              => ucfirst(Yii::t('campaigns', self::STATUS_DRAFT)),
            self::STATUS_PENDING_SENDING    => ucfirst(Yii::t('campaigns', self::STATUS_PENDING_SENDING)),
            self::STATUS_SENDING            => ucfirst(Yii::t('campaigns', self::STATUS_SENDING)),
            self::STATUS_SENT               => ucfirst(Yii::t('campaigns', self::STATUS_SENT)),
            self::STATUS_PROCESSING         => ucfirst(Yii::t('campaigns', self::STATUS_PROCESSING)),
            self::STATUS_PAUSED             => ucfirst(Yii::t('campaigns', self::STATUS_PAUSED)),
        );
    }
    
    public function getStatusWithStats()
    {
        static $_status = array();
        if (!$this->isNewRecord && isset($_status[$this->campaign_id])) {
            return $_status[$this->campaign_id];
        }
        
        if ($this->isNewRecord || in_array($this->status, array(self::STATUS_DRAFT))) {
            return $_status[$this->campaign_id] = $this->getStatusName();
        }    
        
        $count = 0;
        $sent  = CampaignDeliveryLog::model()->countByAttributes(array(
            'campaign_id' => (int)$this->campaign_id,
        ));
            
        if (!$this->getIsAutoresponder() || $this->option->autoresponder_event == CampaignOption::AUTORESPONDER_EVENT_AFTER_SUBSCRIBE) {
            if (!empty($this->segment_id)) {
                $count = $this->segment->countSubscribers();
            } else {
                $count = $this->list->confirmedSubscribersCount;
            }    
        } elseif ($this->getIsAutoresponder() && $this->option->autoresponder_event == CampaignOption::AUTORESPONDER_EVENT_AFTER_CAMPAIGN_OPEN && ! empty($this->option->autoresponder_open_campaign_id)) {
            $count = $this->option->autoresponderOpenCampaign->getUniqueOpensCount();
        }

        $formatter = Yii::app()->format;
        return $_status[$this->campaign_id] = sprintf('%s (%s/%s)', $this->getStatusName(), $formatter->formatNumber($sent), $formatter->formatNumber($count));
    }

    public function getTypesList()
    {
        return array(
            self::TYPE_REGULAR          => ucfirst(Yii::t('campaigns', self::TYPE_REGULAR)),
            self::TYPE_AUTORESPONDER    => ucfirst(Yii::t('campaigns', self::TYPE_AUTORESPONDER))
        );
    }
    
    public function getTypeName($type = null) 
    {
        if (empty($type)) {
            $type = $this->type;
        }
        $types = $this->getTypesList();
        return isset($types[$type]) ? $types[$type] : $type;
    }
    
    public function getTypeNameDetails($type = null, $lineBreak = '<br />')
    {
        $type = $this->getTypeName($type);
        if (!$this->isAutoresponder) {
            return $type;
        }
        if (empty($this->option)) {
            return $type;
        }
        
        $timeUnit = $this->option->autoresponder_time_unit;
        if ($this->option->autoresponder_time_value > 1) {
            $timeUnit .= 's';
        }
        $timeUnit = Yii::t('app', $timeUnit);
        return sprintf('%s%s(%d %s/%s)', $type, $lineBreak, $this->option->autoresponder_time_value, $timeUnit, $this->option->getAutoresponderEventName());
    }
    
    public function getIsAutoresponder()
    {
        return $this->type == self::TYPE_AUTORESPONDER;
    }
    
    public function getIsRegular()
    {
        return $this->type == self::TYPE_REGULAR;
    }
    
    public function getUniqueOpensCount($format = false)
    {
        $criteria = new CDbCriteria();
        $criteria->compare('campaign_id', (int)$this->campaign_id);
        $criteria->group = 'subscriber_id';
        $count = CampaignTrackOpen::model()->count($criteria);
        if ($format) {
            $count = Yii::app()->format->formatNumber($count);
        }
        return $count;
    }
    
    public function getListSegmentName()
    {
        $names  = array(); 
        if (isset($names[$this->campaign_id])) {
            return $names[$this->campaign_id];
        }
        
        $name   = array();
        $count  = Yii::app()->format->formatNumber(!empty($this->segment_id) ? $this->segment->countSubscribers() : $this->list->confirmedSubscribersCount);
        $name[] = (empty($this->segment_id) ? $this->list->name : $this->list->name . '/' . $this->segment->name) . '('.Yii::t('campaigns', '{n} subs', $count).')';
        
        if (!empty($this->temporarySources)) {
            foreach ($this->temporarySources as $source) {
                $count  = Yii::app()->format->formatNumber(!empty($source->segment_id) ? $source->segment->countSubscribers() : $source->list->confirmedSubscribersCount);
                $name[] = (empty($source->segment_id) ? $source->list->name : $source->list->name . '/' . $source->segment->name) . '('.Yii::t('campaigns', '{n} subs', $count).')';
            }
        }
        return $names[$this->campaign_id] = implode(', ', $name);
    }
}
