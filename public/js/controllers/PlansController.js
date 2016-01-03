app.controller('PlansController', ['$scope', '$window', '$routeParams', 'Database', '$controller',
  function ($scope, $window, $routeParams, Database, $controller) {
    'use strict';

    $controller('EditController', {$scope: $scope});

    $scope.addPlanInclude = function () {
      if ($scope.newInclude && $scope.newInclude.value && $scope.entity.include[$scope.newInclude.type] === undefined) {
        $scope.entity.include[$scope.newInclude.type] = $scope.newInclude.value;
        $scope.newInclude = {type: undefined, value: undefined};
      }
    };

    $scope.deleteInclude = function (name) {
      delete $scope.entity.include[name];
    };

    $scope.deleteGroupParam = function (group_name, param) {
      delete $scope.entity.include.groups[group_name][param];
    };

    $scope.addNewGroup = function () {
      if ($scope.entity.include.groups[$scope.newGroup.name]) {
        return;
      }
      $scope.entity.include.groups[$scope.newGroup.name] = {};
      $scope.newGroup.name = "";
    };

    $scope.deleteGroup = function (group_name) {
      delete $scope.entity.include.groups[group_name];
    };

    $scope.addGroupParam = function (group_name) {
      if (group_name && $scope.newGroupParam[group_name] && $scope.newGroupParam[group_name].value && $scope.entity.include.groups[group_name]) {
        $scope.entity.include.groups[group_name][$scope.newGroupParam[group_name].type] = $scope.newGroupParam[group_name].value;
        $scope.newGroupParam[group_name].type = undefined;
        $scope.newGroupParam[group_name].value = undefined;
      }
    };

    $scope.plansTemplate = function () {
      if ($scope.entity && $scope.entity.type === 'charging') {
        return 'views/plans/chargingedit.html';
      } else if ($scope.entity) {
        return 'views/plans/customeredit.html';
      }
      return '';
    };

    $scope.init = function () {
      $scope.initEdit();
      Database.getAvailableServiceProviders().then(function (res) {
        $scope.availableServiceProviders = res.data;
      });
      $scope.action = $routeParams.action.replace(/_/g, ' ');
      $scope.duplicate_rates = {on: ($scope.action === 'duplicate')};
      $scope.includeTypes = ['call', 'data', 'sms', 'mms'];
      $scope.groupParams = ["data", "call", "incoming_call", "incoming_sms", "sms"];
      $scope.newInclude = {type: undefined, value: undefined};
      $scope.newGroupParam = [];
      $scope.newGroup = {name: ""};
    };
  }]);
