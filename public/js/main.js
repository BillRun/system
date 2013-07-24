$('.date').datetimepicker({
	format: 'yyyy-MM-dd hh:mm:ss',
});

$('body').on('hidden', '.modal', function () {
	$(this).removeData('modal');
});

$(function(){
	$("#update_current,#close_and_new,#duplicate").click(function(){
		var items_checked = $('#data_table :checked');
		if (items_checked.length) {
			$(this).data('remote', edit_url_prefix + items_checked.eq(0).val() + '&type=' + $(this).data('type'));
		}
	});

	$("#remove").click(function(){
		var items_checked = $('#data_table :checked');
		var output = $.map(items_checked, function(n, i){
			return n.value;
		}).join(',');
		if (items_checked.length) {
			$(this).data('remote', confirm_url_prefix + output + '&type=' + $(this).data('type'));
		}
	});

	$("#popupModal, #confirmModal").on('show', function(event){
		var items_checked = $('#data_table :checked');
		if (!items_checked.length || (items_checked.length!=1 && (coll!='lines' || $(this).attr('id')!='confirmModal'))) {
			alert('Please check exactly one item from the list');
			$(this).removeData('modal');
			event.preventDefault();
		}
	});
});