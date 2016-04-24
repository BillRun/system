app.controller('BatchController', ['$scope', '$window', '$routeParams', 'Database', '$location', '$controller',
	function ($scope, $window, $routeParams, Database, $location, $controller) {
		'use strict';

		$controller('EditController', {$scope: $scope});

		$scope.cancel = function () {
			$window.location = baseUrl + '/admin/cards';
		};
		$scope.save = function () {
			if ($location.search().cards) {
				var serial_numbers = {
					"$in": $location.search().cards
				};
			}
			var params = {
				entity: $scope.entity,
				coll: 'cards',
				batch: $scope.batch_no,
				type: $routeParams.action,
				serial_number: JSON.stringify(serial_numbers)
			};
			Database.saveEntity(params).then(function (res) {
				$window.location = baseUrl + '/admin/cards';
			}, function (err) {
				alert("Connection error!");
			});
		};

		$scope.isStatusDisabled = function (status) {
			if (status === undefined)
				return true;
			if ($scope.card_status === undefined)
				return false;
			// idle -> (active optional) -> [expired,stolen,disqualified,used]
			// disallow going backwards
			return false;
		};

		$scope.init = function () {
			$scope.action = $routeParams.action;
			$scope.batch_no = $routeParams.id;
			$scope.cardStatuses = ["Idle", "Active", "Disqualified", "Used", "Expired", "Stolen"];
			$scope.entity = {
				serial_numbers_from: undefined,
				serial_numbers_to: undefined,
				service_provider: undefined,
				status: undefined,
				charging_plan_name: undefined
			};
			Database.getAvailableServiceProviders().then(function (res) {
				$scope.availableServiceProviders = res.data;
			});
			Database.getAvailablePlans('charging').then(function (res) {
				$scope.availablePlans = res.data;
			});
		};
	}]);