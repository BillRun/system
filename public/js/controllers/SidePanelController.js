angular
  .module('BillrunApp')
  .controller('SidePanelController',  SidePanelController);

function SidePanelController(Database) {
  'use strict';

  var vm = this;
  vm.showSidePanel = false;

  vm.init = function () {
    if (_.isEmpty(angular.element('.show-side-panel'))) return;
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