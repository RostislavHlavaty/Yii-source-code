<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * EmailTemplateTagFilter
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class EmailTemplateTagFilter extends CApplicationComponent 
{
    /**
     * EmailTemplateTagFilter::getFiltersMap()
     * 
     * @return array
     */
    public function getFiltersMap()
    {
        return array(
            // name            // callback
            'urlencode'     => 'urlencode',
            'rawurlencode'  => 'rawurlencode',
            'htmlencode'    => array('CHtml', 'encode'),
            'trim'          => 'trim',
            'uppercase'     => 'strtoupper',
            'lowercase'     => 'strtolower',
            'ucwords'       => 'ucwords',
            'ucfirst'       => 'ucfirst',
            'reverse'       => 'strrev',
        );
    }
    
    /**
     * EmailTemplateTagFilter::apply()
     * 
     * @param mixed $content
     * @param mixed $registeredTags
     * @return string
     */
    public function apply($content, array $registeredTags)
    {
        $filtersMap = $this->getFiltersMap();
        
        $searchReplace = array();
        foreach ($registeredTags as $tagName => $tagValue) {
            
            if (empty($tagValue)) {
                continue;
            }
            
            $tagName = str_replace(array('[', ']'), '', $tagName);
            if (strpos($content, '['.$tagName.':filter:') === false) {
                continue;
            }
            
            // do we really need preg_quote ?
            if (preg_match_all('/\['.preg_quote($tagName, '/').':filter:([a-z|]+)\]/', $content, $matches)) {
                if (empty($matches[1])) {
                    continue;
                }
                
                $filterTags     = array_unique($matches[0]);
                $filterStrings  = array_unique($matches[1]);
                
                if (count($filterStrings) != count($filterTags)) {
                    continue;
                }
                
                $tagToFilters = array_combine($filterTags, $filterStrings);
                unset($filterTags, $filterStrings);
                
                foreach ($tagToFilters as $tag => $filtersString) {
                    $filters = explode('|', $filtersString);
                    if (empty($filters)) {
                        continue;
                    }
                    
                    $filters    = array_map('trim', $filters);
                    $filtered   = false;
                    foreach ($filters as $filterName) {
                        if (!isset($filtersMap[$filterName]) || !is_callable($filtersMap[$filterName])) {
                            continue;
                        }
                        $filtered = true;
                        $tagValue = call_user_func($filtersMap[$filterName], $tagValue);
                    }
                    
                    if ($filtered && !empty($tagValue)) {
                        $searchReplace[$tag] = $tagValue;    
                    }
                }
            }
        }
        
        if (empty($searchReplace)) {
            return $content;
        }

        $content = str_replace(array_keys($searchReplace), array_values($searchReplace), $content);
        return $content;
    }
    
}