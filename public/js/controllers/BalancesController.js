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
      _.forEach(vm.entity.balance.totals, function (total) {
        if (total.cost) total.cost = parseFloat(total.cost);
        if (total.usagev) total.usagev = parseFloat(total.usagev);
      });
    }
    if (vm.entity.to) vm.entity.to = vm.entity.to.toISOString();
    if (vm.action === 'new') {
      var postData = {
        method: 'update',
        sid: "" + vm.entity.sid,
        query: JSON.stringify({
          "pp_includes_name": vm.entity.pp_includes_name
        }),
        upsert: JSON.stringify({
          value: vm.newBalanceAmount, 
          expiration_date: vm.entity.to,
          operation: "set"
        })
      };
      $http.post(baseUrl + '/api/balances', postData).then(function (res) {
        if (res.data.status)
          $window.location = baseUrl + '/admin/balances';
        else
          // TODO: change to flash message
          alert(res.data.desc + " - " + res.data.details);
      });
    } else {
      vm.save(true);
    }
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