app.controller('CollectionsController', ['$scope', '$routeParams', '$location', 'Database',
  function ($scope, $routeParams, $location, Database) {
    'use strict';

    var min_pages = 0;
    var max_pages = 0;

    function calculatePaging() {
      var count = parseInt($scope.pager.count, 10);
      var current = parseInt($scope.pager.current, 10);
      var range = 5;
      var min = current - range;
      var max = current + range;
      if (current < 1) current = 1;
      if (current > count) current = count;
      if (min < 1) min = 1;
      if (max > count) max = count;
      min_pages = min;
      max_pages = max;
      $scope.i = min;
    }

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

    $scope.editBatch = function () {
      if (!$scope.listFilter.batch_number) return;
      $location.path('/batch/update/' + $scope.listFilter.batch_number);
    };

    $scope.goto = function (action) {
      if (!_.isEmpty($scope.selected_item)) return;
      if (action === undefined) return;
      if (_.countBy(_.values($scope.selected_items), function (n) { return n === true; }).true > 1) {
        alert("Please select only one item");
        return;
      }
      var selected;
      _.forEach($scope.selected_items, function (v, k) {
        if (k) selected = k;
        return false;
      });
      $location.path('/' + $routeParams.collection + '/' + action + '/' + selected);
    };

    $scope.page = function (page) {
      $location.search({page: page});
    };
    $scope.nextPage = function () {
      $scope.page(parseInt($scope.pager.current, 10) + 1);
    };
    $scope.prevPage = function () {
      $scope.page(parseInt($scope.pager.current, 10) - 1);
    };

    $scope.contains = function (arr, str) {
      return _.contains(arr, str);
    };

    $scope.maxPages = function () {
      var i, a = new Array();
      for (i = min_pages; i <= max_pages; i++) {
        a.push(i);
      }
      return a;
    };

    $scope.init = function () {
      var params = {
        coll: $routeParams.collection,
        show_prefix: false
      };
      $scope.active = $routeParams.collection;
      $scope.listFilter = {};
      $scope.selected_items = {};
      if ($location.search() && $location.search().page) {
        params.page = parseInt($location.search().page, 10);
      }
      Database.getCollectionItems(params).then(function (res) {
        $scope.pager = res.data.pager;
        calculatePaging();
        $scope.collection = res.data.items;
        $scope.authorized_write = res.data.authorized_write;
      });
    };
  }]);