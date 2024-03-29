/**
 * This file is part of the MailWizz EMA application.
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.0
 */
jQuery(document).ready(function($){
    
    $('ul.list-forms-nav li a').on('click', function(){
        var $lis = $('ul.list-forms-nav li');
        var $li = $(this).closest('li');
        if (!$li.is('.active')) {
            $lis.removeClass('active');
            $li.addClass('active');
            $('.form-container').hide();
            $($(this).attr('href')).show();
        }
        return false;
    });
});