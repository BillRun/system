app.controller('SubscribersAutoRenewController', ['$scope', '$controller', 'Database',
	function ($scope, $controller, Database) {
		'use strict';

		$controller('EditController', {$scope: $scope});

		$scope.cancel = function () {
			$window.location = baseUrl + '/admin/subscribersautorenewservices';
		};

		$scope.init = function () {
			$scope.initEdit(function (entity) {
				if (_.isObject(entity.last_renew_date)) {
					entity.last_renew_date = new Date(entity.last_renew_date.sec * 1000);
				}
				if (_.isObject(entity.next_renew_date)) {
					entity.next_renew_date = new Date(entity.next_renew_date.sec * 1000);
				}
				if (_.isObject(entity.creation_date)) {
					entity.creation_date = new Date(entity.creation_date.sec * 1000);
				}
				$scope.title = _.capitalize($scope.action) + " Subscriber Auto Renew";
				angular.element('title').text("BillRun - " + $scope.title);
			});
			$scope.intervals = ["month", "day"];
			Database.getAvailableServiceProviders().then(function (res) {
				$scope.availableServiceProviders = res.data;
			});
			Database.getAvailablePlans('charging').then(function (res) {
				$scope.availablePlans = res.data;
			});
		};
	}]);