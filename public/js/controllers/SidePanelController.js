angular
  .module('BillrunApp')
  .controller('SidePanelController',  SidePanelController);

function SidePanelController(Database) {
  'use strict';

  var vm = this;

  vm.init = function () {
    vm.showSidePanel = false;
    Database.getSubscriberDetails().then(function (res) {
      if (res.data.subscriber) {
        vm.subscriber = res.data.subscriber;
        vm.showSidePanel = true;
      } else {
        vm.showSidePanel = false;
      }
    });
  };
}