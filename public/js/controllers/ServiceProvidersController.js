angular
  .module('BillrunApp')
  .controller('ServiceProvidersController', ServiceProvidersController);

function ServiceProvidersController(Database, $uibModal) {
  'use strict';

  var vm = this;

  vm.save = function () {
    vm.saving = true;
    Database.saveServiceProviders(vm.serviceProviders).then(function (res) {
      vm.saving = false;
      if (res.data.success) {
        $uibModal.open({
          template: "<div>Save success!</div>",
          size: 'sm'
        });
      }
    });
  };

  vm.init = function () {
    vm.saving = false;
    Database.getAvailableServiceProviders({full_objects: true}).then(function (res) {
      vm.serviceProviders = res.data;
    });
  };
}