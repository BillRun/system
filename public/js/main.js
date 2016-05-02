var checkItems = false;
$(function () {
	$('#check_all').change(function () {
		$("tbody input[type='checkbox']").prop('checked', $(this).prop('checked'));
	});
	$("#close_and_new,#duplicate").click(function () {
		var items_checked = $('#data_table :checked');
		checkItems = true;
		if (items_checked.length) {
			if (active_collection === 'plans' || active_collection === 'rates' || active_collection === 'cards' || active_collection === 'subscribers')
				window.location = '/admin#/' + active_collection + '/' + $(this).data('type') + '/' + items_checked.eq(0).val();
			else {
				$(this).data('remote', '/admin/edit?coll=' + active_collection + '&id=' + items_checked.eq(0).val() + '&type=' + $(this).data('type'));
			}
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

	$("#ratePlanPopup").on('show.bs.modal', function (event) {
		var rate_id = $(event.relatedTarget).data('rate-id');
		var interconnect_key = $(event.relatedTarget).data('interconnect-key');
		var plan = $(event.relatedTarget).data('plan');
		var usage = $(event.relatedTarget).data('usage');
		$('#data-rates-tbody tr').remove();
		$('#rate-interconnect-info').remove();
		$.ajax({
			url: baseUrl + '/admin/getRate',
			type: "GET",
			data: {coll: 'rates', id: rate_id, interconnect_key: interconnect_key}
		}).done(function (res) {
			var entity = JSON.parse(res).entity;
			var interconnect_entity = JSON.parse(res).interconnect;
			var rate = (_.isUndefined(entity.rates[usage][plan]) ? entity.rates[usage]['BASE'].rate : entity.rates[usage][plan].rate);
			if (!_.isEmpty(interconnect_entity)) {
				var interconnect = (_.isUndefined(interconnect_entity.rates[usage][plan]) ?
						interconnect_entity.rates[usage]['BASE'].rate :
						interconnect_entity.rates[usage][plan].rate);
			}
			var $tbody = $("#data-rates-tbody");
			$('#ratePlanPopupLabel').text(entity.key + " - " + plan);
			_.forEach(rate, function (r) {
				var $row = $("<tr><td>" + r.interval + "</td><td>" + r.price + "</td><td>" + r.to + "</td></tr>");
				$tbody.append($row);
			});
			if (interconnect) {
				var $inter_table = $("<div id='rate-interconnect-info'><hr/><h3>Interconnect - " + interconnect_entity.key + "</h3><table class='table table-striped table-bordered data-rates-table'>\
					<thead>\
						<tr>\
							<th>Interval</th>\
							<th>Price</th>\
							<th>To</th>\
						</tr>\
					</thead>\
					<tbody id='data-interconnect-tbody'>\
					</tbody>\
				</table></div>");
				var $inter_tbody = $('#data-interconnect-tbody', $inter_table)
				_.forEach(interconnect, function (inter) {
					var $row = $("<tr><td>" + inter.interval + "</td><td>" + inter.price + "</td><td>" + inter.to + "</td></tr>");
					$inter_tbody.append($row);
				});
				$('.data-rates-table').after($inter_table);
			}
		});
	});

	$("#SourceRefPopup").on('show.bs.modal', function (event) {
		var line_id = $(event.relatedTarget).data('line');
		$.ajax({
			url: baseUrl + '/admin/getEntity',
			type: "GET",
			data: {coll: "lines", id: line_id}
		}).done(function (res) {
			var entity = JSON.parse(res).entity;
			var $modal_body = $(".modal-body");
			var html = "";
			_.forEach(entity.source_ref, function (v, k) {
				if (_.isObject(v))
					return;
				var key = _.capitalize(k.replace(/_/, ' '));
				html += "<br/><b>" + key + ":</b> " + v;
			});
			$modal_body.html(html);
		});
	});

	$("#chargingPlanPopup").on('show.bs.modal', function (event) {
		var plan_name = $(event.relatedTarget).data('charging-plan-name');
		$('#data-charging-plan-tbody tr').remove();
		$.ajax({
			url: baseUrl + '/admin/getEntity',
			type: "GET",
			data: {coll: 'plans', name: plan_name}
		}).done(function (res) {
			var entity = JSON.parse(res).entity;
			var include_types = _.keys(entity.include);
			var tbody = $("#data-charging-plan-tbody");
			var amount, pp_includes_name;
			_.forEach(include_types, function (include_type) {
				if (entity.include[include_type].length) {
					_.forEach(entity.include[include_type], function (k, i) {
						amount = (entity.include[include_type][i].usagev ?
								entity.include[include_type][i].usagev :
								(entity.include[include_type][i].cost ?
										entity.include[include_type][i].cost :
										entity.include[include_type][i].value));
						pp_includes_name = entity.include[include_type][i].pp_includes_name;
					});
				} else {
					amount = (entity.include[include_type].usagev ?
							entity.include[include_type].usagev :
							(entity.include[include_type][i].cost ?
									entity.include[include_type][i].cost :
									entity.include[include_type][i].value));
					pp_includes_name = entity.include[include_type].pp_includes_name;
				}
				var $row = $("<tr><td>" + include_type + "</td><td>" + amount + "</td><td>" + pp_includes_name + "</td></tr>");
				tbody.append($row);
			})
		});
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
	
	$("#saveVersionModal #saveVersion").click(function (event) {
		function saveVersion(collection, name) {
			$.ajax({
				url: "/api/saveversion",
				type: "POST",
				data: {collection: collection, name: name},
				error: function(err) { 
					alert('Error exporting, try again');
					$('#cancelSaveVersion').click();
				}       
			}).done(function (ret) {
				alert('Exported successfully');
				$('#cancelSaveVersion').click();
			});
		}
		
		var _collection = coll;
		var _name = $('#versionName').val();
		saveVersion(_collection, _name);
	});

	$("#loadVersionModal #loadVersion").click(function (event) {
		function loadVersion(collection, name, remove_new) {
			$.ajax({
				url: "/api/loadversion",
				type: "POST",
				data: {collection: collection, fileName: name, remove_new: remove_new},
				error: function(err) { 
					alert('Error importing, try again');
					$('#cancelLoadVersion').click();
				}       
			}).done(function (ret) {
				alert('Imported successfully');
				$('#cancelLoadVersion').click();
			});
		}
		
		var _collection = coll;
		var _fileName = $('#versions-select').val();
		var _removeNew = $('#remove-new-rates').is(':checked');
		loadVersion(_collection, _fileName, _removeNew);
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
						data: {prices: _prices, remove_non_existing_usage_types: remove_non_existing_usage_types, remove_non_existing_prefix: remove_non_existing_prefix}
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

	$('.multiselect:not(#usage,#billrun,#source,#plan,#extra_columns select)').multiselect({});

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

	$('select[id="plan"]').multiselect({
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
	$("select[name='manual_type[]']").on('change', function () {
		type_changed(this)
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
		$("#manual_filters").slideToggle();
		$("i", this).toggleClass("icon-chevron-down icon-chevron-up");
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
	} else {
		$('#menutab1').tab('show');
	}



}
);
function removeFilter(button) {
	$(button).siblings("input[name='manual_value[]']").val('');
	if ($(button).parent().siblings().length) {
		$(button).parent().remove();
	} else {
		$('.advanced-options').click();
	}
}

function type_changed(sel) {
	if ($(sel).val() == "date") {
		$(sel).siblings("input[name='manual_value[]']").hide();
		$(sel).siblings(".input-append.date").show();
		$(sel).parent().find("input[name='manual_value[]']").prop('disabled', function (_, val) {
			return !val;
		});
	} else {
		$(sel).siblings("input[name='manual_value[]']").show().prop('disabled', false);
		$(sel).siblings(".input-append.date").hide();
		$(sel).parent().find(".input-append.date>input[name='manual_value[]']").prop('disabled', true);
	}
}

function addFilter(button) {
	var original = $("#manual_filters>:last-child");
	$('select.multiselect', original).multiselect('destroy');
	var cloned = original.clone().appendTo('#manual_filters');
	cloned.find("select").each(function (i) {
		var cloned_sel = $(this);
		var original_sel = $("#manual_filters>div").eq(-2).find("select").eq(i);
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
}

function update_current(obj) {
	var item_checked = $(obj).next("input[type=checkbox],input[type=hidden]");
	checkItems = false;
	if (item_checked.length) {
		$(obj).data('remote', '/admin/edit?coll=' + active_collection + '&id=' + item_checked.eq(0).val() + '&type=' + $(obj).data('type'));
	}
}

function editBatchCards() {
	var batch_no = $('#batch_number').val();
	var selected = $('tbody input[type="checkbox"]:checked');
	var serial_numbers = [];
	$.each(selected, function (check, elm) {
		serial_numbers.push($(elm).parent().parent().parent().find('*[data-title="Serial Number"]').html());
	});
	if (batch_no) {
		window.location = '/admin#/batch/update/' + batch_no + "?cards=[" + serial_numbers.join(',') + "]";
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

	$('table').stickyTableHeaders({fixedOffset: $('.navbar-fixed-top')});
	if (window.location.pathname.match(/rates/gi)) {
		if ($('select[id="plan"]').length) {
			$('a[data-type="update"]').each(function (i, el) {
				var href = $(el).attr('href');
				href += '?plans=' + JSON.stringify($('select[id="plan"]').val());
				$(el).attr('href', href);
			});
		}
	}
	$('#data_table tbody tr').on('click', function () {
		$(this).addClass('highlight').siblings().removeClass('highlight');
	});
	$('body').on('hidden.bs.modal', '.modal', function () {
		$(this).removeData('bs.modal');
	});
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
	} else {
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

function detailFormatter(index, row) {
	$.ajax({
		method: "GET",
		url: baseUrl + "/admin/getLineDetailsFromArchive",
		data: {stamp: $('tr[data-index="' + index + '"]').data('stamp')}
	})
			.done(function (res) {
				res = JSON.parse(res);
				var lines = res.detailed;
				var $title, $thead;

				if (lines[0] && lines[0].usaget !== "balance") {
					var aggregated = res.aggregated;
					// aggregated
					$title = $("<strong>Breakdown By Balance</strong>");
					var $aggregated_table = $("<table class='table table-striped table-bordered table-no-more-tables table-hover'></table>");
					$thead = $("<thead><tr><th>#</th><th>Balance ID</th><th>Balance Name</th><th>Usage</th><th>Charge</th><th>Balance Before</th><th>Balance After</th><th>Unit</th></tr></thead>");
					$aggregated_table.append($thead).append('<tbody>');
					_.forEach(aggregated, function (aggregate, i) {
						var $tr = $("<tr></tr>");
						var idx = i + 1;
						var usagev = (aggregate.s_usagev || aggregate.s_usagev == 0) ? aggregate.s_usagev : "";
						var charge = (aggregate.s_price || aggregate.s_price == 0) ? aggregate.s_price.toFixed(6) : "";
						if (aggregate.s_unit && aggregate.s_unit.toLowerCase() !== "nis")
							charge = usagev;
						//var remote = '/admin/edit?coll=archive&id=' + line['_id']['$id'] + '&type=view';
						$tr.append("<td>" + idx + "</td>");
						$tr.append("<td>" + (aggregate._id.pp_includes_external_id ? aggregate._id.pp_includes_external_id : "") + "</td>");
						$tr.append("<td>" + (aggregate._id.pp_includes_name ? aggregate._id.pp_includes_name : "") + "</td>");
						$tr.append("<td>" + usagev + "</td>");
						$tr.append("<td>" + charge + "</td>");
						$tr.append("<td>" + (_.isNumber(aggregate.balance_before) ? aggregate.balance_before.toFixed(6) : "") + "</td>");
						$tr.append("<td>" + (_.isNumber(aggregate.balance_after) ? aggregate.balance_after.toFixed(6) : "") + "</td>");
						$tr.append("<td>" + aggregate.s_unit + "</td>");
						$aggregated_table.append($tr);
					});
					$('tr[data-index="' + index + '"]').next('tr.detail-view').find('td').append($title, "<br/>").append($aggregated_table);
				}

				// lines
				if (lines[0] && lines[0].usaget === "balance") {
					$title = $("<strong>Breakdown</strong>");
				} else {
					$title = $("<strong>Breakdown By Intervals</strong>");
				}
				var $table = $("<table class='table table-striped table-bordered table-no-more-tables table-hover'></table>");
				$thead = $("<tr><th>#</th><th>Balance ID</th><th>Balance Name</th>");
				if (lines[0] && lines[0].usaget !== "balance") {
					$thead.append("<th>API Name</th>");
				}
				$thead.append("<th>Usage</th><th>Charge</th><th>Balance Before</th><th>Balance After</th><th>Unit</th><th>Time</th></tr>");
				$("<thead></thead>").append($thead);
				$table.append($thead).append('<tbody>');
				_.forEach(lines, function (line, i) {
					var usagev = (line.usagev || line.usagev == 0) ? line.usagev : "";
					var charge = (line.aprice || line.aprice == 0) ? line.aprice.toFixed(6) : "";
					if (line.usage_unit && line.usage_unit.toLowerCase() !== "nis")
						charge = usagev;
					var $tr = $("<tr></tr>");
					var idx = i + 1;
					var remote = '/admin/edit?coll=archive&id=' + line['_id']['$id'] + '&type=update';
					$tr.append("<td><a href='#popupModal' data-remote='" + remote + "' data-type='view' data-toggle='modal' role='button' onclick='update_current(this);'>" + idx + "</a></td>");
					$tr.append("<td>" + (line.pp_includes_external_id ? line.pp_includes_external_id : "") + "</td>");
					$tr.append("<td>" + (line.pp_includes_name ? line.pp_includes_name : "") + "</td>");
					if (line.usaget === "data")
						$tr.append("<td>" + (line.record_type ? line.record_type : "") + "</td>");
					else if (line.usaget !== "balance")
						$tr.append("<td>" + (line.api_name ? line.api_name : "") + "</td>");
					$tr.append("<td>" + usagev + "</td>");
					$tr.append("<td>" + charge + "</td>");
					$tr.append("<td>" + (_.isNumber(line.balance_before) ? line.balance_before.toFixed(6) : "") + "</td>");
					$tr.append("<td>" + (_.isNumber(line.balance_after) ? line.balance_after.toFixed(6) : "") + "</td>");
					$tr.append("<td>" + (line.usage_unit ? line.usage_unit : "") + "</td>");
					$tr.append("<td>" + ((line.urt && line.urt.sec) ? moment(line.urt.sec * 1000).format('DD-MM-YYYY HH:mm:ss') : "") + "</td>");
					$table.append($tr);
				});
				if ($aggregated_table) {
					$aggregated_table.after("<br/>", $title, "<br/>", $table);
				} else {
					$('tr[data-index="' + index + '"]').next('tr.detail-view').find('td').append($title, "<br/>").append($table);
				}
			});
}