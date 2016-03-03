angular
  .module('BillrunApp')
  .controller('BandwidthCapController', BandwidthCapController);

function BandwidthCapController(Database) {
  'use strict';
  var vm = this;

  vm.edit_mode = false;
  vm.newent = false;

  vm.newBandwidthCap = function () {
    vm.current_entity = {
      cap_name: "",
      service: "",
      speed: 0
    };
    vm.newent = true;
    vm.edit_mode = true;
  };
  vm.save = function () {
    Database.saveBandwidthCap({data: vm.current_entity, newent: vm.newent}).then(function (res) {
      if (res.data.status) {
        vm.newent = false;
        vm.edit_mode = false;
        vm.bandwidthCaps[vm.current_entity.cap_name] = res.data.data;
      }
    });
  };
  vm.cancel = function () {
    vm.edit_mode = false;
    vm.newent = false;
  };
  vm.edit = function (cap_name) {
    vm.edit_mode = true;
    vm.newent = false;
    vm.current_entity = vm.bandwidthCaps[cap_name];
    vm.current_entity.cap_name = cap_name;
  };

  vm.removeBandwidthCap = function (cap_name) {
    var r = confirm("Are you sure you want to remove " + cap_name);
    if (r) {
      Database.removeBandwidthCap({cap_name: cap_name}).then(function (res) {
        delete vm.bandwidthCaps[cap_name];
      });
    }
  };

  vm.init = function () {
    Database.getBandwidthCapDetails().then(function (res) {
      vm.bandwidthCaps = res.data.caps;
      vm.authorized_write = res.data.authorized_write;
    });
  };
}