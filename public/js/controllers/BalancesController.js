angular
  .module('BillrunApp')
  .controller('BalancesController', BalancesController);

function BalancesController($controller, Utils, $http, $window) {
  'use strict';

  var vm = this;
  $controller('EditController', {$scope: vm});
  vm.utils = Utils;

  vm.saveBalance = function () {
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
    vm.availableBalanceTypes = ["CORE BALANCE", "Bonus Balance", "Local Calls Balance", "Local Calls Minutes",
      "Internet and Data", "Pele in_net Time", "SMS Balance", "Data Package", "Monthly Bonus", "Special Monthly Re"];
    vm.availableBalances = ["cost", "sms", "call", "data"];
  };
}