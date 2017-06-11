angular
		.module('BillrunApp')
		.controller('RgConversionsController', RgConversionsController);

function RgConversionsController(Database, Utils) {
	'use strict';
	var vm = this;

	angular.element('.active').removeClass('active');
	angular.element('.menu-item-rg_conversions').addClass('active');

//	vm.edit_mode = false;
//	vm.newent = false;

//	vm.newBandwidthCap = function () {
//		vm.current_entity = {
//			cap_name: "",
//			service: "",
//			speed: 0
//		};
//		vm.newent = true;
//		vm.edit_mode = true;
//	};
	vm.save = function () {
		Database.saveBandwidthCap({data: vm.current_entity, newent: vm.newent}).then(function (res) {
			if (res.data.status) {
				vm.newent = false;
				vm.edit_mode = false;
				vm.bandwidthCaps[vm.current_entity.cap_name] = res.data.data;
			}
		});
	};
//	vm.cancel = function () {
//		vm.edit_mode = false;
//		vm.newent = false;
//	};
//	vm.edit = function (cap_name) {
//		vm.edit_mode = true;
//		vm.newent = false;
//		vm.current_entity = vm.bandwidthCaps[cap_name];
//		vm.current_entity.cap_name = cap_name;
//	};

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