app.controller('BalancesController', ['$scope', '$controller',
  function ($scope, $controller) {
    'use strict';

    $controller('EditController', {$scope: $scope});

    $scope.init = function () {
      $scope.entity = {};
      $scope.initEdit($scope.entity);
    };
  }]);