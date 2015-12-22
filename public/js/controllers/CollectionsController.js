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

    $scope.init = function () {
      var params = {
        collection: $routeParams.collection,
        show_prefix: false
      };
      Database.getCollectionItems(params).then(function (res) {
        $scope.collection = res.data;
      });
    };
  }]);