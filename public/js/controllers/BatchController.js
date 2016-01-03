app.controller('BatchController', ['$scope', '$window', '$routeParams', 'Database',
  function ($scope, $window, $routeParams, Database) {
    'use strict';
    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/cards';
    };
    $scope.save = function () {
      var serial_number_range = {
        "$gte": $scope.entity.serial_numbers_from,
        "$lte": $scope.entity.serial_numbers_to
      };
      var params = {
        entity: $scope.entity,
        coll: 'cards',
        batch: $scope.batch_no,
        type: $routeParams.action,
        serial_number: JSON.stringify(serial_number_range)
      };
      Database.saveEntity(params).then(function (res) {
        $window.location = baseUrl + '/admin/cards';
      }, function (err) {
        alert("Connection error!");
      });
    };

    $scope.isStatusDisabled = function (status) {
      if (status === undefined) return true;
      if ($scope.card_status === undefined) return false;
      // idle -> (active optional) -> [expired,stolen,disqualified,used]
      // disallow going backwards
      return false;
    };

    $scope.capitalize = function (str) {
      return _.capitalize(str);
    };

    $scope.init = function () {
      $scope.action = $routeParams.action;
      $scope.batch_no = $routeParams.id;
      $scope.cardStatuses = ["Idle", "Active", "Disqualified", "Used", "Expired", "Stolen"];
      $scope.entity = {
        serial_numbers_from: undefined,
        serial_numbers_to: undefined,
        service_provider: undefined,
        status: undefined,
        charging_plan_name: undefined
      };
      Database.getAvailableServiceProviders().then(function (res) {
        $scope.availableServiceProviders = res.data;
      });
      Database.getAvailablePlans('charging').then(function (res) {
        $scope.availablePlans = res.data;
      });
    };
  }]);