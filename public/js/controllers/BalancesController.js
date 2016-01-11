angular
  .module('BillrunApp')
  .controller('BalancesController', BalancesController);

function BalancesController($controller) {
  'use strict';

  var vm = this;
  $controller('EditController', {$scope: vm});

  vm.init = function () {
    vm.initEdit();
  };
}