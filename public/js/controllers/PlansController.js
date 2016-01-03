app.controller('PlansController', ['$scope', '$window', '$routeParams', 'Database',
  function ($scope, $window, $routeParams, Database) {
    'use strict';

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

    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/' + $routeParams.collection;
    };
    $scope.savePlan = function () {
      var params = {
        entity: $scope.entity,
        coll: $routeParams.collection,
        type: $routeParams.action,
        duplicate_rates: $scope.duplicate_rates.on
      };
      Database.saveEntity(params).then(function () {
        $window.location = baseUrl + '/admin/' + $routeParams.collection;
      }, function (err) {
        alert("Connection error!");
      });
    };

    $scope.isObject = function (o) {
      return _.isObject(o);
    };

    $scope.plansTemplate = function () {
      if ($scope.entity && $scope.entity.type === 'charging') {
        return 'views/plans/chargingedit.html';
      } else if ($scope.entity) {
        return 'views/plans/customeredit.html';
      }
      return '';
    };

    $scope.setAdvancedMode = function (mode) {
      $scope.advancedMode = mode;
    };

    $scope.capitalize = function (str) {
      return _.capitalize(str);
    };

    $scope.init = function () {
      var params = {
        coll: $routeParams.collection,
        id: $routeParams.id
      };
      Database.getEntity(params).then(function (res) {
        $scope.entity = res.data.entity;
        $scope.authorized_write = res.data.authorized_write;
      });
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
