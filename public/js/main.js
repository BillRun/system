$('.date').datetimepicker({
	format: 'yyyy-MM-dd hh:mm:ss',
});

$('body').on('hidden', '.modal', function () {
	$(this).removeData('modal');
});

$("#update_current,#close_and_new,#duplicate").click(function(){
	var items_checked = $('#data_table :checked');
	if (items_checked.length) {
		$(this).data('remote', url_prefix + items_checked.eq(0).val() + '&type=' + $(this).data('type'));
	}
});

$("#popupModal").on('show', function(event){
	var items_checked = $('#data_table :checked');
	if (items_checked.length!=1) {
		alert('Please check exactly one item from the list');
		event.preventDefault();
		$(this).removeData('modal');
	}
});