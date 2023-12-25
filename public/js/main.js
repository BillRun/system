$('body').on('hidden.bs.modal', '.modal', function () {
	$(this).removeData('bs.modal');
});
var checkItems = false;
$(function () {
	$("#close_and_new,#duplicate").click(function () {
		var items_checked = $('#data_table :checked');
		checkItems = true;
		if (items_checked.length) {
			$(this).data('remote', '/admin/edit?coll=' + active_collection + '&id=' + items_checked.eq(0).val() + '&type=' + $(this).data('type'));
		}
	});

	$("#remove").click(function () {
		var items_checked = $('#data_table :checked');
		checkItems = true;
		var output = $.map(items_checked, function (n, i) {
			return n.value;
		}).join(',');
		if (items_checked.length) {
			$(this).data('remote', '/admin/confirm?coll=' + active_collection + '&id=' + output + '&type=' + $(this).data('type'));
		}
	});

	$("#new").click(function () {
		$(this).data('remote', '/admin/edit?coll=' + active_collection + '&type=' + $(this).data('type'));
	});

	$("#popupModal,#confirmModal").on('show.bs.modal', function (event) {
		if (checkItems) {
			var items_checked = $('#data_table :checked');
			if (!items_checked.length || (items_checked.length != 1 && ($.inArray(coll, ['lines', 'users']) === -1 || $(this).attr('id') != 'confirmModal'))) {
				alert('Please check exactly one item from the list');
				$(this).removeData('modal');
				event.preventDefault();
			}
		}
	});

	function getInputFileContent(file, contentLoadedCB) {
		if (isAPIAvailable()) {
			var reader = new FileReader();
			reader.readAsText(file);
			reader.onload = function (event) {
				contentLoadedCB(event.target.result);
			};
		} else {
			alert("Your browser doesn't support parse csv on client side. Please use IE12+, Chrome or Firefox");
		}
	}

	function getCSVContent(file, csvContentParsedCB) {
		getInputFileContent(file, function (content) {
			var csv = content;
			csv = csv.replace(/^\s*$/g, "");
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
				if (row != 0 && data[row].length == headers.length) { // skip the first (header) and only take rows that hass all column of the headers
					retRow = {};
					for (var item in data[row]) {
						retRow[headers[item]] = data[row][item];
					}
					ret[_c++] = retRow;
				}
			}

			csvContentParsedCB(JSON.stringify(ret));
		});
	}

	$("#resetSubsModal #upload").click(function (event) {
		function resetLines(content) {
			$.ajax({
				url: "/api/resetlines",
				type: "POST",
				data: {sid: content}
			}).done(function (msg) {
				obj = JSON.parse(msg);
				if (obj.status == "1") { // success - print the file token
					$('#resetSubsModal #saveOutput').append('Successful upload. Results: ' + JSON.stringify(obj.input));
				} else {
					$('#resetSubsModal #saveOutput').append('Failed on upload. Reason as follow: ' + obj.desc);
				}
			});
			$("#resetSubsModal #file-upload").val('');
			$("#resetSubsModal #single-sub-input").val('');
		}

		var files = $("#resetSubsModal #file-upload").get(0).files;
		$('#resetSubsModal #saveOutput').html('');
		if (files.length == 0) {
			if ($("#resetSubsModal #single-sub-input").val()) {
				resetLines($("#resetSubsModal #single-sub-input").val());
			}
		} else {
			for (var i = 0; i < files.length; i++) {
				getInputFileContent(files[i], resetLines);
			}
		}
	});

	$("#recreateInvoicesModal #uploadAccounts").click(function (event) {
		function recreateInvoices(content) {
			$.ajax({
				url: "/api/recreateinvoices",
				type: "POST",
				data: {account_id: content}
			}).done(function (msg) {
				obj = JSON.parse(msg);
				$('#recreateInvoicesModal #saveOutputAccounts').append('Successful upload. Results: ' + JSON.stringify(obj));
			});
			$("#recreateInvoicesModal #file-upload-accounts").val('');
			$("#recreateInvoicesModal #single-sub-input-accounts").val('');
		}

		var files = $("#recreateInvoicesModal #file-upload-accounts").get(0).files;
		$('#recreateInvoicesModal #saveOutputAccounts').html('');
		if (files.length == 0) {
			if ($("#recreateInvoicesModal #single-sub-input-accounts").val()) {
				recreateInvoices($("#recreateInvoicesModal #single-sub-input-accounts").val());
			}
		} else {
			for (var i = 0; i < files.length; i++) {
				getInputFileContent(files[i], recreateInvoices);
			}
		}
	});

	$("#uploadModal #upload").click(function (event) {
		var files = $("#uploadModal #file-upload").get(0).files;
		$('#uploadModal #saveOutput').html('');
		for (var i = 0; i < files.length; i++) {
			getCSVContent(files[i], function (content) {
				$.ajax({
					url: '/api/bulkcredit',
					type: "POST",
					data: {operation: "credit", credits: content}
				}).done(function (msg) {
					obj = JSON.parse(msg);
					if (obj.status == "1") { // success - print the file token
						$('#uploadModal #saveOutput').append('Success to upload. File token: ' + obj.stamp);
					} else {
						$('#uploadModal #saveOutput').append('Failed to upload. Reason as follow: ' + obj.desc);
					}
				});
				$("#uploadModal #file-upload").val('');
			});
		}
	});

	/**
	 * @todo prevent duplicate code with credits import
	 */
	$("#importPricesModal #import").click(function (event) {
		if (isAPIAvailable()) {
			var remove_non_existing_usage_types = $("#remove_non_existing_usage_types").is(':checked') ? 1 : 0;
			var remove_non_existing_prefix = $("#remove_non_existing_prefix").is(':checked') ? 1 : 0;
			var allow_past_rates = $("#allow_past_rates").is(':checked') ? 1 : 0;
			var files = $("#importPricesModal #file-upload2").get(0).files; // FileList object
			if (files.length) {
				$(this).attr('disabled', 'disabled');
				var file = files[0];
				var reader = new FileReader();
				reader.readAsText(file);
				reader.onload = function (event) {
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

					var _prices = JSON.stringify(ret);
					$.ajax({
						url: '/api/importpriceslist',
						type: "POST",
						data: {
							prices: _prices,
							remove_non_existing_usage_types: remove_non_existing_usage_types, remove_non_existing_prefix: remove_non_existing_prefix,
							allow_past_rates: allow_past_rates
						}
					}).done(function (msg) {
						obj = JSON.parse(msg);
						var output;
						if (obj.status == "1") {
							output = 'Success.<br/>';
							var reasons = {"updated": "Updated", "future": "Rates that were not imported due to an existing future rate", "missing_category": "Rates that were not updated because they miss category", "old": "Inactive rates not imported"};
							$.each(obj.keys, function (key, value) {
								if (value.length) {
									output += eval("reasons." + key) + ": " + value.join() + "<br/>";
								}
							});
							$("#importPricesModal #file-upload2").val('');
							$('#importPricesModal #saveOutput2').html(output);
						} else {
							output = 'Failed to import: ' + obj.desc;
							if (obj.input) {
								output += '</br>Input was: ' + JSON.stringify(obj.input);
							}
							$('#importPricesModal #saveOutput2').html(output);
						}
						$('#import').removeAttr("disabled");
					});
				}
			}

		} else {
			alert("Your browser doesn't support parse csv on client side. Please use IE12+, Chrome or Firefox");
			return false;
		}

	});

	$("#uploadModal #file-upload").on('change', function (event) {

	});

	$('.multiselect:not(#usage,#billrun,#source,#extra_columns select)').multiselect({});

	$('#usage,#billrun,#source').multiselect({
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

	$('#search-criteria').submit(function () {
		if ($("#type").length && !$("#type :selected").length) {
			alert('You must choose at least one usage.');
			return false;
		}
	});
	
	$(".add-filter").on('click', function () {
		addFilter(this);
	});
	$(".remove-filter").on('click', function () {
		removeFilter(this);
	});
	$(".add-group-data").on('click', function () {
		addGroupData(this);
	});
	$(".remove-group-data").on('click', function () {
		removeGroupData(this);
	});
	$("select[name='manual_type[]']").on('change', function () {
		type_changed(this);
	});
	$('.date:not(.wholesale-date)').datetimepicker({
		format: 'YYYY-MM-DD HH:mm:ss',
		locale: 'he-il',
		showTodayButton: true,
		showClose: true
	});
	$('.wholesale-date').datetimepicker({
		format: 'YYYY-MM-DD HH:mm:ss',
		locale: 'he-il',
		showTodayButton: true,
		showClose: true,
		pickTime: false
	});

	$(".advanced-options").on('click', function () {
		var _container = $('.tab-content div.active');
		if (!_container.length) {
			$("#manual_filters").slideToggle();
		} else {
			$("#manual_filters", _container).slideToggle();
		}
		$("i", this).toggleClass("glyphicon-minus-sign glyphicon-plus-sign");
	});
	
	$(".groupData").on('click', function () {
		var _container = $('.tab-content div.active');
		if (!_container.length) {
			$("#group_by_filters").slideToggle();
			$("#groupBy").slideToggle();
		} else {
			$("#group_by_filters", _container).slideToggle();
			$("#groupBy", _container).slideToggle();

		}
		$("i", this).toggleClass("glyphicon-minus-sign glyphicon-plus-sign");
	});

	if ($.fn.stickyTableHeaders) {
		$('.wholesale-table').stickyTableHeaders({fixedOffset: $('.navbar-fixed-top')});
	}

	$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
		localStorage.setItem('lastTab', $(e.target).attr('id'));
	});

	var lastTab = localStorage.getItem('lastTab');
	if (lastTab) {
		$('#' + lastTab).tab('show');
	}
	else {
		$('#menutab1').tab('show');
	}



}
);

function addSort(row) {
	var active = $(row).find(".active").first();
	if(active.length <= 0) {
		return;
	}
	var value = $(active).text();
	var input = $(active).find("input").first();
	
	var label =  input.val();
	var option = $('#sort_by option[value=' + label + ']');
	
	// If already exists.
	if(option.length > 0) {
		return;
	}
	
	// Get timestamp.
	var timestamp = row.data('timestamp');
	
	// If it doesn't have a timestamp ignore.
	if(timestamp !== undefined){
		// Remove the option with this timestamp.
		$('#sort_by option[timestamp=' + timestamp + ']').remove();
	}
	
	// Update the timestamp.
	timestamp = Date.now();

	row.data('timestamp', timestamp);
	$('#sort_by').append("<option timestamp=\"" + timestamp + "\" value=\"" + label + "\">" + value + "</option>");
	$('#sort_by').multiselect( 'rebuild' );
}

function removeSort(row) {
	var active = $(row).find(".active").first();
	if(active.length <= 0) {
		return;
	}
	var label = $(active).find("input").first().val();
	$('#sort_by option[value=' + label + ']').remove();
	$('#sort_by').multiselect( 'rebuild' );
}

function removeFilter(button) {
	$(button).siblings("input[name='manual_value[]']").val('');
	var row = $(button).parent();
	if (row.siblings().length) {
		removeSort(row);
		row.remove();
	}
	else {
		$('.advanced-options').click();
	}
}

function removeGroupData(button) {
	var row = $(button).parent();
	if (row.siblings().length) {
		removeSort(row);
		row.remove();
	}
	else {
		$('#groupData').click();
	}
}

function type_changed(sel) {
	if ($(sel).val() == "date") {
		$(sel).siblings("input[name='manual_value[]']").hide();
		$(sel).siblings(".input-append.date").show();
		$(sel).parent().find("input[name='manual_value[]']").prop('disabled', function (_, val) {
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
	var _container = $('.tab-content div.active');
	var _containerId = '';
	if (_container.length) {
		_containerId = '#' + _container.attr('id') + " ";
	}
	var original = $(_containerId + "#manual_filters .filters > *:last-child");
	$('select.multiselect', original).multiselect('destroy');
	var cloned = original.clone().appendTo(_containerId + '#manual_filters  .filters');
	cloned.find("select").each(function (i) {
		var cloned_sel = $(this);
		var original_sel = $(_containerId + "#manual_filters>div").eq(-2).find("select").eq(i);
		cloned_sel.val(original_sel.val());
	});
	$('.date', cloned).datetimepicker({
		format: 'YYYY-MM-DD HH:mm:ss',
		locale: 'en-gb',
		showTodayButton: true,
		showClose: true
	});
	$(".remove-filter", cloned).on('click', function () {
		removeFilter(this);
	});
	$(".add-filter", cloned).on('click', function () {
		addFilter(this);
	});
	$("select[name='manual_type[]']", cloned).on('change', function () {
		type_changed(this);
	});
	
	$('.multiselect', original).multiselect({});
	$('.multiselect', cloned).multiselect({});
	
	$('.multiselect', cloned).on('change', function() {
		addSort(cloned);
	});
}

function addGroupData(button) {
	var _container = $('.tab-content div.active');
	var _containerId = '';
	if (_container.length) {
		_containerId = '#' + _container.attr('id') + " ";
	}
	var original = $(_containerId + "#group_by_filters>:last-child");
	$('select.multiselect', original).multiselect('destroy');
	var cloned = original.clone().appendTo(_containerId + '#group_by_filters');
	cloned.find("select").each(function (i) {
		var cloned_sel = $(this);
		var original_sel = $(_containerId + "#group_by_filters>div").eq(-2).find("select").eq(i);
		cloned_sel.val(original_sel.val());
	});
	$(".remove-group-data", cloned).on('click', function () {
		removeGroupData(this);
	});
	$(".add-group-data", cloned).on('click', function () {
		addGroupData(this);
	});
	$("select[name='manual_type[]']", cloned).on('change', function () {
		type_changed(this);
	});
		
	$('.multiselect', original).multiselect({});
	$('.multiselect', cloned).multiselect({});
	
	$('.multiselect', cloned).on('change', function() {
		addSort(cloned);
	});
	
}

function update_current(obj) {
	var item_checked = $(obj).next("input[type=checkbox],input[type=hidden]");
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
$(document).ready(function () {
	$(".config input[type='checkbox']").bootstrapSwitch();
});

/**
 * Open wholesale popup
 * @param {type} obj
 * @param {type} direction
 * @returns {undefined}
 */
function openPopup(obj, direction) {
	obj = $(obj);
	var popup_group_field = $("input[name='popup_group_field']").val();
	var direction = obj.closest('table').data('type');
	if (popup_group_field == 'carrier') {
		var from_day, to_day;
		from_day = to_day = obj.data('value');
	}
	else {
		var from_day = $('input[name="init_from_day"]').val();
		var to_day = $('input[name="init_to_day"]').val();
		var carrier = obj.siblings('input.carrier').val();
	}
	obj.data('remote', '/admin/wholesaleajax?direction=' + direction + '&group_by=' + popup_group_field + '&from_day=' + from_day + '&to_day=' + to_day + (carrier ? '&carrier=' + encodeURIComponent(carrier) : ''));
}

function exportRates() {
	var show_prefix = $('#showprefix').is(':checked');
	window.location.href = "/admin/exportrates?show_prefix=" + show_prefix;
}
