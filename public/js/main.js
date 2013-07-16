$('.date').datetimepicker({
	format: 'yyyy-MM-dd hh:mm:ss',
});

$('body').on('hidden', '.modal', function () {
	$(this).removeData('modal');
});

$(function(){
	$("#update_current,#close_and_new,#duplicate").click(function(){
		//		$("#cancelRemove").click();
		var items_checked = $('#data_table :checked');
		if (items_checked.length) {
			$(this).data('remote', edit_url_prefix + items_checked.eq(0).val() + '&type=' + $(this).data('type'));
		}
	});

	$("#remove").click(function(){
		var items_checked = $('#data_table :checked');
		if (items_checked.length) {
			$(this).data('remote', confirm_url_prefix + items_checked.eq(0).val() + '&type=' + $(this).data('type'));
		}
	});

	$("#popupModal, #confirmModal").on('show', function(event){
		var items_checked = $('#data_table :checked');
		if (items_checked.length!=1) {
			alert('Please check exactly one item from the list');
			$(this).removeData('modal');
			event.preventDefault();
		}
	});
});