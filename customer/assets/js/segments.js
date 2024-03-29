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
	var segmentConditionsIndex = $('.conditions-container .item').length, 
		$segmentConditionsTemplate = $('#condition-template');
	
	$('.btn-add-condition').on('click', function(){
		var html = $segmentConditionsTemplate.html();
		html = html.replace(/\{index\}/g, segmentConditionsIndex);
		$('.conditions-container').append(html);
		$('.btn-show-segment-subscribers').hide();
		++segmentConditionsIndex;
		return false;
	});
	
	$(document).on('click', '.btn-remove-condition', function(){
		$(this).closest('.item').remove();
		$('.btn-show-segment-subscribers').hide();
		return false;
	});
	
	$('.btn-show-segment-subscribers').on('click', function(){
		var $this = $(this);
		$this.button('loading');
		$.get($this.attr('href'), {}, function(html){
			$('#subscribers-wrapper').html(html);
			$('.subscribers-wrapper').show();
			$this.button('reset');
			$this.hide();
		});
		return false;
	});
	
	$(document).on('click', 'ul#subscribers-pagination li a', function(){
		$.get($(this).attr('href'), {}, function(html){
			$('#subscribers-wrapper').html(html);
			$('.subscribers-wrapper').show();
		});
		return false;
	});
	
});