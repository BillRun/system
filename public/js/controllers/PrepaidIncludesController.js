angular
  .module('BillrunApp')
  .controller('PrepaidIncludesController', PrepaidIncludesController);

function PrepaidIncludesController(Database, Utils, $http) {
  'use strict';
  var vm = this;
  vm.edit_mode = false;
  vm.newent = false;

  vm.newPPInclude = function () {
    vm.edit_mode = true;
    vm.newent = true;
    vm.current_entity = {
      name: "",
      id: undefined,
      charging_by: "",
      charging_by_usaget: "",
      priority: 0,
      from: new Date(),
      to: moment().add(100, 'years')
    };
  };

  vm.edit = function (external_id) {
    vm.edit_mode = true;
    vm.newent = false;
    vm.current_entity = _.find(vm.pp_includes, function (e) {
      return e.external_id === external_id;
    });
  };

  vm.cancel = function () { vm.edit_mode = false; };
  vm.save = function () {
    $http.post(baseUrl + '/admin/savePPIncludes', {data: vm.current_entity, new_entity: vm.newent}).then(function (res) {
      if (vm.newent) vm.pp_includes.push(vm.current_entity);
      vm.edit_mode = false;
    });
  };

  vm.init = function () {
    vm.availableChargingBy = [
      "total_cost",
      "cost",
      "usagev"
    ];
    vm.availableChargingByType = [
      "total_cost",
      "call",
      "data",
      "sms"
    ];
    Database.getAvailablePPIncludes({full_objects: true}).then(function (res) {
      vm.pp_includes = res.data.ppincludes;
      vm.authorized_write = res.data.authorized_write;
      var format = Utils.getDateFormat() + " HH:MM:SS";
      _.forEach(vm.pp_includes, function (pp_include) {
        pp_include.from = moment(pp_include.from.sec * 1000).format(format.toUpperCase());
        pp_include.to = moment(pp_include.to.sec * 1000).format(format.toUpperCase());
      });
    });
  };
}