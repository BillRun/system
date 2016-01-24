angular
  .module('BillrunApp')
  .controller('ServiceProvidersController', ServiceProvidersController);

function ServiceProvidersController(Database, $uibModal) {
  'use strict';

  var vm = this;

  vm.saveServiceProvider = function (data, service_provider) {
    _.forEach(data, function (v, k) {
      service_provider[k] = v;
    });
    return Database.saveEntity({
      coll: 'service_providers',
      entity: service_provider,
      type: 'update'
    });
  };


  vm.removeServiceProvider = function (service_provider) {
    Database.removeEntity({
      coll: 'service_providers',
      ids: [service_provider._id]
    }).then(function () {
      vm.serviceProviders = _.without(vm.serviceProviders, service_provider);
    });
  };

  vm.init = function () {
    vm.saving = false;
    vm.inserted = {name: '', id: '', code: ''};
    Database.getAvailableServiceProviders({full_objects: true}).then(function (res) {
      vm.serviceProviders = res.data;
    });
  };
}