app.controller('BalancesController', ['$scope', '$window', '$routeParams', 'Database',
  function ($scope, $window, $routeParams, Database) {
    'use strict';
    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/plans';
    };
    $scope.savePlan = function () {
      Database.saveEntity($scope.entity, 'plans').then(function (res) {
        $window.location = baseUrl + '/admin/plans';
      }, function (err) {
        alert("Danger! Danger! Beedeebeedeebeedee!");
      });
    };

    $scope.init = function () {
      Database.getEntity('balances', $routeParams.id).then(function (res) {
        $scope.entity = res.data;
      });
    };
  }]);