$('.date').datetimepicker({
	format: 'yyyy-MM-dd hh:mm:ss',
});

$('body').on('hidden', '.modal', function () {
  $(this).removeData('modal');
});
