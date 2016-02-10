app.controller('PlansController', ['$scope', '$window', '$routeParams', 'Database', '$controller', '$location',
  function ($scope, $window, $routeParams, Database, $controller, $location) {
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
      return 'views/plans/' + $location.search().type + 'edit.html';
    };

    $scope.removeIncludeType = function (include_type_name) {
      delete $scope.entity.include[include_type_name];
    };
    $scope.removeIncludeCost = function (index) {
      $scope.entity.include.cost.splice(index, 1);
    };

    $scope.addIncludeType = function () {
      var include_type = $scope.newIncludeType.type;
      var new_include_type = {
        cost: undefined,
        usagev: undefined,
        pp_includes_name: "",
        pp_includes_external_id: "",
        period: {
          duration: undefined,
          unit: ""
        }
      };
      if (_.isUndefined($scope.entity.include[include_type])) {
        if (include_type === "cost")
          $scope.entity.include.cost = [new_include_type];
        else
          $scope.entity.include[include_type] = new_include_type;
      } else if (include_type === "cost") {
        $scope.entity.include.cost.push(new_include_type);
      }
      $scope.newIncludeType.type = '';
    };

    $scope.includeTypeExists = function (include_type) {
      if (include_type === 'cost')
        return false;
      return !_.isUndefined($scope.entity.include[include_type]);
    };

    $scope.save = function (redirect) {
      $scope.err = {};
      var params = {
        entity: $scope.entity,
        coll: $routeParams.collection,
        type: $routeParams.action,
        duplicate_rates: ($scope.duplicate_rates ? $scope.duplicate_rates.on : false)
      };
      Database.saveEntity(params).then(function (res) {
        if (redirect) {
          $window.location = baseUrl + '/admin/' + $location.search().type + 'plans';
        }
      }, function (err) {
        $scope.err = err;
      });
    };
    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/' + $scope.entity.type + $routeParams.collection;
    };

    $scope.init = function () {
      angular.element('.menu-item-' + $location.search().type + 'plans').addClass('active');
      var params = {
        coll: $routeParams.collection.replace(/_/g, ''),
        id: $routeParams.id,
        type: $routeParams.action
      };
      $scope.advancedMode = false;
      $scope.action = $routeParams.action;
      Database.getEntity(params).then(function (res) {
        if ($routeParams.action !== "new") {
          $scope.entity = res.data.entity;
          if (_.isUndefined($scope.entity.include) && $scope.entity.recurring != 1)
            $scope.entity.include = {};
        } else if ($location.search().type === "charging") {
          $scope.entity = {
            "name": "",
            "external_id": "",
            "service_provider": "",
            "desc": "",
            "type": "charging",
            "operation": "",
            "charging_type": [],
            "from": new Date(),
            "to": new Date(),
            "include": {},
            "priority": "0"
          };
        } else if ($location.search().type === "customer") {
          $scope.entity = {
            "name": "",
            "from": new Date(),
            "to": new Date(),
            "type": "customer",
            "external_id": "",
            "external_code": ""
          };
        }
        $scope.plan_rates = res.data.plan_rates;
        $scope.authorized_write = res.data.authorized_write;
      }, function (err) {
        alert("Connection error!");
      });

      $scope.availableCostUnits = ['days', 'months'];
      $scope.availableOperations = ['set', 'accumulated', 'charge'];
      $scope.newIncludeType = {type: ""};
      $scope.availableIncludeTypes = ['cost', 'data', 'sms', 'call'];
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
