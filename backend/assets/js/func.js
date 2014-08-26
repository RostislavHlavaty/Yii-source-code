function checkDate(fromdateid,todateid){
	var startDate = new Date($('#'+fromdateid+'').val());
	var endDate   = new Date($('#'+todateid+'').val());
	if ((startDate <= endDate) || endDate=='Invalid Date'){
		return true;		
	}else{
		return false;		
	}
}

$(document).ready(function(){
	jQuery('#using_domain_name').click(function(){
		jQuery('#filterformDomain').show();	
		jQuery('#filterformIps').hide();		
	});	
	jQuery('#using_ip_address').click(function(){
		jQuery('#filterformDomain').hide();
		jQuery('#filterformIps').show();				
	});	
});

$(document).ready(function(){
	jQuery('#fromdate').change(function(){
		if(!checkDate('fromdate','todate')){			
			$('#fromdate').val('');
			alert('From date should be less than to date!');			
			return false;
		}
	});	
	jQuery('#todate').change(function(){
		if(!checkDate('fromdate','todate')){
			if($('#fromdate').val()!=''){
				$('#todate').val('');
				alert('To date should be greater than From date!');
			}
			return false;
		}
	});	
	jQuery('#ipfromdate').change(function(){
		if(!checkDate('ipfromdate','iptodate')){
			$('#ipfromdate').val('');
			alert('From date should be less than to date!');
			return false;
		}
	});	
	jQuery('#iptodate').change(function(){
		if(!checkDate('ipfromdate','iptodate')){
			if($('#ipfromdate').val()!=''){
				$('#iptodate').val('');
				alert('To date should be greater than From date!');
			}
			return false;
		}
	});	
});

$(document).ready(function(){
	jQuery('#filterformDomain').submit(function(){
	   var error       = 0;
		var msg         = '';
		var site         = $("input[name='site']").val();
		var fromdate     = $("input[name='fromdate']").val();
		var todate       = $("input[name='todate']").val();
		var value_notin  = $("#value_notin_domian").val();
		var return_url   = $("#return_url").val();

		if(site==""){
			error = 1;
			msg  +='+ Please enter a domain in comma seperated format.\n';
		}
		if(fromdate==""){
			error = 1;
			msg   +='+ Please enter a from date.\n';
		}
		if(todate==""){
			error = 1;
			msg   +='+ Please enter a to date.';
		}
		if(error){
			alert(msg);
			return false;
		}
		
		return true;
	});	
});

$(document).ready(function(){
	jQuery('#filterformIps').submit(function(){
	var error       = 0;
	var msg         = '';
	var site        = $("input[name='ipsite']").val();
	var fromdate    = $("input[name='ipfromdate']").val();
	var todate      = $("input[name='iptodate']").val();
	var value_notin = $("#value_notin_ip").val();
	var return_url  = $("#return_url").val();

	if(site==""){
		error = 1;
		msg  +='+ Please enter a IP address in comma seperated format.\n';
	}
	if(fromdate==""){
		error = 1;
		msg   +='+ Please enter a from date.\n';
	}
	if(todate==""){
		error = 1;
		msg   +='+ Please enter a to date.';
	}
	if(error){
		alert(msg);
		return false;
	}
	
	return true;

	});	
});
