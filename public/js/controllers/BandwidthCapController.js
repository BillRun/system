angular
  .module('BillrunApp')
  .controller('BandwidthCapController', BandwidthCapController);

function BandwidthCapController(Database) {
  'use strict';
  var vm = this;

  vm.edit_mode = false;

  vm.newBandwidthCap = function () {
    vm.current_entity = {
      cap_name: "",
      service: "",
      speed: 0
    };
    vm.edit_mode = true;
  };
  vm.save = function () {

  };
  vm.cancel = function () {
    vm.edit_mode = false;
  };
  vm.edit = function (cap_name) {
    vm.edit_mode = true;
    vm.current_entity = vm.bandwidthCaps[cap_name];
    vm.current_entity.cap_name = cap_name;
  };

  vm.init = function () {
    Database.getBandwidthCapDetails().then(function (res) {
      vm.bandwidthCaps = res.data.caps;
      vm.authorized_write = res.data.authorized_write;
    });
  };
}