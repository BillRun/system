app.controller('BalancesController', ['$scope', '$window', '$routeParams', 'Database',
  function ($scope, $window, $routeParams, Database) {
    'use strict';
    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/' + $routeParams.collection;
    };
    $scope.save = function () {
      var params = {
        entity: $scope.entity,
        coll: 'balances',
        type: $routeParams.action
      };
      Database.saveEntity(params).then(function (res) {
        $window.location = baseUrl + '/admin/' + $routeParams.collection;
      }, function (err) {
        alert("Danger! Danger! Beedeebeedeebeedee!");
      });
    };

    $scope.setAdvancedMode = function (mode) {
      $scope.advancedMode = mode;
    };

    $scope.init = function () {
      var params = {
        coll: $routeParams.collection,
        id: $routeParams.id
      };
      Database.getEntity(params).then(function (res) {
        $scope.entity = res.data.entity;
        $scope.authorized_write = res.data.authorized_write;
      }, function (err) {
        alert("Danger! Danger! Beedeebeedeebeedee!");
      });
    };
  }]);