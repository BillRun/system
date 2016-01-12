angular
  .module('BillrunApp')
  .controller('BalancesController', BalancesController);

function BalancesController($controller, Utils) {
  'use strict';

  var vm = this;
  $controller('EditController', {$scope: vm});
  vm.utils = Utils;

  vm.init = function () {
    vm.initEdit();
  };
}