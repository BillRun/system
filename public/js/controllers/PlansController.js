app.controller('PlansController', ['$scope', '$http', '$window', function ($scope, $http, $window) {
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
    if ($scope.entity.include.groups[$scope.newGroup.name]) return;
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
    $window.location = $scope.form_data.baseUrl + '/admin/' + $scope.form_data.collectionName;
  };
  $scope.savePlan = function () {
    var ajaxOpts = {
      id: $scope.entity._id,
      coll: $scope.form_data.collectionName,
      type: $scope.form_data.type,
      duplicate_rates: false,
      data: JSON.stringify($scope.entity)
    };
    $http.post($scope.form_data['baseUrl'] + '/admin/save', ajaxOpts).then(function (res) {
      $window.location = $scope.form_data.baseUrl + '/admin/' + $scope.form_data.collectionName;
    }, function (err) {
      alert("Danger! Danger! Beedeebeedeebeedee!");
    });
  };

  $scope.init = function () {
	$scope.entity = entity;
	$scope.form_data = form_data;
	$scope.includeTypes = ['call', 'data', 'sms', 'mms'];
	$scope.groupParams = ["data", "call", "incoming_call", "incoming_sms", "sms"];
	$scope.newInclude = {type: undefined, value: undefined};
	$scope.newGroupParam = [];
	$scope.newGroup = {name: ""};
  };
}]);
