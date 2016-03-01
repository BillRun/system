angular
  .module('BillrunApp')
  .controller('BalancesController', BalancesController);

function BalancesController($controller, Utils, $http, $window, Database) {
  'use strict';

  var vm = this;
  $controller('EditController', {$scope: vm});
  vm.utils = Utils;

  vm.saveBalance = function () {
    if (vm.action !== 'new') {
      if (vm.entity.balance && vm.entity.balance.totals) {
        _.forEach(vm.entity.balance.totals, function (total) {
          if (total.cost) total.cost = parseFloat(total.cost);
          if (total.usagev) total.usagev = parseFloat(total.usagev);
        });
      }
      if (vm.entity.balance.cost && _.isString(vm.entity.balance.cost)) vm.entity.balance.cost = parseFloat(vm.entity.balance.cost);
    }
    if (vm.entity.to && _.isObject(vm.entity.to)) vm.entity.to = vm.entity.to.toISOString();
    var postData = {
      method: 'update',
      sid: parseInt(vm.entity.sid, 10),
      query: JSON.stringify({
        "pp_includes_name": vm.entity.pp_includes_name
      })
    };
    if (vm.action === "new") {
      postData.upsert = JSON.stringify({
        value: vm.newBalanceAmount,
        expiration_date: vm.entity.to,
        operation: "set"
      });
    } else {
      var value = 0;
      if (vm.entity.balance.cost) value = vm.entity.balance.cost;
      else if (_.result(vm.entity.balance, "totals.call.usagev")) value = vm.entity.balance.totals.call.usagev;
      else if (_.result(vm.entity.balance, "totals.call.cost")) value = vm.entity.balance.totals.call.cost;
      else if (_.result(vm.entity.balance, "totals.sms.usagev")) value = vm.entity.balance.totals.sms.usagev;
      else if (_.result(vm.entity.balance, "totals.sms.cost")) value = vm.entity.balance.totals.sms.cost;
      else if (_.result(vm.entity.balance, "totals.data.usagev")) value = vm.entity.balance.totals.data.usagev;
      else if (_.result(vm.entity.balance, "totals.data.cost")) value = vm.entity.balance.totals.data.cost;
      postData.upsert = JSON.stringify({
        value: value,
        expiration_date: vm.entity.to,
        operation: (vm.entity.operation ? vm.entity.operation : "")
      });
    }
    $http.post(baseUrl + '/api/balances', postData).then(function (res) {
      if (res.data.status)
        $window.location = baseUrl + '/admin/balances';
      else
        // TODO: change to flash message
        alert("Error saving balance! Please refresh and try again!");
    });
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
    Database.getAvailablePPIncludes().then(function (res) {
      vm.availableBalanceTypes = res.data;
    });
  };
}