<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This file is part of the MailWizz EMA application.
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link http://www.mailwizz.com/
 * @copyright 2013-2014 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 * @since 1.3.3.1
 */
 
?>
	<div class="ui-full" id="content" style="background: none repeat scroll 0% 0% rgb(204, 204, 204);">
		Select Filter Type: &nbsp;&nbsp;&nbsp;
		<input id="using_domain_name" checked="true" type="radio" name="filterType">&nbsp;&nbsp;Domain
		<input id="using_ip_address" type="radio" name="filterType">&nbsp;&nbsp;IP Addresss
	</div>
		
	
		<?php $form = $this->beginWidget('CActiveForm', array(
    'id'=>'filterformDomain',
	 'action' => Yii::app()->createUrl('misc/filter-actions'),
	'htmlOptions'=>array('enctype'=>'multipart/form-data'),
    'enableAjaxValidation'=>false,
 
)); ?>
			<div id="content" class="ui-full white">
				<h2>Filter by Domain</h2>
			</div> 

			<div id="content" class="ui-full white">
			
					
					<p>
						<h2>Upload CSV to Export File</h2><input type="file" name="file" id="file" size="150">
					</p>
					<br>
					<p>
						<span style="width:21%;float:left;padding-left:2px;"></span>
						<input type="text" class="inputbox" placeholder="Insert URL(s)" name="site">
						<input type="text" placeholder="Start Date" id="fromdate" name="fromdate">
						<input type="text" placeholder="End Date" id="todate" name="todate">	
						<input type="hidden" id="return_url" value="<?php echo $_SERVER['REQUEST_URI'];?>">
						<select id="value_notin_domian" name="value_notin">
							<option value=" NOT ">NOT IN</option>
							<option selected="selected" value="">IN</option>
						</select>		
                        <input type="hidden" value="<?php echo Yii::app()->request->csrfToken;?>" 
      name="YII_CSRF_TOKEN" />			
						<input type="submit" id="domaincsv" value="Export" name="export_domains">
						<script type="text/javascript">$(function() {$( "#fromdate , #todate" ).datepicker({dateFormat: 'yy-mm-dd'});});</script>
					</p>
				</h2>
			</div>
			<div class="ui-clear"></div>

<?php $this->endWidget(); ?>


		<?php $form = $this->beginWidget('CActiveForm', array(
    'id'=>'filterformIps',
	 'action' => Yii::app()->createUrl('misc/filter-actions'),
	'htmlOptions'=>array('enctype'=>'multipart/form-data','style'=>'display: none;'),
    'enableAjaxValidation'=>false,
 
)); ?>
		
			<div id="content" class="ui-full white">
				<h2>Filter by IP Address</h2>
			</div> 

			<div id="content" class="ui-full white">
				<p>
						<h2>Upload CSV to Export File</h2></p>
                        <input type="file" name="file" id="file" size="150"></p>
					 <p>
						<span style="width:21%;float:left;padding-left:2px;"></span>
						<span>
							<input type="text" class="inputbox" placeholder="Insert IP Address(es)" name="ipsite">
							<input type="text" placeholder="Start Date" id="ipfromdate" name="ipfromdate">
							<input type="text" placeholder="End Date" id="iptodate" name="iptodate">        
							<select id="value_notin_ip" name="value_notin">
								<option value=" NOT ">NOT IN</option>
								<option selected="selected" value="">IN</option>
							</select>	
                            <input type="hidden" value="<?php echo Yii::app()->request->csrfToken;?>" 
      name="YII_CSRF_TOKEN" />
							<input type="hidden" id="return_url" value="<?php echo $_SERVER['REQUEST_URI'];?>">	
							<input type="submit" id="ipcsv" value="Export" name="export_ips">  
							<script type="text/javascript">$(function() {$( "#ipfromdate , #iptodate" ).datepicker({dateFormat: 'yy-mm-dd'});});</script>
						</span>             
					</p>
				
			</div>
			<div class="ui-clear"></div>

<?php $this->endWidget(); ?>