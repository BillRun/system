app.controller('EditController', ['$scope', 'Utils', '$routeParams', '$window', 'Database',
  function ($scope, Utils, $routeParams, $window, Database) {
    'use strict';

    $scope.utils = Utils;
    $scope._ = _;
    $scope.entity = {};

    angular.element('.menu-item-home').removeClass('active');
    angular.element('.menu-item-' + $routeParams.collection).addClass('active');

    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/' + $routeParams.collection;
    };

    $scope.save = function () {
      var params = {
        entity: $scope.entity,
        coll: $routeParams.collection,
        type: $routeParams.action,
        duplicate_rates: ($scope.duplicate_rates ? $scope.duplicate_rates.on : false),
      };
      Database.saveEntity(params).then(function (res) {
        $window.location = baseUrl + '/admin/' + $routeParams.collection.replace(/_/g, '');
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

    $scope.initEdit = function (callback) {
      var params = {
        coll: $routeParams.collection.replace(/_/g, ''),
        id: $routeParams.id,
        type: $routeParams.action
      };
      $scope.advancedMode = false;
      $scope.action = $routeParams.action;
      Database.getEntity(params).then(function (res) {
        $scope.entity = res.data.entity;
        $scope.authorized_write = res.data.authorized_write;
        if (callback !== undefined) callback($scope.entity);
      }, function (err) {
        alert("Connection error!");
      });
    };
  }]);