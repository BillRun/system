app.controller('ListController', ['$scope', '$routeParams', '$location', 'Database', 'Utils',
	function ($scope, $routeParams, $location, Database, Utils) {
		'use strict';

		var min_pages = 0;
		var max_pages = 0;

		function calculatePaging() {
			var count = parseInt($scope.pager.count, 10);
			var current = parseInt($scope.pager.current, 10);
			var range = 5;
			var min = current - range;
			var max = current + range;
			if (current < 1)
				current = 1;
			if (current > count)
				current = count;
			if (min < 1)
				min = 1;
			if (max > count)
				max = count;
			min_pages = min;
			max_pages = max;
			$scope.i = min;
		}

		function getCollectionItems(res) {
			$scope.pager = res.data.pager;
			$scope.pager.current = parseInt($scope.pager.current, 10);
			$scope.pager.size = parseInt($scope.pager.size, 10);
			$scope.pager.count = parseInt($scope.pager.count, 10);
			calculatePaging();
			$scope.collection = res.data.items;
			$scope.session = res.data.items.session;
			$scope.filter_fields = res.data.filter_fields;
			$scope.authorized_write = res.data.authorized_write;
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
				var format = Utils.getDateFormat() + " HH:MM:SS";
				return moment(d).format(format.toUpperCase());
			}
			return '';
		};

		$scope.filterList = function () {
			var params = {
				coll: $routeParams.collection,
				filter: JSON.stringify($scope.session),
				size: $scope.pager.size
			};
			Database.getCollectionItems(params).then(getCollectionItems);
		};

		$scope.editBatch = function () {
			if (!$scope.listFilter.batch_number)
				return;
			$location.path('/batch/update/' + $scope.listFilter.batch_number);
		};

		$scope.goto = function (action) {
			if (!_.isEmpty($scope.selected_item))
				return;
			if (action === undefined)
				return;
			if (_.countBy(_.values($scope.selected_items), function (n) {
				return n === true;
			}).true > 1) {
				alert("Please select only one item");
				return;
			}
			var selected;
			_.forEach($scope.selected_items, function (v, k) {
				if (k)
					selected = k;
				return false;
			});
			$location.path('/' + $routeParams.collection + '/' + action + '/' + selected);
		};

		$scope.page = function (page) {
			if (page === $scope.pager.current)
				return;
			$location.search({page: page, size: $scope.pager.size});
		};
		$scope.nextPage = function () {
			if ($scope.pager.current === $scope.pager.count)
				return;
			$scope.page($scope.pager.current + 1);
		};
		$scope.prevPage = function () {
			if ($scope.pager.current === 1)
				return;
			$scope.page($scope.pager.current - 1);
		};
		$scope.lastPage = function () {
			if ($scope.pager.current === $scope.pager.count)
				return;
			$scope.page($scope.pager.count);
		};
		$scope.firstPage = function () {
			if ($scope.pager.current === 1)
				return;
			$scope.page(1);
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

		$scope.setListSize = function (size) {
			$location.search({size: size});
		};

		$scope.init = function () {
			var params = {
				coll: $routeParams.collection
			};
			$scope.listFilter = {};
			$scope.availableListSizes = [10, 50, 100, 500, 1000];
			$scope.active = $routeParams.collection;
			$scope.selected_items = {};
			if ($location.search()) {
				if ($location.search().page) {
					params.page = parseInt($location.search().page, 10);
				} else {
					params.page = 1;
				}
				if ($location.search().size) {
					params.size = parseInt($location.search().size, 10);
				} else {
					params.size = 100;
				}
			}
			Database.getCollectionItems(params).then(getCollectionItems);
		};
	}]);