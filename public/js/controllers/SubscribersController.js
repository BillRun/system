app.controller('SubscribersController', ['$scope', '$window', '$routeParams', 'Database', '$controller',
  function ($scope, $window, $routeParams, Database, $controller) {
    'use strict';

    $controller('EditController', {$scope: $scope});

    $scope.addIMSI = function () {
      $scope.entity.imsi.push("");
    };

    $scope.deleteIMSI = function (imsiIndex) {
      if (imsiIndex === undefined)
        return;
      $scope.entity.imsi.splice(imsiIndex, 1);
    };

    $scope.init = function () {
      $scope.action = $routeParams.action;
      $scope.entity = {imsi: []};
      if ($scope.action.toLowerCase() !== "new") {
        var params = {
          coll: $routeParams.collection,
          id: $routeParams.id
        };
        Database.getEntity(params).then(function (res) {
          $scope.entity = res.data.entity;
          if ($scope.entity.imsi && _.isString($scope.entity.imsi)) {
            $scope.entity.imsi = [$scope.entity.imsi];
          }
          $scope.authorized_write = res.data.authorized_write;
        }, function (err) {
          alert("Connection error!");
        });
      }
      Database.getAvailableServiceProviders().then(function (res) {
        $scope.availableServiceProviders = res.data;
      });
      Database.getAvailablePlans().then(function (res) {
        $scope.availablePlans = res.data;
      });
      $scope.availableLanguages = ["Hebrew", "English", "Arabic", "Russian", "Thai"];
      $scope.availableChargingTypes = ["prepaid", "postpaid"];
    };
  }]);