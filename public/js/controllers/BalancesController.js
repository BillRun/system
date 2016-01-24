angular
  .module('BillrunApp')
  .controller('BalancesController', BalancesController);

function BalancesController($controller, Utils) {
  'use strict';

  var vm = this;
  $controller('EditController', {$scope: vm});
  vm.utils = Utils;

  vm.saveBalance = function () {
    if (vm.action === 'new') {
      if (vm.newBalance && vm.newBalanceAmount) {
        if (vm.newBalance !== 'cost') vm.entity.balance = {totals: {usagev: vm.newBalanceAmount}};
        else vm.entity.balance = {cost: vm.newBalanceAmount};
      }
    }
    vm.save();
  };

  vm.init = function () {
    vm.initEdit(function (entity) {
      if (entity.to && _.result(entity.to, 'sec')) {
        entity.to = new Date(entity.to.sec * 1000);
      }
      if (entity.from && _.result(entity.from, 'sec')) {
        entity.from = new Date(entity.from.sec * 1000);
      }
    });
    vm.availableBalanceTypes = ["CORE BALANCE", "Bonus Balance", "Local Calls Balance", "Local Calls Minutes",
      "Internet and Data", "Pele in_net Time", "SMS Balance", "Data Package", "Monthly Bonus", "Special Monthly Re"];
    vm.availableBalances = ["cost", "sms", "call", "data"];
  };
}