angular
  .module('BillrunApp')
  .controller('BalancesController', BalancesController);

function BalancesController($controller, Utils) {
  'use strict';

  var vm = this;
  $controller('EditController', {$scope: vm});
  vm.utils = Utils;

  vm.init = function () {
    vm.initEdit(function (entity) {
      if (entity.to && _.result(entity.to, 'sec')) {
        entity.to = new Date(entity.to.sec * 1000);
      }
    });
  };
}