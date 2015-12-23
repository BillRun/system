app.controller('CollectionsController', ['$scope', '$routeParams', '$location', 'Database',
  function ($scope, $routeParams, $location, Database) {
    'use strict';

    $scope.capitalize = function (str) {
      return _.capitalize(str);
    };

    $scope.editItem = function (collection, item_id) {
      $location.path('/' + collection + '/edit/' + item_id);
    };

    $scope.datetimeCol = function (col) {
      return col === "to" || col === "from";
    };
    $scope.printDate = function (dateobj) {
      if (_.isObject(dateobj) && dateobj.sec) {
        var d = new Date(dateobj.sec * 1000);
        return moment(d).format("YYYY-MM-DD HH:MM:SS");
      }
      return '';
    };

    $scope.filterList = function () {
      var params = {
        coll: $routeParams.collection,
        session: JSON.stringify($scope.collection.session)
      };
      Database.filterCollectionItems(params).then(function (res) {
        $scope.collection.data = res.data.data;
      });
    };

    $scope.init = function () {
      var params = {
        coll: $routeParams.collection,
        show_prefix: false
      };
      $scope.listFilter = {};
      Database.getCollectionItems(params).then(function (res) {
        $scope.collection = res.data;
      });
    };
  }]);