app.controller('CollectionsController', ['$scope', '$routeParams', '$location', 'Database',
  function ($scope, $routeParams, $location, Database) {
    'use strict';

    $scope.editItem = function (collection, item_id) {
      $location.path('/' + collection + '/edit/' + item_id);
    };

    $scope.init = function () {
      var params = {
        collection: $routeParams.collection,
        show_prefix: false
      };
      Database.getCollectionItems(params).then(function (res) {
        $scope.collection = res.data;
        console.log(res.data);
      });
    };
  }]);