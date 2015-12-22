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

    $scope.setAdvancedMode = function (mode) {
      $scope.advancedMode = mode;
    };

    $scope.init = function () {
      var params = {
        coll: 'balances',
        id: $routeParams.id,
        type: 'update'
      };
      Database.getEntity(params).then(function (res) {
        $scope.entity = res.data;
      }, function (err) {
        alert("Danger! Danger! Beedeebeedeebeedee!");
      });
    };
  }]);