angular
  .module('BillrunApp')
  .controller('ServiceProvidersController', ServiceProvidersController);

function ServiceProvidersController(Database, $uibModal) {
  'use strict';

  var vm = this;

  vm.saveServiceProviders = function () {
    Database.saveEntity({
      coll: 'service_providers',
      entity: service_provider,
      type: 'update'
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
    vm.saving = false;
    vm.inserted = {name: '', id: '', code: ''};
    vm.selectedServiceProviders = {};
    vm.edit_mode = false;
    vm.editing = [];
    Database.getAvailableServiceProviders({full_objects: true}).then(function (res) {
      vm.serviceProviders = res.data;
    });
  };
}