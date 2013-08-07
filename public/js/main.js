$('body').on('hidden', '.modal', function() {
	$(this).removeData('modal');
});

$(function() {
	$("#update_current,#close_and_new,#duplicate").click(function() {
		var items_checked = $('#data_table :checked');
		if (items_checked.length) {
			$(this).data('remote', edit_url_prefix + items_checked.eq(0).val() + '&type=' + $(this).data('type'));
		}
	});

	$("#remove").click(function() {
		var items_checked = $('#data_table :checked');
		var output = $.map(items_checked, function(n, i) {
			return n.value;
		}).join(',');
		if (items_checked.length) {
			$(this).data('remote', confirm_url_prefix + output + '&type=' + $(this).data('type'));
		}
	});

	$("#popupModal, #confirmModal").on('show', function(event) {
		var items_checked = $('#data_table :checked');
		if (!items_checked.length || (items_checked.length != 1 && (coll != 'lines' || $(this).attr('id') != 'confirmModal'))) {
			alert('Please check exactly one item from the list');
			$(this).removeData('modal');
			event.preventDefault();
		}
	});

	$('.multiselect').multiselect({
		//        includeSelectAllOption: true,
		selectAllValue: 'all',
		selectedClass: null
	});

	$('#search-criteria').submit(function() {
		if ($("#type").length && !$("#type :selected").length) {
			alert('You must choose at least one usage.');
			return false;
		}
	});
	$(".add-filter").on('click', function() {
		addFilter(this);
	});
	$(".remove-filter").on('click', function() {
		removeFilter(this);
	});
	$("select[name='manual_type[]']").on('change', function() {
		type_changed(this)
	});
	$('.date').datetimepicker({
		format: 'yyyy-MM-dd hh:mm:ss',
	});
	$(".advanced-options").on('click',function() {
		$("#manual_filters").slideToggle();
		$("i",this).toggleClass("icon-chevron-down icon-chevron-up");
	});
});

function removeFilter(button) {
	$(button).parent().remove();
}

function type_changed(sel) {
	if ($(sel).val() == "date") {
		$(sel).siblings("input[name='manual_value[]']").hide();
		$(sel).siblings(".input-append.date").show();
		$(sel).parent().find("input[name='manual_value[]']").prop('disabled', function(_, val) {
			return !val;
		});
	}
	else {
		$(sel).siblings("input[name='manual_value[]']").show().prop('disabled', false);
		$(sel).siblings(".input-append.date").hide();
		$(sel).parent().find(".input-append.date>input[name='manual_value[]']").prop('disabled', true);
	}
}

function addFilter(button) {
	var cloned = $("#manual_filters>:last-child").clone().appendTo('#manual_filters');
	cloned.find("select").each(function(i) {
		var cloned_sel = $(this);
		var original_sel = $("#manual_filters>div").eq(-2).find("select").eq(i);
		cloned_sel.val(original_sel.val());
	});
	$('.date', cloned).datetimepicker({
		format: 'yyyy-MM-dd hh:mm:ss',
	});
	$(".remove-filter", cloned).on('click', function() {
		removeFilter(this);
	});
	$(".add-filter", cloned).on('click', function() {
		addFilter(this);
	});
	$("select[name='manual_type[]']", cloned).on('change', function() {
		type_changed(this);
	});
}