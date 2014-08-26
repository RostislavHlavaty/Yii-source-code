<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * 
 * EmailTemplateParser
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
 
class EmailTemplateParser extends CApplicationComponent 
{
    private $_isParsed = false;
    
    private $_content;
    
    public $inlineCss = false;
    
    public $minify = false;
    
    /**
     * EmailTemplateParser::setContent()
     * 
     * @param mixed $content
     * @return EmailTemplateParser
     */
    public function setContent($content)
    {
        $this->_isParsed = false;
        $this->_content = $content;
        return $this;
    }
    
    /**
     * EmailTemplateParser::getContent()
     * 
     * @return mixed
     */
    public function getContent()
    {
        if ($this->_isParsed) {
            return $this->_content;
        }
        
        if (empty($this->_content)) {
            $this->_isParsed = true;
            return $this->_content = null;
        }
        
        $ioFilter = Yii::app()->ioFilter;
        
        // decode
        $this->_content = CHtml::decode($this->_content);
        
        // remove invisible chars
        $this->_content = $this->removeInvisibleCharacters($this->_content);
        
        // compact words if there's the case for this
        $this->_content = $this->compactWords($this->_content);
        
        // remove attributes like onclick|onload|etc. (042 and 047 are octal quotes)
        $this->_content = preg_replace('#\bon\w*\s*=\s*(\042|\047)([^\\1]*?)(\\1)([^\w]+)?#six', '', $this->_content);

        // some expressions never allowed
        $notAllowedRegex = array(
            'javascript\s*:', 'expression\s*(\(|&\#40;)', 'vbscript\s*:', 'Redirect\s+302',
            "([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?",
        );
        
        foreach ($notAllowedRegex as $regex) {
            $this->_content = preg_replace('#'.$regex.'#is', '', $this->_content);
        }
        
        // some strings never allowed
        $notAllowedStrings = array(
            'document.cookie', 'document.write', '.parentNode', '.innerHTML', 
            'window.location', '-moz-binding', '<![CDATA[', '<comment>'
        );
        
        $this->_content = str_replace($notAllowedStrings, '', $this->_content);
        
        if (empty($this->_content)) {
            $this->_isParsed = true;
            return $this->_content = null;
        }
        
        $content = $this->_content;
        
        // if no body element, goodbye.
        preg_match('/<body[^>]*>(.*?)<\/body>/si', $content, $matches);
        if (empty($matches[1])) {
            $this->_isParsed = true;
            return $this->_content = null;
        }
        
        $document = new CMap(array(
            'head'              => null,
            'metaTags'          => null,
            'css'               => null,
            'conditionalCss'    => null,
            'body'              => $this->decodeSurroundingTags(trim($ioFilter->purify($matches[1]))),
            
        ));
        
        $_cssBlock  = '';
        $cssBlock   = '';
        $conditionalCssBlock = '';

        // conditional tags?
        $head = null;
        preg_match('/<head[^>]*>(.*?)<\/head>/si', $content, $matches);
        if (!empty($matches[1])) {
            $head = $matches[1];
        }
        $conditionalPatternMatch = '/(<!--\[if[a-z0-9\s]+\]>)([^\]]+)?(<!\[endif\]-->)/six';
        $conditionalPatternReplace = '/(<!--\[[^\]]+\]>)([^\]]+)?(<!\[[^\]]+\]-->)/six';
        preg_match_all($conditionalPatternMatch, $head, $matches);
        
        if (!empty($matches[0]) && !empty($matches[1]) && !empty($matches[3])) {
            foreach ($matches[0] as $index => $condition) {
                $replaced = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/six', array($this, 'replaceInConditionalBlock'), $condition);
                // if nothing replaced, style not found
                if ($replaced == $condition) {
                    unset($matches[0][$index]);
                    continue;
                }
                // because <script> tags might be inserted in the conditional tags. We only need the style.
                preg_match('/<style[^>]*>(.*?)<\/style>/six', $replaced, $_matches);
                if (empty($_matches[0]) || empty($matches[1][$index]) || empty($matches[3][$index])) {
                    unset($matches[0][$index]);
                    continue;
                }
                $matches[0][$index] = $matches[1][$index] ."\n". $_matches[0] ."\n". $matches[3][$index] . "\n";
            }
            if (!empty($matches[0])) {
                $conditionalCssBlock = implode("\n", $matches[0]);
            }
        }
        $document->add('conditionalCss', $conditionalCssBlock);
        
        // remove the conditional tags now
        $content = preg_replace($conditionalPatternReplace, '', $content);
        
        // extract all the styles from the now lighter content.
        preg_match_all('/<style[^>]*>(.*?)<\/style>/six', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $plainCss) {
                $cssBlock .= trim($plainCss) . "\n";
            }
            $cssBlock = trim($this->encode($ioFilter->purify($cssBlock)));
            $_cssBlock = $cssBlock;
            $cssBlock = "\n" . '<style type="text/css">' . "\n" . $cssBlock . "\n" . '</style>' . "\n";
        }
        
        $document->add('css', $cssBlock);
        
        // TO DO: maybe add dir="Yii::app()->locale->orientation" to html element?
        $stub = '<!DOCTYPE html>
        <html>
            <head>
                
            </head>
            <body>
                
            </body>
        </html>';
        
        require_once(Yii::getPathOfAlias('common.vendors.QueryPath.src.QueryPath') . '/QueryPath.php');
        $newHtml = qp($stub, null, array(
            'ignore_parser_warnings'    => true,
            'convert_to_encoding'       => Yii::app()->charset,
            'convert_from_encoding'     => Yii::app()->charset,
            'use_parser'                => 'html',
        ));

        $newHtml->top()->find('head')->append($document->itemAt('css'));
        $newHtml->top()->find('head')->append($document->itemAt('conditionalCss'));
        $newHtml->top()->find('body')->append($document->itemAt('body'));

        libxml_use_internal_errors(true);
        $providedHtml = qp($this->_content, null, array(
            'ignore_parser_warnings'    => true,
            'convert_to_encoding'       => Yii::app()->charset,
            'convert_from_encoding'     => Yii::app()->charset,
            'use_parser'                => 'html',
        ));
        
        // to do: what action should we take here?
        if (count(libxml_get_errors()) > 0) {
            
        }
        
        $body = $providedHtml->top()->find('body');
        if ($body->length == 1) {
            $bodyAttributes = $body->attr();
            if (!empty($bodyAttributes)) {
                foreach ($bodyAttributes as $name => $value) {
                    if (stripos($name, 'on') === 0 || stripos($value, 'javascript') !== false) {
                        unset($bodyAttributes[$name]);
                        continue;
                    }
                    $bodyAttributes[CHtml::encode($name)] = CHtml::encode($value);
                }
                $newHtml->top()->find('body')->attr($bodyAttributes);
            }
        }
        
        $head = $providedHtml->top()->find('head');
        if ($head->length == 1) {
            $metaTags = $head->find('meta');
            if ($metaTags->length > 0) {
                foreach ($metaTags as $metaTag) {
                    $attributes = $metaTag->attr();
                    if (!empty($attributes)) {
                        foreach ($attributes as $name => $value) {
                            $metaTag->attr(CHtml::encode($name), CHtml::encode($value));
                        }
                        $newHtml->top()->find('head')->prepend($metaTag);
                    }
                }
            }
            $title = $providedHtml->top()->find('head title');
            if ($title->length > 0) {
                $newHtml->top()->find('head')->prepend('<title>'.CHtml::encode($title->text()).'</title>');
            }
        }
        
        $title = $newHtml->top()->find('head title');
        if ($title->length == 0) {
            $newHtml->top()->find('head')->prepend('<title>Untitled</title>');
        }
        
        $charsetMeta = $newHtml->top()->find('head meta[http-equiv="Content-Type"]');
        if ($charsetMeta->length > 0) {
            $charsetMeta->remove(); // this can cause problems!    
        }
        
        $charsetMeta = $newHtml->top()->find('head')->find('meta[name=charset]');
        if ($charsetMeta->length == 1) {
            $charsetMeta->attr('content', Yii::app()->charset);
        } else {
            $newHtml->top()->find('head')->prepend(CHtml::metaTag(Yii::app()->charset, 'charset'));
        }
        
        // stop here if no need to copy inline.
        if (!$this->inlineCss) {
            $this->_isParsed = true;
            $this->_content = $newHtml->top()->html();
            $this->_content = $this->decodeSurroundingTags($this->_content);
            $this->_content = str_replace(array('&gt;'), array('>'), $this->_content);
            libxml_use_internal_errors(false);
            if ($this->minify) {
                $this->_content = self::minifyContent($this->_content);
            }
            return $this->_content;
        }
        
        // start adding inline styles
        $normalizeSearch = array('; ', ' ;', ': ', ' :', ', ', ' ,', ';;', '"', '!important', ';;');
        $normalizeReplace = array(';', ';', ':', ':', ',', ',', ';', "'", '', ';');
        
        // remove comments
        $_cssBlock = preg_replace('#/\*[^*]*\*+([^/][^*]*\*+)*/#', '', $_cssBlock);
        
        // remove media queries
        $mediaQueries = $this->parseMediaQueriesIntoArray($_cssBlock);
        if (!empty($mediaQueries)) {
            $_cssBlock = str_replace($mediaQueries, '', $_cssBlock);
        }
        // $_cssBlock = preg_replace('#(@media[^{]*{(?:(?!}\s*}).)*}.*?})#six', '', $_cssBlock); // memory hog!
        
        // remove import style
        $_cssBlock = preg_replace('#(\s*)?@import.*;(\s*)?#i', '', $_cssBlock);
        
        // and see if we have css left to match
        preg_match_all('/[\.\#a-z0-9\s_\-\:"\'\[\]=\*,]+\{[^\}]+\}?/six', $_cssBlock, $cssElementRules);
        
        if (!empty($cssElementRules)) {
            foreach ($cssElementRules[0] as $rule) {
                preg_match('/([\.\#a-z0-9\s_\-\:"\'\[\]=\*,]+)\{/six', $rule, $matches);
                if (empty($matches[1])) {
                    continue;
                }
                
                $selector = $matches[1];
                $selector = preg_replace('/\s{2,}/six', ' ', $selector);
                $selector = trim($selector);
                if (empty($selector)) {
                    continue;
                }
                
                // selector properties, as in font-weight:bold; color:#333333
                preg_match('/\{([^\}]+)\}/six', $rule, $matches);
                if (empty($matches[1])) {
                    continue;
                }
                $properties = rtrim($matches[1], ';') . ';';
    
                try {
                    $elements = $newHtml->top()->find('body')->find($selector);
                } catch (Exception $e) {
                    $elements = null;
                }
                
                if ($elements === null || $elements->length == 0) {
                    continue;
                }
                
                foreach ($elements as $element) {
                    $style = '';
                    if ($element->attr('style')) {
                        $style = rtrim($element->attr('style'), ';') . ';';
                    }
                    
                    $newStyle = CHtml::decode($style . $properties);
                    // normalize things a bit
                    $newStyle = str_replace($normalizeSearch, $normalizeReplace, $newStyle);
                    $newStyle = preg_replace('/\s{2,}/six', ' ', $newStyle);
                    $newStyle = trim($newStyle);
                    $newStyle = explode(';', $newStyle);
                    $newStyle = array_map('trim', $newStyle);
                    $newStyle = array_unique($newStyle);

                    $tempNewStyle = array();
                    foreach ($newStyle as $property) {
                        if (strpos($property, ':') === false) {
                            continue;
                        }
                        $_parts = explode(':', $property);
                        $_parts = array_map('trim', $_parts);
                        if (count($_parts) != 2) {
                            continue;
                        }
                        list($propName, $propValue) = $_parts;
                        $propName = strtolower($propName);
                        // this makes sure there are no duplicates.
                        if (isset($tempNewStyle[$propName])) {
                            continue;
                        }
                        $tempNewStyle[$propName] = $propValue;
                    }
                    if (empty($tempNewStyle)) {
                        continue;
                    }
                    $newStyle = array();
                    foreach ($tempNewStyle as $propName => $propValue) {
                        $newStyle[] = $propName.':'.$propValue;
                    } 

                    $newStyle = implode(';', $newStyle);
                    $element->attr('style', $this->encode($newStyle));
                }
            }
        }
        // end adding inline styles
        
        $this->_isParsed = true;
        $this->_content = $newHtml->top()->html();
        $this->_content = $this->decodeSurroundingTags($this->_content);
        $this->_content = str_replace(array('&gt;'), array('>'), $this->_content);
        libxml_use_internal_errors(false);
        if ($this->minify) {
            $this->_content = self::minifyContent($this->_content);
        }
        return $this->_content;
    }
    
    /**
     * EmailTemplateParser::minifyContent()
     * 
     * @param string $content
     * @return string $content
     */
    public static function minifyContent($content)
    {
        static $minified = array();
        $contentKey = sha1($content);
        
        if (isset($minified[$contentKey]) || array_key_exists($contentKey, $minified)) {
            return $minified[$contentKey];
        }
        
        if (!class_exists('Minify_Autoloader', false)) {
            require_once Yii::getPathOfAlias('common.vendors.Minify.Autoloader').'.php';
            Yii::registerAutoloader(array('Minify_Autoloader', 'autoloader'), true);
        }

        $content = Minify_HTML::minify($content, array(
            'cssMinifier' => array('Minify_CSS', 'minify'),
        ));
        
        return $minified[$contentKey] = $content;
    }
    
    /**
     * EmailTemplateParser::replaceInConditionalBlock()
     * 
     * @param mixed $matches
     * @return string
     */
    public function replaceInConditionalBlock($matches)
    {
        if (empty($matches[1])) {
            return '';
        }
        $cssBlock = $this->encode(Yii::app()->ioFilter->purify($matches[1]));
        return '<style type="text/css">' . "\n". trim($cssBlock) ."\n". '</style>';    
    }
    
    /**
     * EmailTemplateParser::decodeSurroundingTags()
     * 
     * @param mixed $content
     * @return string
     */
    protected function decodeSurroundingTags($content)
    {
        return StringHelper::decodeSurroundingTags($content);
    }

    /**
     * EmailTemplateParser::compactWords()
     * 
     * Credits to CI's Security class.
     * 
     * @param mixed $str
     * @return string
     */
    protected function compactWords($str)
    {
        $words = array(
            'javascript', 'expression', 'vbscript', 'script', 'base64',
            'applet', 'alert', 'document', 'write', 'cookie', 'window',
            'style', 'link', 'meta'
        );

        foreach ($words as $word) {
            $temp = '';

            for ($i = 0, $wordlen = strlen($word); $i < $wordlen; $i++) {
                $temp .= substr($word, $i, 1)."\s*";
            }

            // We only want to do this when it is followed by a non-word character
            // That way valid stuff like "dealer to" does not become "dealerto"
            $str = preg_replace_callback('#('.substr($temp, 0, -3).')(\W)#is', array($this, 'compactExplodedWords'), $str);
        }
        
        return $str;
    }
    
    /**
     * EmailTemplateParser::compactExplodedWords()
     * 
     * Credits to CI's Security class.
     * 
     * @param mixed $matches
     * @return string
     */
    protected function compactExplodedWords($matches)
    {
        return preg_replace('/\s+/s', '', $matches[1]).$matches[2];
    }
    
    /**
     * EmailTemplateParser::removeInvisibleCharacters()
     * 
     * Credits to CI's Security class.
     * 
     * @param mixed $str
     * @param bool $url_encoded
     * @return string
     */
    protected function removeInvisibleCharacters($str, $url_encoded = TRUE)
    {
        $non_displayables = array();
        
        // every control character except newline (dec 10)
        // carriage return (dec 13), and horizontal tab (dec 09)
        
        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/';    // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/';    // url encoded 16-31
        }
        
        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';    // 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        }
        while ($count);

        return $str;
    }
    
    /**
     * EmailTemplateParser::encode()
     * 
     * @param mixed $text
     * @return string
     */
    protected function encode($text)
    {
        return htmlspecialchars($text, ENT_NOQUOTES, Yii::app()->charset);
    }
    
    /**
     * EmailTemplateParser::parseMediaQueriesIntoArray()
     * 
     * @param string $css
     * @return array
     */
    protected function parseMediaQueriesIntoArray($css)
    {
        $blocks = array();
        $start = 0;
        while (($start = strpos($css, "@media", $start)) !== false) {
            $s = array();
            $i = strpos($css, "{", $start);
            if ($i !== false) {
                array_push($s, $css[$i]);
                $i++;
                while (!empty($s)) {
                    if ($css[$i] == "{") {
                        array_push($s, "{");
                    } elseif ($css[$i] == "}") {
                        array_pop($s);
                    }
                    $i++;
                }
                $blocks[] = substr($css, $start, ($i + 1) - $start);
                $start = $i;
            }
        }
        return $blocks;
    }
}