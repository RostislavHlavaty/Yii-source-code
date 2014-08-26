<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CampaignHelper
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class CampaignHelper
{    
    private static $_trackingUrls = array();
    
    /**
     * CampaignHelper::parseContent()
     * 
     * This should be always connected with the CampaignTemplate model class::getAvailableTags().
     * Will parse the content tags and transform them
     * 
     * It is used in: 
     * console/components/behaviors/CampaignSenderBehavior.php
     * frontend/controllers/CampaignsController.php
     * 
     * @param string $content
     * @param Campaign $campaign
     * @param ListSubscriber $subscriber
     * @param bool $appendBeacon
     * @return array
     */
    public static function parseContent($content, Campaign $campaign, ListSubscriber $subscriber, $appendBeacon = false)
    {
        $content    = StringHelper::decodeSurroundingTags($content);
        $list       = $campaign->list;
        $options    = Yii::app()->options;

        // parse template content;
        static $tagFilter;
        if ($tagFilter === null) {
            $tagFilter = new EmailTemplateTagFilter();
        }
        $searchReplace  = array();

        // custom fields tags first so that we can override them with ours if conflict.
        if (!empty($subscriber->subscriber_id)) {
            
            static $fields = array();
            if (!isset($fields[$list->list_id])) {
                $criteria = new CDbCriteria();
                $criteria->select = 't.field_id, t.tag';
                $criteria->compare('t.list_id', $list->list_id);
                $fields[$list->list_id] = ListField::model()->findAll($criteria);
            }
            
            foreach ($fields[$list->list_id] as $field) {
                $tag = '['.$field->tag.']';
                if (strpos($content, $tag) === false && strpos($campaign->subject, $tag) === false && strpos($campaign->to_name, $tag) === false) {
                    continue;
                }
                /**
                 * Using AR here proven to be a real bottleneck
                 * Instead, switching to Query Builder seems to be faster.
                 * This is a ListFieldValue model replacement!
                 */
                $values = Yii::app()->getDb()->createCommand()
                    ->select('value')
                    ->from('{{list_field_value}}')
                    ->where('subscriber_id = :sid AND field_id = :fid', array(':sid' => (int)$subscriber->subscriber_id, ':fid' => (int)$field->field_id))
                    ->queryAll();

                $value = array();
                foreach ($values as $val) {
                    $value[] = $val['value'];
                }
                $searchReplace['['.$field->tag.']'] = implode(', ', $value);
            }    
        }

        // common tags;
        $searchReplace['[LIST_NAME]']           = $list->name;
        $searchReplace['[LIST_DESCRIPTION]']    = $list->description;
        $searchReplace['[LIST_FROM_NAME]']      = $list->default->from_name;
        $searchReplace['[LIST_FROM_EMAIL]']     = $list->default->from_email;
        $searchReplace['[LIST_SUBJECT]']        = $list->default->subject;

        // date related
        $searchReplace['[CURRENT_YEAR]']    = date('Y');
        $searchReplace['[CURRENT_MONTH]']   = date('m');
        $searchReplace['[CURRENT_DAY]']     = date('d');
        $searchReplace['[CURRENT_DATE]']    = date('m/d/Y');
        
        $searchReplace['[COMPANY_FULL_ADDRESS]'] = !empty($list->company) ? nl2br($list->company->getFormattedAddress()) : null;
        if (!empty($list->company)) {
            $company = $list->company;
            $searchReplace['[COMPANY_NAME]']        = $company->name;
            $searchReplace['[COMPANY_ADDRESS_1]']   = $company->address_1;
            $searchReplace['[COMPANY_ADDRESS_2]']   = $company->address_2;
            $searchReplace['[COMPANY_CITY]']        = $company->city;
            $searchReplace['[COMPANY_ZONE]']        = !empty($company->zone) ? $company->zone->name : null;
            $searchReplace['[COMPANY_ZIP]']         = $company->zip_code;
            $searchReplace['[COMPANY_COUNTRY]']     = !empty($list->company->country) ? $company->country->name : null;    
            $searchReplace['[COMPANY_PHONE]']       = $company->phone;
        }
        
        $searchFor      = array_keys($searchReplace);
        $replaceWith    = array_values($searchReplace);
        
        $to = str_replace($searchFor, $replaceWith, $campaign->to_name);
        $to = $tagFilter->apply($to, $searchReplace);
        $to = preg_replace('/\[(.*)?\]/', '', $to);
        
        $subject = str_replace($searchFor, $replaceWith, $campaign->subject);
        $subject = $tagFilter->apply($subject, $searchReplace);
        $subject = preg_replace('/\[(.*)?\]/', '', $subject);
        
        if (empty($to) && !empty($subscriber->subscriber_id)) {
            $to = $subscriber->email;
        }
        
        if (empty($to)) {
            $to = 'unknown';
        }
        
        if (empty($subject)) {
            $subject = 'unknown';
        }
        
        // campaign tags, to catch the toName and the campaign subject.
        $searchReplace['[CAMPAIGN_SUBJECT]']    = $subject;
        $searchReplace['[CAMPAIGN_TO_NAME]']    = $to;
        $searchReplace['[CAMPAIGN_FROM_NAME]']  = $campaign->from_name;
        $searchReplace['[CAMPAIGN_FROM_EMAIL]'] = $campaign->from_email;
        $searchReplace['[CAMPAIGN_REPLY_TO]']   = $campaign->reply_to;
        $searchReplace['[CAMPAIGN_UID]']        = $campaign->campaign_uid;
        $searchReplace['[SUBSCRIBER_UID]']      = $subscriber->subscriber_uid;

        // parse the unsubscribe url.
        $unsubscribeUrl = $options->get('system.urls.frontend_absolute_url');
        $unsubscribeUrl .= 'lists/' . $list->list_uid . '/unsubscribe';
        if (!empty($subscriber->subscriber_id)) {
            $unsubscribeUrl .= '/' . $subscriber->subscriber_uid . '/' . $campaign->campaign_uid;
        }

        // update profile url
        $updateProfileUrl = null;
        if (!empty($subscriber->subscriber_id)) {
            $updateProfileUrl = $options->get('system.urls.frontend_absolute_url');
            $updateProfileUrl .= 'lists/' . $list->list_uid . '/update-profile/' . $subscriber->subscriber_uid;
        }
        
        // web version url
        $webVersionUrl = null;
        if (!empty($subscriber->subscriber_id)) {
            $webVersionUrl = $options->get('system.urls.frontend_absolute_url');
            $webVersionUrl .= 'campaigns/' . $campaign->campaign_uid . '/web-version/' . $subscriber->subscriber_uid;    
        }
        
        // campaign url
        $campaignUrl = $options->get('system.urls.frontend_absolute_url');
        $campaignUrl .= 'campaigns/' . $campaign->campaign_uid;
        
        $searchReplace['[UNSUBSCRIBE_URL]']     = $unsubscribeUrl;
        $searchReplace['[UPDATE_PROFILE_URL]']  = $updateProfileUrl;
        $searchReplace['[WEB_VERSION_URL]']     = $webVersionUrl;
        $searchReplace['[CAMPAIGN_URL]']        = $campaignUrl;

        // and run a massive search replace action against the email content!
        $content = str_replace(array_keys($searchReplace), array_values($searchReplace), $content);
        $content = $tagFilter->apply($content, $searchReplace);
        
        if ($appendBeacon && !empty($subscriber->subscriber_id)) {
            $beaconUrl = $options->get('system.urls.frontend_absolute_url');
            $beaconUrl .= 'campaigns/' . $campaign->campaign_uid . '/track-opening/' . $subscriber->subscriber_uid;
            $beaconImage = CHtml::image($beaconUrl, '', array('width' => 1, 'height' => 1));
            $content = str_ireplace('</body>', $beaconImage . "\n" . '</body>', $content);
        }
        
        unset($searchFor, $replaceWith, $searchReplace);
        
        return array($to, $subject, $content);
    }
    
    /**
     * CampaignHelper::transformLinksForTracking()
     * 
     * @param string $content
     * @param Campaign $campaign
     * @param ListSubscriber $subscriber
     * @param bool $canSave
     * @return string
     */
    public static function transformLinksForTracking($content, Campaign $campaign, ListSubscriber $subscriber, $canSave = false)
    {
        $content    = StringHelper::decodeSurroundingTags($content);
        $list       = $campaign->list;
        $mustSave   = false;
        
        if (!isset(self::$_trackingUrls[$campaign->campaign_id])) {
            
            self::$_trackingUrls[$campaign->campaign_id] = array();
            
            $urlModelsCount = 0;
            if ($canSave) {
                $urlModelsCount = CampaignUrl::model()->countByAttributes(array(
                    'campaign_id' => $campaign->campaign_id,
                ));    
            }

            $mustSave       = $urlModelsCount == 0;
            $baseUrl        = Yii::app()->options->get('system.urls.frontend_absolute_url');
            $trackingUrl    = $baseUrl . 'campaigns/[CAMPAIGN_UID]/track-url/[SUBSCRIBER_UID]';
    
            // (\042|\047) are octal quotes.
            $pattern = '/href(\s+)?=(\s+)?(\042|\047)(\s+)?(.*?)(\s+)?(\042|\047)/i';
            if (!preg_match_all($pattern, $content, $matches)) {
                return $content;
            }
            
            $urls = $matches[5];
            $urls = array_map('trim', $urls);
            // combine url with markup
            $urls = array_combine($urls, $matches[0]);
            $foundUrls = array();
            
            foreach ($urls as $url => $markup) {
    
                // external url which may contain one or more tags(sharing maybe?)
                if (preg_match('/https?.*/i', $url, $matches) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $_url = trim($matches[0]);
                    $foundUrls[$_url] = $markup;
                    continue;
                }
                
                // local tag to be transformed
                if (preg_match('/^\[([A-Z_]+)_URL\]$/', $url, $matches)) {
                    $_url = trim($matches[0]);
                    $foundUrls[$_url] = $markup;
                    continue;
                }
            }
            
            if (empty($foundUrls)) {
                return $content;
            }
   
            $prefix = $campaign->campaign_uid;
            $sort   = array();
            
            foreach ($foundUrls as $url => $markup) {

                $urlHash = sha1($prefix . $url);
                $track   = $trackingUrl . '/' . $urlHash;
                $length  = strlen($url);
                
                self::$_trackingUrls[$campaign->campaign_id][] = array(
                    'url'       => $url,
                    'hash'      => $urlHash,
                    'track'     => $track,
                    'length'    => $length,
                    'markup'    => $markup,
                );
                
                $sort[] = $length;
            }
            
            unset($foundUrls);    
            // make sure we order by the longest url to the shortest
            array_multisort($sort, SORT_DESC, SORT_NUMERIC, self::$_trackingUrls[$campaign->campaign_id]);
        }
        
        if (!empty(self::$_trackingUrls[$campaign->campaign_id])) {
            
            $searchReplace = array();
            foreach (self::$_trackingUrls[$campaign->campaign_id] as $urlData) {
                $searchReplace[$urlData['markup']] = 'href="'.$urlData['track'].'"';
            }
            
            $content = str_replace(array_keys($searchReplace), array_values($searchReplace), $content);
            unset($searchReplace);

            // save the url tags.
            if ($mustSave && $canSave) {
                
                foreach (self::$_trackingUrls[$campaign->campaign_id] as $urlData) {
                    
                    $urlModel = CampaignUrl::model()->findByAttributes(array(
                        'campaign_id' => (int)$campaign->campaign_id,
                        'hash'        => $urlData['hash'],
                    ));
                    
                    if (!empty($urlModel)) {
                        continue;
                    }
                    
                    $urlModel = new CampaignUrl();
                    $urlModel->campaign_id = $campaign->campaign_id;
                    $urlModel->destination = $urlData['url'];
                    $urlModel->hash = $urlData['hash'];
                    $urlModel->save();
                }
            }
        }
        
        // return transformed
        return $content;
    }
    
    /**
     * CampaignHelper::htmlToText()
     * 
     * @param string $content
     * @return string
     */
    public static function htmlToText($content)
    {
        static $html2text;
        
        if ($html2text === null) {
            Yii::import('common.vendors.Html2Text.*');
            $html2text = new Html2Text();
            
            if (!MW_IS_CLI) {
                $appName = Yii::app()->apps->getCurrentAppName();
                $options = Yii::app()->options;
                $html2text->set_base_url($options->get('system.urls.'.$appName.'_absolute_url'));
            }
        }
        
        $html2text->set_html($content);
        
        return $html2text->get_text();
    }
    
    /**
     * CampaignHelper::getSpamScore()
     * 
     * @param Campaign $campaign
     * @return mixed
     */
    public static function getSpamScore(Campaign $campaign)
    {
        if (empty($campaign->template) || empty($campaign->template->content)) {
            return false;
        }
        
        if (!CommonHelper::functionExists('curl_init')) {
            return false;
        }
        
        if (!($server = DeliveryServer::pickServer())) {
            return false;
        }
        
        $subscriber = ListSubscriber::model()->findByAttributes(array(
            'list_id'   => $campaign->list->list_id,
            'status'    => ListSubscriber::STATUS_CONFIRMED,
        ));
        
        $fromName       = $campaign->from_name;
        $toName         = $campaign->from_name;
        $emailSubject   = $campaign->subject;
        $emailContent   = $campaign->template->content;
        $emailAddress   = $campaign->reply_to;
        
        if (!empty($subscriber)) {
            
            if (!empty($campaign->option) && $campaign->option->xml_feed == CampaignOption::TEXT_YES) {
                $emailContent = CampaignXmlFeedParser::parseContent($emailContent);
            }
            
            if (!empty($campaign->option) && $campaign->option->json_feed == CampaignOption::TEXT_YES) {
                $emailContent = CampaignJsonFeedParser::parseContent($emailContent);
            }

            $emailData = self::parseContent($emailContent, $campaign, $subscriber, false);
            list(, $emailSubject, $emailContent) = $emailData;
        }

        $emailPlainText = null;
        if (!empty($campaign->option) && $campaign->option->plain_text_email == CampaignOption::TEXT_YES) {
            $emailPlainText = self::htmlToText($emailContent);
        }
        
        $emailParams = array(
            'from'      => array($server->getFromEmail() => $campaign->from_name),
            'replyTo'   => array($campaign->reply_to => $campaign->from_name),
            'to'        => array($emailAddress => $toName),
            'subject'   => $emailSubject,
            'body'      => $emailContent,
            'plainText' => $emailPlainText,
        );

        $headers = array(
			'Accept: application/json',
			'Content-Type: application/json'
		);

        $encodedData = CJSON::encode(array(
            'email'     => Yii::app()->mailer->getEmailMessage($server->getParamsArray($emailParams)), 
            'options'   => 'short'
        ));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://spamcheck.postmarkapp.com/filter');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        
        if (curl_error($ch) != '') {
            return false;
        }
        
        return CJSON::decode($response);
    }
    
    /**
     * CampaignHelper::resetTrackingUrls()
     * 
     * @return
     */
    public static function resetTrackingUrls()
    {
        self::$_trackingUrls = array();
    }
    
    /**
     * CampaignHelper::minifyContent()
     * 
     * @param string $content
     * @return string $content
     */
    public static function minifyContent($content)
    {
        return EmailTemplateParser::minifyContent($content);
    }
    
    /**
     * CampaignHelper::embedContentImages()
     * 
     * @param string $content
     * @param Campaign $campaign
     * @return mixed
     */
    public static function embedContentImages($content, Campaign $campaign)
    {
        if (empty($content)) {
            return array($content, array());
        }

        static $parsed = array();
        $key = sha1($campaign->campaign_uid . $content);
        
        if (isset($parsed[$key]) || array_key_exists($key, $parsed)) {
            return $parsed[$key];
        }
        
        if (!CommonHelper::functionExists('qp')) {
            require_once(Yii::getPathOfAlias('common.vendors.QueryPath.src.QueryPath') . '/QueryPath.php');
        }
        
        $embedImages = array();
        $storagePath = Yii::getPathOfAlias('root.frontend.assets.gallery');
        $extensions  = array('jpg', 'jpeg', 'png', 'gif');
        
        libxml_use_internal_errors(true);
        
        try {
            
            $query = qp($content, 'body', array(
                'ignore_parser_warnings'    => true,
                'convert_to_encoding'       => Yii::app()->charset,
                'convert_from_encoding'     => Yii::app()->charset,
                'use_parser'                => 'html',
            ));    
            
            // to do: what action should we take here?
            if (count(libxml_get_errors()) > 0) {}
            
            $images = $query->top()->find('img');    
            
            if (empty($images) || !is_object($images) || $images->length == 0) {
                throw new Exception('No images found!');
            }

            foreach ($images as $image) {
                $src = $image->attr('src');
                $src = str_replace(array('../', './', '..\\', '.\\', '..'), '', trim($src));
                
                if (empty($src)) {
                    continue;
                }
                
                $ext = pathinfo($src, PATHINFO_EXTENSION);
                if (empty($ext) || !in_array(strtolower($ext), $extensions)) {
                    continue;
                }
                unset($ext);
                
                if (preg_match('/\/frontend\/assets\/gallery\/(([a-zA-Z0-9]{13,})\/.*)/', $src, $matches)) {
                    $src = $matches[1];
                }
                
                if (preg_match('/^https?/i', $src)) {
                    continue;
                }
                
                $fullFilePath = $storagePath . '/' . $src;
                if (!is_file($fullFilePath)) {
                    continue;
                }
                
                $imageInfo = @getimagesize($fullFilePath);
                if (empty($imageInfo[0]) || empty($imageInfo[1]) || empty($imageInfo['mime'])) {
                    continue;
                }
                
                $cid = sha1($fullFilePath);
                $embedImages[] = array(
                    'name'  => basename($fullFilePath),
                    'path'  => $fullFilePath,
                    'cid'   => $cid,
                    'mime'  => $imageInfo['mime'],
                );
                
                $image->attr('src', 'cid:' . $cid);
                unset($fullFilePath, $cid, $imageInfo);
            }
            
            $content = $query->top()->html();
            unset($query, $images);
        
        } catch (Exception $e) {}
        
        libxml_use_internal_errors(false);
        return $parsed[$key] = array($content, $embedImages);
    }
    
    public static function extractTemplateUrls($content)
    {
        if (empty($content)) {
            return array();
        }
        
        static $urls = array();
        $hash = sha1($content);

        if (array_key_exists($hash, $urls)) {
            return $urls[$hash];
        }
        
        $urls[$hash] = array();
        if (!CommonHelper::functionExists('qp')) {
            require_once(Yii::getPathOfAlias('common.vendors.QueryPath.src.QueryPath') . '/QueryPath.php');
        }
        
        libxml_use_internal_errors(true);
        
        try {
            
            $query = qp($content, 'body', array(
                'ignore_parser_warnings'    => true,
                'convert_to_encoding'       => Yii::app()->charset,
                'convert_from_encoding'     => Yii::app()->charset,
                'use_parser'                => 'html',
            ));    
            
            // to do: what action should we take here?
            if (count(libxml_get_errors()) > 0) {}
            
            $anchors = $query->top()->find('a');    
            
            if (empty($anchors) || !is_object($anchors) || $anchors->length == 0) {
                throw new Exception('No anchor found!');
            }

            foreach ($anchors as $anchor) {
                $urls[$hash][] = trim($anchor->attr('href'));
            }
            
            unset($query, $anchors);
        
        } catch (Exception $e) {}
        
        libxml_use_internal_errors(false);
        
        $urls[$hash] = array_unique($urls[$hash]);
        
        // remove tag urls
        $strlen = CommonHelper::functionExists('mb_strlen') ? 'mb_strlen' : 'strlen';
        foreach ($urls[$hash] as $index => $url) {
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                unset($urls[$hash][$index]);
            }
        }
        
        sort($urls[$hash]);
        
        return $urls[$hash];
    }
    
    public static function injectEmailFooter($emailContent, $emailFooter, Campaign $campaign)
    {
        return str_ireplace('</body>', $emailFooter . "\n" . '</body>', $emailContent);
    }
}