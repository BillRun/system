angular.module('plansApp', []).config(function ($httpProvider) {
  $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded;charset=utf-8';
  /**
   * The workhorse; converts an object to x-www-form-urlencoded serialization.
   * @param {Object} obj
   * @return {String}
   */
  var param = function (obj) {
    var query = '', name, value, fullSubName, subName, subValue, innerObj, i;

    for (name in obj) {
    value = obj[name];

    if (value instanceof Array) {
      for (i = 0; i < value.length; ++i) {
      subValue = value[i];
      fullSubName = name + '[' + i + ']';
      innerObj = {};
      innerObj[fullSubName] = subValue;
      query += param(innerObj) + '&';
      }
    } else if (value instanceof Object) {
      for (subName in value) {
      subValue = value[subName];

      // TODO : Convert this in the directive!
      if (subValue && subValue.toDateString)
        subValue = twoDigit(subValue.getDate()) + '-' + twoDigit(subValue.getMonth() + 1) + '-' + subValue.getFullYear();
      // TODO

      fullSubName = name + '[' + subName + ']';
      innerObj = {};
      innerObj[fullSubName] = subValue;
      query += param(innerObj) + '&';
      }
    } else if (value !== undefined && value !== null) {
      query += encodeURIComponent(name) + '=' + encodeURIComponent(value) + '&';
    }
    }

    return query.length ? query.substr(0, query.length - 1) : query;
  };

  // Override $http service's default transformRequest
  $httpProvider.defaults.transformRequest = [function (data) {
    if (!data) data = {};
    return angular.isObject(data) && String(data) !== '[object File]' ? param(data) : data;
  }];
}).controller('PlansController', ['$scope', '$http', '$window', function ($scope, $http, $window) {
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
