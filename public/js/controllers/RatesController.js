app.controller('RatesController', ['$scope', '$http', '$window', function ($scope, $http, $window) {
  $scope.addOutCircuitGroup = function () {
	if ($scope.newOutCircuitGroup.to === undefined && $scope.newOutCircuitGroup.from === undefined) return;
	$scope.entity.params.out_circuit_group.push($scope.newOutCircuitGroup);
	$scope.newOutCircuitGroup = {to: undefined, from: undefined};
  };

  $scope.deleteOutCircuitGroup = function (outCircuitGroup) {
	if (outCircuitGroup === undefined) return;
	$scope.entity.params.out_circuit_group = _.without($scope.entity.params.out_circuit_group, outCircuitGroup);
  };

  $scope.addPrefix = function () {
	if (!$scope.newPrefix || $scope.newPrefix.value === undefined) return;
	if ($scope.entity.params.prefix === undefined)
		$scope.entity.params.prefix = [];
	$scope.entity.params.prefix.push($scope.newPrefix.value);
	$scope.newPrefix.value = undefined;
  };

  $scope.deletePrefix = function (prefixIndex) {
	if (prefixIndex === undefined) return;
	$scope.entity.params.prefix.splice(prefixIndex, 1);
  };

  $scope.addRecordType = function () {
	if (!$scope.newRecordType || !$scope.newRecordType.value) return;
	if ($scope.entity.params.record_type === undefined)
		$scope.entity.params.record_type = [];
	$scope.entity.params.record_type.push($scope.newRecordType.value);
	$scope.newRecordType.value = undefined;
  };

  $scope.deleteRecordType = function (recordTypeIndex) {
	  if (recordTypeIndex === undefined) return;
	  $scope.entity.params.record_type.splice(recordTypeIndex, 1);
  };

  $scope.addCallRate = function () {
	if (!$scope.newCallRate || !$scope.newCallRate.name || !$scope.newCallRate.params.rate.interval
			|| !$scope.newCallRate.params.rate.price || !$scope.newCallRate.params.rate.to)
		return;
	if ($scope.entity.rates.call === undefined)
		$scope.entity.rates.call = {};
    $scope.entity.rates.call[$scope.newCallRate.name] = [$scope.newCallRate.params];
	$scope.newCallRate = {
	  name: undefined,
	  params: {
	    unit: undefined,
		access: undefined,
		rate: {
		  interval: undefined,
		  price: undefined,
		  to: undefined
		}
	  }
	};
  };

  $scope.deleteCallRate = function(rateName) {
	  if (!rateName) return;
	  delete $scope.entity.rates.call[rateName];
  };

  $scope.addSMSRate = function () {
	if (!$scope.newSMSRate || !$scope.newSMSRate.name || !$scope.newSMSRate.params.rate.interval
			|| !$scope.newSMSRate.params.rate.price || !$scope.newSMSRate.params.rate.to)
		return;
	if ($scope.entity.rates.sms === undefined)
		$scope.entity.rates.sms = {};
    $scope.entity.rates.sms[$scope.newSMSRate.name] = [$scope.newSMSRate.params];
	$scope.newSMSRate = {
	  name: undefined,
	  params: {
	    unit: undefined,
		access: undefined,
		rate: {
		  interval: undefined,
		  price: undefined,
		  to: undefined
		}
	  }
	};
  };

  $scope.deleteSMSRate = function(rateName) {
	  if (!rateName) return;
	  delete $scope.entity.rates.sms[rateName];
  };

  $scope.addCallPlan = function () {
	if (!$scope.newCallPlan || !$scope.newCallPlan.value) return;
	if ($scope.entity.rates.call.plans === undefined)
		$scope.entity.rates.call.plans = [];
	$scope.entity.rates.call.plans.push($scope.newCallPlan.value);
	$scope.newCallPlan.value = undefined;
  };

  $scope.deleteCallPlan = function (planIndex) {
	if (planIndex === undefined) return;
	$scope.entity.rates.call.plans.splice(planIndex, 1);
  };

  $scope.addSMSPlan = function () {
	if (!$scope.newSMSPlan || !$scope.newSMSPlan.value) return;
	if ($scope.entity.rates.sms.plans === undefined)
		$scope.entity.rates.sms.plans = [];
	$scope.entity.rates.sms.plans.push($scope.newSMSPlan.value);
	$scope.newSMSPlan.value = undefined;
  };

  $scope.deleteSMSPlan = function (planIndex) {
	if (planIndex === undefined) return;
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
    $http.post($scope.form_data['baseUrl'] + '/admin/save', ajaxOpts).then(function (res) {
      $window.location = $scope.form_data.baseUrl + '/admin/' + $scope.form_data.collectionName;
    }, function (err) {
      alert("Danger! Danger! Beedeebeedeebeedee!");
    });
  };

  $scope.init = function () {
	$scope.entity = entity;
	if (_.isEmpty($scope.entity.rates)) {
		$scope.entity.rates = {};
	}
	$scope.form_data = form_data;
	$scope.availableCallUnits = ['seconds', 'minutes', 'hours'];
	$scope.availablePlans = available_plans;
	$scope.newOutCircuitGroup = {from: undefined, to: undefined};
	$scope.newPrefix = {value: undefined};
	$scope.newRecordType = {value: undefined};
	$scope.newCallRate = {name: undefined,
	  params: {
	    unit: undefined,
		  access: undefined,
		  rate: {
		    interval: undefined,
			price: undefined,
			to: undefined}
		}
	};
	$scope.newCallPlan = {value: undefined};
	$scope.newSMSRate = {interval: undefined,
		price: undefined,
		to: undefined};
	$scope.newSMSPlan = {value: undefined};
  };
}]);