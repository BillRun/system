app.controller('SubscribersAutoRenewController', ['$scope', '$controller', 'Database',
  function ($scope, $controller, Database) {
    'use strict';

    $controller('EditController', {$scope: $scope});

    $scope.init = function () {
      $scope.initEdit();
      $scope.intervals = ["month", "day"];
      Database.getAvailableServiceProviders().then(function (res) {
        $scope.availableServiceProviders = res.data;
      });
      Database.getAvailablePlans('charging').then(function (res) {
        $scope.availablePlans = res.data;
      });
    };
  }]);