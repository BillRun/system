$('body').on('hidden', '.modal', function() {
	$(this).removeData('modal');
});
var checkItems = false;
$(function() {
	$("#close_and_new,#duplicate").click(function() {
		var items_checked = $('#data_table :checked');
		checkItems = true;
		if (items_checked.length) {
			$(this).data('remote', '/admin/edit?coll=' + active_collection + '&id=' + items_checked.eq(0).val() + '&type=' + $(this).data('type'));
		}
	});

	$("#remove").click(function() {
		var items_checked = $('#data_table :checked');
		checkItems = true;
		var output = $.map(items_checked, function(n, i) {
			return n.value;
		}).join(',');
		if (items_checked.length) {
			$(this).data('remote', '/admin/confirm?coll=' + active_collection + '&id=' + output + '&type=' + $(this).data('type'));
		}
	});

	$("#popupModal,#confirmModal").on('show', function(event) {
		if (checkItems) {
			var items_checked = $('#data_table :checked');
			if (!items_checked.length || (items_checked.length != 1 && (coll != 'lines' || $(this).attr('id') != 'confirmModal'))) {
				alert('Please check exactly one item from the list');
				$(this).removeData('modal');
				event.preventDefault();
			}
		}
	});

	$("#uploadModal #upload").click(function(event) {
		if (isAPIAvailable()) {
			var files = $("#uploadModal #file-upload").get(0).files; // FileList object
			var file = files[0];
			var reader = new FileReader();
			reader.readAsText(file);
			reader.onload = function(event) {
				var csv = event.target.result;
				var data = $.csv.toArrays(csv);
				var header_line = data[0];
				var headers = [];
				var ret = [];
				for (var item in header_line) {
					headers[item] = data[0][item];
				}
				var _c = 0;
				var retRow;
				for (var row in data) {
					if (row != 0) { // skip the first (header)
						retRow = {};
						for (var item in data[row]) {
							retRow[headers[item]] = data[row][item];
						}
						ret[_c++] = retRow;
					}
				}

				var _credits = JSON.stringify(ret);
				$.ajax({
					url: '/api/bulkcredit',
					type: "POST",
					data: {operation: "credit", credits: _credits}
				}).done(function(msg) {
					obj = JSON.parse(msg);
					if (obj.status == "1") { // success - print the file token
						$('#uploadModal #saveOutput').html('Success to upload. File token: ' + obj.stamp);
					} else {
						$('#uploadModal #saveOutput').html('Failed to upload. Reason as follow: ' + obj.desc);
					}
				});
				$("#uploadModal #file-upload").val('');
			}

		} else {
			alert("Your browser doesn't support parse csv on client side. Please use IE12+, Chrome or Firefox");
			return false;
		}

	});

	$("#uploadModal #file-upload").on('change', function(event) {

	});



	$('#usage,#billrun').multiselect({
		selectAllValue: 'all',
		selectedClass: null
	});

	$('#extra_columns select').multiselect({
		maxHeight: 250,
		enableFiltering: true,
		enableCaseInsensitiveFiltering: true,
		includeSelectAllOption: true,
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
	$(".advanced-options").on('click', function() {
		$("#manual_filters").slideToggle();
		$("i", this).toggleClass("icon-chevron-down icon-chevron-up");
	});
});

function removeFilter(button) {
	$(button).siblings("input[name='manual_value[]']").val('');
	if ($(button).parent().siblings().length) {
		$(button).parent().remove();
	}
	else {
		$('.advanced-options').click();
	}
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

function update_current(obj) {
	var item_checked = $(obj).next("input[type=checkbox]");
	checkItems = false;
	if (item_checked.length) {
		$(obj).data('remote', '/admin/edit?coll=' + active_collection + '&id=' + item_checked.eq(0).val() + '&type=' + $(obj).data('type'));
	}
}

function isAPIAvailable() {
	// Check for the various File API support.
	if (window.File && window.FileReader && window.FileList && window.Blob) {
		// Great success! All the File APIs are supported.
		return true;
	} else {
		// source: File API availability - http://caniuse.com/#feat=fileapi
		// source: <output> availability - http://html5doctor.com/the-output-element/
		document.writeln('The HTML5 APIs used in this form are only available in the following browsers:<br />');
		// 6.0 File API & 13.0 <output>
		document.writeln(' - Google Chrome: 13.0 or later<br />');
		// 3.6 File API & 6.0 <output>
		document.writeln(' - Mozilla Firefox: 6.0 or later<br />');
		// 10.0 File API & 10.0 <output>
		document.writeln(' - Internet Explorer: Not supported (partial support expected in 10.0)<br />');
		// ? File API & 5.1 <output>
		document.writeln(' - Safari: Not supported<br />');
		// ? File API & 9.2 <output>
		document.writeln(' - Opera: Not supported');
		return false;
	}
}