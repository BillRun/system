app.controller('EditController', ['$scope', 'Utils', '$routeParams', '$window', 'Database',
  function ($scope, Utils, $routeParams, $window, Database) {
    'use strict';

    $scope.utils = Utils;
    $scope._ = _;
    $scope.entity = {};

    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/' + $routeParams.collection;
    };

    $scope.save = function () {
      var params = {
        entity: $scope.entity,
        coll: $routeParams.collection,
        type: $routeParams.action,
        duplicate_rates: ($scope.duplicate_rates ? $scope.duplicate_rates.on : false)
      };
      Database.saveEntity(params).then(function (res) {
        $window.location = baseUrl + '/admin/' + $routeParams.collection;
      }, function (err) {
        alert("Connection error!");
      });
    };

    $scope.capitalize = function (str) {
      return _.capitalize(str);
    };

    $scope.isObject = function (o) {
      return _.isObject(o);
    };

    $scope.setAdvancedMode = function (mode) {
      $scope.advancedMode = mode;
    };

    $scope.initEdit = function () {
      var params = {
        coll: $routeParams.collection.replace(/_/g, ''),
        id: $routeParams.id
      };
      Database.getEntity(params).then(function (res) {
        $scope.entity = res.data.entity;
        $scope.authorized_write = res.data.authorized_write;
      }, function (err) {
        alert("Connection error!");
      });
    };
  }]);