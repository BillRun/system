angular
		.module('BillrunApp')
		.controller('RgConversionsController', RgConversionsController);

function RgConversionsController(Database, Utils) {
	'use strict';
	var vm = this;

	angular.element('.active').removeClass('active');
	angular.element('.menu-item-rg_conversions').addClass('active');

	vm.edit_mode = false;
	vm.availableMccs = ['ISRAEL', 'ROAMING'];

	vm.newRgConversion = function () {
		vm.current_entity = {
			from_rg: "92",
			to_rg: "93",
			mcc: "ISRAEL"
		};
		vm.edit_mode = true;
	};

	vm.createRgConversion = function () {
		Database.createRgConversion(vm.current_entity).then(function (res) {
			vm.edit_mode = false;
			vm.init();
		});
	};
	
	vm.cancel = function () {
		vm.edit_mode = false;
	};

	vm.removeRgConversion = function (conversion) {
		var r = confirm("Are you sure you want to remove conversion of '" + conversion.mcc + "' from rating group '"
				+ conversion.from_rg + "' to '" + conversion.to_rg + "'?");
		if (r) {
			Database.removeRgConversion(conversion).then(function (res) {
				vm.init();
			});
		}
	};

	vm.init = function () {
		vm.initActiveConversions();
		vm.initConversionsHistory();
	};
	
	vm.initActiveConversions = function() {
		Database.getActiveRgConversionsDetails().then(function (res) {
			vm.rg_conversions = res.data.response;
		});
	};
	
	vm.initConversionsHistory = function() {
		Database.getRgConversionsLogDetails().then(function (res) {
			vm.rg_conversions_log = res.data.response;
			_.forEach(vm.rg_conversions_log, function (conversion) {
				conversion.time = moment.unix(conversion.time.sec).format('YYYY/MM/DD HH:mm:ss');
				conversion.mode = conversion.mode.toUpperCase();
			});
		});
	};
}