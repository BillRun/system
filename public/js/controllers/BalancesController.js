app.controller('BalancesController', ['$scope', '$window', '$routeParams', 'Database',
  function ($scope, $window, $routeParams, Database) {
    'use strict';
    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/balances';
    };
    $scope.save = function () {
      var params = {
        entity: $scope.entity,
        coll: 'balances',
        type: $routeParams.action
      };
      Database.saveEntity(params).then(function (res) {
        $window.location = baseUrl + '/admin/balances';
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
        id: $routeParams.id
      };
      Database.getEntity(params).then(function (res) {
        $scope.entity = res.data;
      }, function (err) {
        alert("Danger! Danger! Beedeebeedeebeedee!");
      });
    };
  }]);