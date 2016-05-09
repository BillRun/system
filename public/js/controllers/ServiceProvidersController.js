angular.module('BillrunApp').controller('ServiceProvidersController', ServiceProvidersController);

function ServiceProvidersController(Database, $uibModal) {
	'use strict';
	var vm = this;

	angular.element('.active').removeClass('active');
	angular.element('.menu-item-service_providers').addClass('active');

	vm.saveNew = function (redirect) {
		var params = {
			entity: vm.service_provider,
			coll: 'serviceproviders',
			type: 'new'
		};
		vm.err = {};
		Database.saveEntity(params).then(function (res) {
			if (redirect) {
				$window.location = '/admin/' + $routeParams.collection;
			}
			vm.init();
		}, function (err) {
			vm.err = err;
			console.log(err);
		});
	};
	vm.inEdit = function (id) {
		return _.includes(vm.editing, id);
	};
	vm.editServiceProviders = function () {
		_.forEach(vm.selectedServiceProviders, function (val, key) {
			if (val.selected)
				vm.editing.push(key);
		});
		vm.edit_mode = true;
	};
	vm.cancelEdit = function () {
		vm.edit_mode = false;
		vm.editing = [];
	};
	vm.removeServiceProviders = function () {
		Database.removeEntity({
			coll: 'service_providers',
			ids: _.keys(vm.selectedServiceProviders)
		}).then(function () {
			_.forEach(_.keys(vm.selectedServiceProviders), function (spid) {
				console.log(spid);
				//vm.serviceProviders = _.without(vm.serviceProviders, service_provider);
			});
		});
	};
	vm.init = function () {
		vm.new = {};
		vm.newMode = false;
		vm.saving = false;
		vm.service_provider = {name: "", code: "", id: ""};
		vm.inserted = {
			name: '',
			id: '',
			code: ''
		};
		vm.selectedServiceProviders = {};
		vm.edit_mode = false;
		vm.editing = [];
		Database.getAvailableServiceProviders({
			full_objects: true
		}).then(function (res) {
			vm.serviceProviders = res.data;
		});
	};
}