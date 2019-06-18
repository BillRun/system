var utest_JS = {
	init: function () {
		this.registerOnPageLoad();
		this.registerCollapseContainers();
		this.registerTestFormSubmit();
		this.applyHierarchicalFields();
	},
	registerOnPageLoad: function () {
		//init datepicker fields
		$('.datetimepicker').datetimepicker({locale: 'en', format: 'DD/MM/YYYY HH:mm', });
		//Init hierarchical firleds
		$("input.hierarchical, input.hierarchical").on("change", this.applyHierarchicalFields);
		$("input.hierarchical:checked, input.hierarchical:checked").trigger("change");
		//init Copy to clipard plugin
		new Clipboard('.copy-to-clipboard-btn');
		$('.enable-input').change(this.selectSendCheckboxes);
		$('.enable-input').trigger('change');
		//select the right TAB from url hashtag and Fill data if exist
		if (window.location.hash != "") {
			var tetsId = window.location.hash;
			//show the last test form tab
			$('.nav-tabs a[href="' + tetsId + '"]').tab('show');
			//fill the form with saved submited values
			if (localStorage.getItem(tetsId)) {
				$(tetsId).find('form').autofill($.parseJSON(localStorage.getItem(tetsId)));
			}
			//remove all form saved values
			Object.keys(localStorage).forEach(function (key) {
				if (/^#utest_/.test(key)) {
					localStorage.removeItem(key);
				}
			});
		} else {
			//show first test tab
			$('.nav-tabs a:first').tab('show');
		}
	},
	registerTestFormSubmit: function () {
		$("form").submit(function (event) {
			var key = '#' + $(this).find('input[name="type"]').val();
			//create key->value array of form data
			var formSerializeArray = $(this).serializeArray();
			var formData = {};
			$.map(formSerializeArray, function (n, i) {
				formData[n['name']] = n['value'];
			});
			localStorage.setItem(key, JSON.stringify(formData));
		});
	},
	applyHierarchicalFields: function () {
		var el = $(this);
		var id = el.data('require-pair');
		var className = ".hierarchical-dependency." + el.prop('name');
		$(className).hide();
		$(className + ' input').val('');
		$(id).show();
	},
	registerCollapseContainers: function () {
		$("div.up[data-toggle='collapse']").prepend('<a href="#"><span class="glyphicon glyphicon-expand"></span></a> ');
		$("div.down[data-toggle='collapse']").prepend('<a href="#"><span class="glyphicon glyphicon-collapse-down"></span></a> ');
		$('.collapse').on('show.bs.collapse', function () {
			$(this).parent().find(".glyphicon.glyphicon-expand").removeClass("glyphicon-expand").addClass("glyphicon-collapse-down");
		}).on('hide.bs.collapse', function () {
			$(this).parent().find(".glyphicon.glyphicon-collapse-down").removeClass("glyphicon-collapse-down").addClass("glyphicon-expand");
		});
	},
	selectSendCheckboxes: function () {
		var sendingValue = null;
		if ($(this).closest('span').next('input').length) {
			sendingValue = $(this).closest('span').next('input');
		} else if ($(this).closest('span').next('select').length) {
			sendingValue = $(this).closest('span').next('select');
		} else if ($(this).closest('span').next('textarea').length) {
			sendingValue = $(this).closest('span').next('textarea');
		} else if ($(this).closest('div').next('div.datetimepicker').find('input').length) {
			sendingValue = $(this).closest('div').next('div.datetimepicker').find('input');
		}

		if ($(this).is(':checked')) {
			sendingValue.prop('disabled', false);
			$(this).siblings('small').hide();
			var defaultValue = sendingValue.data('default')
			if (typeof defaultValue !== "undefined") {
				sendingValue.val(defaultValue);
			}
		} else {
			sendingValue.val('');
			sendingValue.prop('disabled', true);
			$(this).siblings('small').show();
		}
	}
};


$(document).ready(function () {
	utest_JS.init();
});
