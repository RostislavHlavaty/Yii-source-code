<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * CampaignTemplateValidator
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class CampaignTemplateValidator extends CValidator
{
    protected function validateAttribute($object, $attribute)
    {
        // extract the attribute value from it's model object
        $value = $object->$attribute;
        if ($object->scenario == 'copy') {
            return;
        }
        
        if ($object->hasErrors($attribute)) {
            return;
        }
        
        $tags = $object->getAvailableTags();
        $missingTags = array();
        
        foreach ($tags as $tag) {
            
            if (!isset($tag['tag']) || !isset($tag['required']) || !$tag['required']) {
                continue;
            }
    
            if (!isset($tag['pattern']) && strpos($value, $tag['tag']) === false) {
                $missingTags[] = $tag['tag'];
            } elseif (isset($tag['pattern']) && !preg_match($tag['pattern'], $value)) {
                $missingTags[] = $tag['tag'];
            }
        }
        
        if (!empty($missingTags)) {
            $missingTags = array_unique($missingTags);
            $this->addError($object, $attribute, Yii::t('campaigns', 'The following tags are required but were not found in your content: {tags}', array(
                '{tags}' => implode(', ', $missingTags),
            )));
        }
        
        if ($object->hasErrors($attribute)) {
            return;
        }
    }
}