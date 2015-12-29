app.controller('CardsController', ['$scope', '$window', '$routeParams', 'Database',
  function ($scope, $window, $routeParams, Database) {
    'use strict';
    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/' + $routeParams.collection;
    };
    $scope.save = function () {
      var params = {
        entity: $scope.entity,
        coll: 'cards',
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

    $scope.capitalize = function (str) {
      return _.capitalize(str);
    };

    $scope.init = function () {
      $scope.action = $routeParams.action;
      var params = {
        coll: $routeParams.collection,
        id: $routeParams.id
      };
      Database.getEntity(params).then(function (res) {
        $scope.entity = res.data;
        $scope.card_status = $scope.entity.status;
        $scope.cardStatuses = ["Idle", "Active", "Disqualified", "Used", "Expired", "Stolen"];
      }, function (err) {
        alert("Danger! Danger! Beedeebeedeebeedee!");
      });
      Database.getAvailableServiceProviders().then(function (res) {
        $scope.availableServiceProviders = res.data;
      });
      Database.getAvailablePlans('charging').then(function (res) {
        $scope.availablePlans = res.data;
      });
    };
  }]);