angular
  .module('BillrunApp')
  .controller('PrepaidIncludesController', PrepaidIncludesController);

function PrepaidIncludesController(Database) {
  'use strict';
  var vm = this;

  vm.init = function () {
    Database.getAvailablePPIncludes({full_objects: true}).then(function (res) {
      console.log(res);
      vm.pp_includes = res.data;
    });
  };
}