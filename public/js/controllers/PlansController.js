app.controller('PlansController', ['$scope', '$http', '$window', function ($scope, $http, $window) {
  $scope.entity = entity;
  $scope.form_data = form_data;
  $scope.includeTypes = ['call', 'data', 'sms', 'mms', 'groups'];
  $scope.groupParams = ["data", "call", "incoming_call", "incoming_sms", "sms"];
  $scope.newParamType = {};
  $scope.newParamValue = {};
  $scope.newGroup = {name: ""};
  $scope.shown = {include: true, groups: true};
  $scope.shownGroups = {};
  _.each($scope.entity.include.groups, function (group, group_name) {
    $scope.shownGroups[group_name] = true;
  });

  $scope.addPlanInclude = function () {
    if ($scope.newIncludeType && $scope.newIncludeValue && $scope.entity.include[$scope.newIncludeType] === undefined)
      $scope.entity.include[$scope.newIncludeType] = $scope.newIncludeValue;
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
  };

  $scope.deleteGroup = function (group_name) {
    delete $scope.entity.include.groups[group_name];
  };

  $scope.addGroupParam = function (group_name) {
    if (group_name && $scope.newParamType[group_name] && $scope.newParamValue[group_name] && $scope.entity.include.groups[group_name])
      $scope.entity.include.groups[group_name][$scope.newParamType[group_name]] = $scope.newParamValue[group_name];
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
}]);
