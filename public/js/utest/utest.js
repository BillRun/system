$(document).ready(function () {
	$(function () {
		$('.datetimepicker').datetimepicker({locale: 'en', format: 'DD/MM/YYYY HH:mm', });
	});
	
	//init Copy to clipard plugin
	new Clipboard('.copy-to-clipboard-btn');

	//select the right TAB from url hashtag
	if(window.location.hash != "") {
		$('a[href="' + window.location.hash + '"]').click()
	}
	
	//Select to send checkboxes
	$('.enable-input').change(setInputByCheckboxState);
	function setInputByCheckboxState() {
		var sendingValue = null;
		if($(this).closest('span').next('input').length){
			sendingValue = $(this).closest('span').next('input');
		} else if ($(this).closest('span').next('select').length){
			sendingValue = $(this).closest('span').next('select');
		} else if($(this).closest('span').next('textarea').length){
			sendingValue = $(this).closest('span').next('textarea');
		} else if($(this).closest('div').next('div.date').find('input').length){
			sendingValue = $(this).closest('div').next('div.date').find('input');
		}
		
		if ($(this).is(':checked')) {
			sendingValue.prop('disabled', false);
			$(this).siblings('small').hide();
		} else {
			sendingValue.val('');
			sendingValue.prop('disabled', true);
			$(this).siblings('small').show();
		}
	}
	$('.enable-input').trigger('change');
	
	$("div.up[data-toggle='collapse']").prepend('<a href="#"><span class="glyphicon glyphicon-expand"></span></a> ');
	$("div.down[data-toggle='collapse']").prepend('<a href="#"><span class="glyphicon glyphicon-collapse-down"></span></a> ');
	$('.collapse').on('show.bs.collapse', function(){
		$(this).parent().find(".glyphicon.glyphicon-expand").removeClass("glyphicon-expand").addClass("glyphicon-collapse-down");
	}).on('hide.bs.collapse', function(){
		$(this).parent().find(".glyphicon.glyphicon-collapse-down").removeClass("glyphicon-collapse-down").addClass("glyphicon-expand");
	});
	
});

