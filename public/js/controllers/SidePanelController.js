angular
  .module('BillrunApp')
  .controller('SidePanelController',  SidePanelController);

function SidePanelController(Database) {
  'use strict';

  var vm = this;
  vm.showSidePanel = (localStorage.getItem('showSidePanel') === "true");

  vm.togglePanel = function () {
    vm.showSidePanel = !vm.showSidePanel;
    localStorage.setItem('showSidePanel', vm.showSidePanel);
  };

  vm.init = function () {
    Database.getSubscriberDetails().then(function (res) {
      if (res.data.subscriber) {
        if (_.isEmpty(res.data.subscriber)) return;
        vm.subscriber = res.data.subscriber;
      } else {
        vm.showSidePanel = false;
      }
    });
  };
}