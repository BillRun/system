app.controller('RatesController', ['$scope', '$http', '$window', function ($scope, $http, $window) {
  $scope.entity = entity;
  $scope.form_data = form_data;
  $scope.availableCallUnits = ['seconds', 'minutes', 'hours'];
  $scope.availablePlans = available_plans;
  $scope.newPrefix = {value: undefined};
  $scope.newRecordType = {value: undefined};
  $scope.newCallRate = {interval: undefined,
	  price: undefined,
	  to: undefined};
  $scope.newCallPlan = {value: undefined};
  $scope.newSMSRate = {interval: undefined,
	  price: undefined,
	  to: undefined};
  $scope.newSMSPlan = {value: undefined};
  
  $scope.addPrefix = function () {
	if (!$scope.newPrefix || !$scope.newPrefix.value) return;
	if ($scope.params.prefix === undefined)
		$scope.params.prefix = [];
	$scope.entity.params.prefix.push($scope.newPrefix.value);
	$scope.newPrefix.value = undefined;
  };

  $scope.addRecordType = function () {
	if (!$scope.newRecordType || !$scope.newRecordType.value) return;
	if ($scope.entity.params.record_type === undefined)
		$scope.entity.params.record_type = [];
	$scope.entity.params.record_type.push($scope.newRecordType.value);
	$scope.newRecordType.value = undefined;
  };
  
  $scope.addCallRate = function () {
	if (!$scope.newCallRate || !$scope.newCallRate.interval || !$scope.newCallRate.price || !$scope.newCallRate.to) return;
	if ($scope.entity.rates.call.rate === undefined)
		$scope.entity.rates.call.rate = [];
	$scope.entity.rates.call.rate.push($scope.newCallRate);
	$scope.newCallRate = {interval: undefined,
	  price: undefined,
	  to: undefined};	  
  };
  
  $scope.deleteCallRate = function(rate) {
	  if (!rate) return;
	  $scope.entity.rates.call.rate = _.without($scope.entity.rates.call.rate, rate);
  };
  
  $scope.addCallPlan = function () {
	if (!$scope.newCallPlan || !$scope.newCallPlan.value) return;
	if ($scope.entity.rates.call.plans === undefined)
		$scope.entity.rates.call.plans = [];
	$scope.entity.rates.call.plans.push($scope.newCallPlan.value);
	$scope.newCallPlan.value = undefined;
  };

  $scope.deleteCallPlan = function (planIndex) {
	if (!planIndex) return;
	$scope.entity.rates.call.plans.splice(planIndex, 1);
  };

  $scope.addSMSRate = function () {
	if (!$scope.newSMSRate || !$scope.newSMSRate.interval || !$scope.newSMSRate.price || !$scope.newSMSRate.to) return;
	if ($scope.entity.rates.sms.rate === undefined)
		$scope.entity.rates.sms.rate = [];
	$scope.entity.rates.sms.rate.push($scope.newSMSRate);
	$scope.newSMSRate = {interval: undefined,
	  price: undefined,
	  to: undefined};	  
  };

  $scope.deleteSMSRate = function (rate) {
	if (!rate) return;
	$scope.entity.rates.sms.rate = _.without($scope.entity.rates.sms.rate, rate);
  };

  $scope.addSMSPlan = function () {
	if (!$scope.newSMSPlan || !$scope.newSMSPlan.value) return;
	if ($scope.entity.rates.sms.plans === undefined)
		$scope.entity.rates.sms.plans = [];
	$scope.entity.rates.sms.plans.push($scope.newSMSPlan.value);
	$scope.newSMSPlan.value = undefined;
  };
  
  $scope.deleteSMSPlan = function (planIndex) {
	if (!planIndex) return;
	$scope.entity.rates.sms.plans.splice(planIndex, 1);
  };
  
  $scope.cancel = function () {
    $window.location = $scope.form_data.baseUrl + '/admin/' + $scope.form_data.collectionName;
  };
  $scope.saveRate = function () {
    var ajaxOpts = {
      id: $scope.entity._id,
      coll: $scope.form_data.collectionName,
      type: $scope.form_data.type,
      duplicate_rates: false,
      data: JSON.stringify($scope.entity)
    };
	$http.post($scope.form_data.baseUrl + '/admin/save', ajaxOpts).then(function (res) {
		$window.location = $scope.form_data.baseUrl + '/admin/' + $scope.form_data.collectionName;
	}, function (err) {
		alert("Danger! Danger! BEEDEE BEEDEE BEEDEE!");
	});
  };
}]);