<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CampaignJsonFeedParser
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3
 */
 
class CampaignJsonFeedParser
{
    public static $maxItemsCount = 100;
    
    public static $itemsCount = 10;
    
    public static function parseContent($content, $campaign = null, $cache = false)
    {
        $content = StringHelper::decodeSurroundingTags($content);
        if (strpos($content, '[JSON_FEED_BEGIN ') === false || strpos($content, '[JSON_FEED_END]') === false) {
            return $content;
        }
        
        if (!preg_match('/\[JSON_FEED_BEGIN(.*?)\](.*)\[JSON_FEED_END\]/sx', $content, $matches)) {
            return $content;
        }
        
        if (!isset($matches[0], $matches[2])) {
            return $content;
        }
        
        $fullFeedHtml       = $matches[0];
        $feedItemTemplate   = $matches[2];
        
        $doCache = false;
        if (!empty($campaign) && ($campaign instanceof Campaign) && !$campaign->isNewRecord && $cache) {
            $doCache = true;
        }
        
        if ($doCache && ($parsedFeedHtml = Yii::app()->cache->get('campaign_json_feed_' . $campaign->campaign_uid))) {
            return str_replace($fullFeedHtml, $parsedFeedHtml, $content);
        }
        
        preg_match('/\[JSON_FEED_BEGIN(.*?)\]/', $fullFeedHtml, $matches);
        if (empty($matches[1])) {
            return $content;
        }
        
        $attributesPattern  = '/(\w+) *= *(?:([\'"])(.*?)\\2|([^ "\'>]+))/';
        preg_match_all($attributesPattern, $matches[1], $matches, PREG_SET_ORDER);
        if (empty($matches)) {
            return $content;
        }
        
        $attributes = array();
        foreach ($matches as $match) {
            if (!isset($match[1], $match[3])) {
                continue;
            }
            $attributes[strtolower($match[1])] = $match[3];
        }
        
        if (!isset($attributes['url']) || !filter_var($attributes['url'], FILTER_VALIDATE_URL)) {
            return $content;
        }
        
        $count = self::$itemsCount;
        if (isset($attributes['count']) && (int)$attributes['count'] > 0 && (int)$attributes['count'] <= self::$maxItemsCount) {
            $count = (int)$attributes['count'];
        }
        
        $feedItems = self::getRemoteFeedItems(str_replace('&amp;', '&', $attributes['url']), $count);
        
        if (empty($feedItems)) {
            return $content;
        }
    
        $feedItemsMap = array(
            '[JSON_FEED_ITEM_TITLE]'         => 'title',
            '[JSON_FEED_ITEM_DESCRIPTION]'   => 'description',
            '[JSON_FEED_ITEM_CONTENT]'       => 'content',
            '[JSON_FEED_ITEM_IMAGE]'         => 'image', 
            '[JSON_FEED_ITEM_LINK]'          => 'link', 
            '[JSON_FEED_ITEM_PUBDATE]'       => 'pubDate', 
            '[JSON_FEED_ITEM_GUID]'          => 'guid', 
        );
    
        $html = '';
        foreach ($feedItems as $feedItem) {
            $itemHtml = $feedItemTemplate;
            foreach ($feedItemsMap as $tag => $mapValue) {
                if (!isset($feedItem[$mapValue]) || !is_string($feedItem[$mapValue])) {
                    continue;
                }
                $itemHtml = str_replace($tag, $feedItem[$mapValue], $itemHtml);
            }
            if (sha1($itemHtml) != sha1($feedItemTemplate)) {
                $html .= $itemHtml;
            }
        }
        
        if ($doCache) {
            Yii::app()->cache->add('campaign_json_feed_' . $campaign->campaign_uid, $html, MW_CACHE_TTL);
        }
        
        return str_replace($fullFeedHtml, $html, $content);
    }
    
    public static function getRemoteFeedItems($url, $count = 10) 
    {
        $items      = array();
        $response   = AppInitHelper::simpleCurlGet($url);
    
        if ($response['status'] == 'error' || empty($response['message'])) {
            return $items;
        }
        
        $json = @CJSON::decode($response['message'], false);
    
        if (empty($json)) {
            return $items;
        }
        
        foreach($json as $item) {
            
            if (count($items) >= $count) {
                break;
            }    
            
            $itemMap = array( 
                'title'         => null,
                'description'   => null,
                'content'       => null,
                'image'         => null,
                'link'          => null,
                'pubDate'       => null,
                'guid'          => null,
            );
            
            if (!empty($item->title)) {
                $itemMap['title'] = (string)$item->title;
            }
            
            if (!empty($item->description)) {
                $itemMap['description'] = (string)$item->description;
            }
            
            if (!empty($item->content)) {
                $itemMap['content'] = (string)$item->content;
            }
            
            if (!empty($item->image)) {
                $itemMap['image'] = (string)$item->image;
            }
    
            if (!empty($item->link)) {
                $itemMap['link'] = (string)$item->link;
            }
            
            if (!empty($item->pubDate)) {
                $itemMap['pubDate'] = (string)$item->pubDate;
            }
            
            if (!empty($item->guid)) {
                $itemMap['guid'] = (string)$item->guid;
            }
            
            $itemMap = array_map(array('CHtml', 'decode'), $itemMap);
            $itemMap = array_map(array('CHtml', 'encode'), $itemMap);
            $items[] = $itemMap;
        }
    
        return $items;
    }
}