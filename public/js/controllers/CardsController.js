app.controller('CardsController', ['$scope', '$window', '$routeParams', 'Database', '$controller', '$http',
	function ($scope, $window, $routeParams, Database, $controller, $http) {
		'use strict';

		$controller('EditController', {$scope: $scope});

		$scope.save = function (redirect) {
			$scope.err = {};
			//Database.saveEntity(params).then(function (res) {
			var postData = {
				method: ($scope.action === "new" ? 'create' : 'update')
			};
			var entData = {
				batch_number: $scope.entity.batch_number,
				serial_number: $scope.entity.serial_number,
				charging_plan_name: $scope.entity.charging_plan_name,
				service_provider: $scope.entity.service_provider,
				to: $scope.entity.to
			};
			if ($scope.card_status !== $scope.entity.status)
				entData.status = $scope.entity.status;
			if ($scope.action === "new")
				postData.cards = [JSON.stringify(entData)];
			else {
				postData.query = JSON.stringify({
					serial_number: $scope.entity.serial_number,
					batch_number: $scope.entity.batch_number
				});
				postData.update = JSON.stringify(entData);
			}
			$http.post(baseUrl + '/api/cards', postData).then(function (res) {
				if (redirect) {
					$window.location = baseUrl + '/admin/' + $routeParams.collection;
				}
			}, function (err) {
				$scope.err = err;

			});
		};

		$scope.isStatusDisabled = function (status) {
			var curr_card_status = $scope.card_status.toLowerCase();
			if (status === undefined)
				return true;
			if ($scope.card_status === undefined)
				return false;
			status = status.toLowerCase();
			// idle -> (active optional) -> [expired,stolen,disqualified,used]
			// don't allow going backwards
			if (curr_card_status === "idle")
				return false;
			if (curr_card_status === "active" && status === "idle")
				return true;
			if (curr_card_status === "active")
				return false;
			if (status === "idle" || status === "active")
				return true;
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
					$scope.entity.to = new Date($scope.entity.to.sec * 1000);
				}
				$scope.cardStatuses = ["Idle", "Active", "Disqualified", "Used", "Expired", "Stolen"];
				$scope.title = _.capitalize($scope.action) + " Card " + $scope.entity.serial_number;
				angular.element('title').text("BillRun - " + $scope.title);
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