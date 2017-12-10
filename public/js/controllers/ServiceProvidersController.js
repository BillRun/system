angular.module('BillrunApp').controller('ServiceProvidersController', ServiceProvidersController);

function ServiceProvidersController(Database, $uibModal) {
	'use strict';
	var vm = this;

	angular.element('.active').removeClass('active');
	angular.element('.menu-item-service_providers').addClass('active');
	
	vm.edit_mode = false;

	vm.saveNew = function (redirect) {
		var params = {
			entity: vm.service_provider,
			coll: 'serviceproviders',
			type: 'new'
		};
		vm.err = {};
		Database.alreadyExistsServiceProvider({serviceProvider: vm.service_provider}).then(function (res) {
			if (res.data.alreadyExists) {
				alert('Entity with same name/code/id already exists');
				return;
			}
			
			Database.saveEntity(params).then(function (res) {
				if (redirect) {
					$window.location = '/admin/' + $routeParams.collection;
				}
				vm.init();
			}, function (err) {
				vm.err = err;
				console.log(err);
			});
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
	
	vm.removeServiceProvider = function (serviceProvider) {
		var serviceProvider_name = serviceProvider.name;
		var r = confirm("Are you sure you want to remove '" + serviceProvider_name + "' ?");
		if (r) {
			Database.removeServiceProvider({mongo_id: serviceProvider._id}).then(function (res) {
				vm.init();
			});
		}
	};
	
	vm.edit = function (serviceProvider) {
		vm.edit_mode = true;
		vm.current_entity = serviceProvider;
	};
	
	vm.cancel = function () {
		vm.edit_mode = false;
	};
	
	vm.save = function () {
		Database.alreadyExistsServiceProvider({serviceProvider: vm.current_entity}).then(function (res) {
			if (res.data.alreadyExists) {
				alert('Entity with same name/code/id already exists');
				return;
			}
			
			var params = {
				mongo_id: vm.current_entity._id,
				data: vm.current_entity
			};
			Database.saveServiceProvider(params).then(function (res) {
				if (res.data.status) {
					vm.init();
				}
			});
		});
	};
}