$(document).ready(function () {
	$(function () {
		$('#datetimepicker').datetimepicker({locale: 'en', format: 'DD/MM/YYYY HH:mm', });
	});
	//select the right TAB from url hashtag
	if(window.location.hash != "") {
		$('a[href="' + window.location.hash + '"]').click()
	}
	
	//Select to send checkboxes
	$('.enable-input').change(setInputByCheckboxState);
	function setInputByCheckboxState() {
		if ($(this).is(':checked')) {
			$(this).closest('span').next('input').prop('disabled', false);
			$(this).closest('span').next('select').prop('disabled', false);
			$(this).closest('span').next('textarea').prop('disabled', false);
			$(this).siblings('small').hide();
		} else {
			$(this).closest('span').next('input').val('');
			$(this).siblings('small').show();
			$(this).closest('span').next('input').prop('disabled', true);
			$(this).closest('span').next('select').prop('disabled', true);
			$(this).closest('span').next('textarea').prop('disabled', true);
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

