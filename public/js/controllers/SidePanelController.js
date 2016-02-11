angular
  .module('BillrunApp')
  .controller('SidePanelController',  SidePanelController);

function SidePanelController(Database) {
  'use strict';

  var vm = this;
  vm.showSidePanel = false;

  vm.togglePanel = function () {
    vm.showSidePanel = !vm.showSidePanel;
  };

  vm.init = function () {
    Database.getSubscriberDetails().then(function (res) {
      if (res.data.subscriber) {
        if (_.isEmpty(res.data.subscriber)) return;
        vm.subscriber = res.data.subscriber;
        vm.showSidePanel = true;
      } else {
        vm.showSidePanel = false;
      }
    });
  };
}