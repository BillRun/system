angular
		.module('BillrunApp')
		.controller('PrepaidIncludesController', PrepaidIncludesController);

function PrepaidIncludesController(Database, Utils, $http, $timeout, $rootScope) {
	'use strict';
	var vm = this;
	vm.edit_mode = false;
	vm.newent = false;

	angular.element('.active').removeClass('active');
	angular.element('.menu-item-pp_includes').addClass('active');

	vm.newPPInclude = function () {
		vm.edit_mode = true;
		vm.newent = true;
		vm.current_entity = {
			name: "",
			id: undefined,
			charging_by: "",
			charging_by_usaget: "",
			priority: 0,
			from: new Date(),
			to: moment().add(100, 'years')
		};
	};

	vm.edit = function (external_id) {
		$rootScope.spinner++;
		vm.current_entity = _.find(vm.pp_includes, function (e) {
			return e.external_id === external_id;
		});
//    vm.allowed_in = {};
//    _.forEach(vm.current_entity.allowed_in, function (a, p) {
//      vm.allowed_in[p] = [];
//      _.forEach(a, function (r) {
//        vm.allowed_in[p].push({key: r, ticked: true});
//      });
//    });
		vm.edit_mode = true;
		vm.newent = false;
		$timeout(function () {
			$('.multiselect').multiselect({
				maxHeight: 250,
				enableFiltering: true,
				enableCaseInsensitiveFiltering: true,
				includeSelectAllOption: true,
				selectAllValue: 'all',
				selectedClass: null
			});
			$rootScope.spinner--;
		}, 0);
	};

	vm.cancel = function () {
		vm.edit_mode = false;
	};
	vm.save = function () {
		$http.post(baseUrl + '/admin/savePPIncludes', {data: vm.current_entity, new_entity: vm.newent}).then(function (res) {
			if (vm.newent)
				vm.init();
			vm.edit_mode = false;
		});
	};

	vm.addAllowedPlan = function () {
		if (!vm.selected_allowed_plan)
			return;
		if (_.isUndefined(vm.current_entity.allowed_in))
			vm.current_entity.allowed_in = {};
		vm.current_entity.allowed_in[vm.selected_allowed_plan] = {};
		$rootScope.spinner++;
		$timeout(function () {
			$('#' + vm.selected_allowed_plan + '-select').multiselect({
				maxHeight: 250,
				enableFiltering: true,
				enableCaseInsensitiveFiltering: true,
				includeSelectAllOption: true,
				selectAllValue: 'all',
				selectedClass: null
			});
			$rootScope.spinner--;
			vm.selected_allowed_plan = "";
		}, 0);
	};

	vm.init = function () {
		vm.availableChargingBy = [
			"total_cost",
			"cost",
			"usagev"
		];
		vm.availableChargingByType = [
			"total_cost",
			"call",
			"data",
			"sms"
		];
		Database.getAvailablePlans("customer", true).then(function (res) {
			vm.availablePlans = res.data;
		});
		Database.getAvailableRates().then(function (res) {
			vm.availableRates = res.data;
		});
		Database.getAvailablePPIncludes({full_objects: true}).then(function (res) {
			vm.pp_includes = res.data.ppincludes;
			vm.authorized_write = res.data.authorized_write;
			var format = Utils.getDateFormat() + " HH:MM:SS";
			_.forEach(vm.pp_includes, function (pp_include) {
				pp_include.from = moment(pp_include.from.sec * 1000).format(format.toUpperCase());
				pp_include.to = moment(pp_include.to.sec * 1000).format(format.toUpperCase());
			});
		});
	};
}