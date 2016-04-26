app.controller('SubscribersController', ['$scope', '$window', '$routeParams', 'Database', '$controller', 'utils', '$http',
	function ($scope, $window, $routeParams, Database, $controller, utils, $http) {
		'use strict';

		$controller('EditController', {$scope: $scope});

		$scope.save = function (redirect) {
			$scope.err = {};
			var entData = $scope.entity;
			var postData = {
				method: ($scope.action === "new" ? 'create' : 'update')
			};
			if ($scope.action === "new")
				postData.subscriber = JSON.stringify(entData);
			else {
				postData.query = JSON.stringify({
					sid: $scope.entity.sid
				});
				postData.update = JSON.stringify(entData);
			}
			$http.post(baseUrl + '/api/subscribers', postData).then(function (res) {
				if (res.data.status)
					$window.location = baseUrl + '/admin/subscribers';
				else
					// TODO: change to flash message
					alert(res.data.desc + " - " + res.data.details);
			});
		};

		$scope.addIMSI = function () {
			if ($scope.entity.imsi && $scope.entity.imsi.length >= 2) {
				return false;
			}
			var idx = _.findIndex($scope.entity.imsi, function (i) {
				return (_.trim(i) === '' || !_.trim(i));
			});

			if (idx > 0) {
				return;
			} else {
				if (_.isUndefined($scope.entity.imsi))
					$scope.entity.imsi = [];
				$scope.entity.imsi.push("");
			}

		};

		$scope.deleteIMSI = function (imsiIndex) {
			if (imsiIndex === undefined)
				return;
			$scope.entity.imsi.splice(imsiIndex, 1);
		};

		$scope.init = function () {
			$scope.action = $routeParams.action;
			$scope.entity = {imsi: []};
			$scope.availableBalanceTypes = [];
			var params = {
				coll: $routeParams.collection,
				id: $routeParams.id,
				type: $routeParams.action
			};
			Database.getEntity(params).then(function (res) {
				$scope.entity = res.data.entity;
				$scope.title = _.capitalize($scope.action.replace(/_/g, " ")) + " Subscriber " + $scope.entity.sid;
				angular.element('title').text("BillRun - " + $scope.title);
				$scope.autorized_write = res.data.authorized_write;
				if ($scope.entity.imsi && _.isString($scope.entity.imsi)) {
					$scope.entity.imsi = [$scope.entity.imsi];
				}
				$scope.authorized_write = res.data.authorized_write;
			}, function (err) {
				alert("Connection error!");
			});
			Database.getAvailableServiceProviders().then(function (res) {
				$scope.availableServiceProviders = res.data;
			});
			Database.getAvailablePlans().then(function (res) {
				$scope.availablePlans = res.data;
			});
			$scope.availableLanguages = ["Hebrew", "English", "Arabic", "Russian", "Thai"];
			$scope.availableChargingTypes = ["prepaid", "postpaid"];
		};
	}]);