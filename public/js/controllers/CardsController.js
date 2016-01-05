app.controller('CardsController', ['$scope', '$window', '$routeParams', 'Database', '$controller',
  function ($scope, $window, $routeParams, Database, $controller) {
    'use strict';

    $controller('EditController', {$scope: $scope});

    $scope.save = function () {
      $scope.entity.to = $scope.entity.to / 1000;
      var params = {
        entity: $scope.entity,
        coll: 'cards',
        type: $routeParams.action
      };
      Database.saveEntity(params).then(function (res) {
        $window.location = baseUrl + '/admin/' + $routeParams.collection;
      }, function (err) {
        alert("Connection error!");
      });
    };

    $scope.isStatusDisabled = function (status) {
      var curr_card_status = $scope.card_status.toLowerCase();
      if (status === undefined) return true;
      if ($scope.card_status === undefined) return false;
      status = status.toLowerCase();
      // idle -> (active optional) -> [expired,stolen,disqualified,used]
      // don't allow going backwards
      if (curr_card_status === "idle") return false;
      if (curr_card_status === "active" && status === "idle") return true;
      if (curr_card_status === "active") return false;
      if (status === "idle" || status === "active") return true;
      return true;
    };

    $scope.init = function () {
      $scope.action = $routeParams.action;
      var params = {
        coll: $routeParams.collection,
        id: $routeParams.id
      };
      Database.getEntity(params).then(function (res) {
        $scope.entity = res.data.entity;
        $scope.card_status = $scope.entity.status;
        $scope.authorized_write = res.data.authorized_write;
        if (_.isObject($scope.entity.to)) {
          $scope.entity.to = $scope.entity.to.sec * 1000;
        }
        $scope.cardStatuses = ["Idle", "Active", "Disqualified", "Used", "Expired", "Stolen"];
      }, function (err) {
        alert("Connection error!");
      });
      Database.getAvailableServiceProviders().then(function (res) {
        $scope.availableServiceProviders = res.data;
      });
      Database.getAvailablePlans('charging').then(function (res) {
        $scope.availablePlans = res.data;
      });
    };
  }]);