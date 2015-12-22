app.controller('RatesController', ['$scope', '$http', '$window', '$routeParams', 'Database',
  function ($scope, $http, $window, $routeParams, Database) {
    'use strict';

    $scope.addOutCircuitGroup = function () {
      if ($scope.newOutCircuitGroup.to === undefined && $scope.newOutCircuitGroup.from === undefined)
        return;
      $scope.entity.params.out_circuit_group.push($scope.newOutCircuitGroup);
      $scope.newOutCircuitGroup = {to: undefined, from: undefined};
    };

    $scope.deleteOutCircuitGroup = function (outCircuitGroup) {
      if (outCircuitGroup === undefined)
        return;
      $scope.entity.params.out_circuit_group = _.without($scope.entity.params.out_circuit_group, outCircuitGroup);
    };

    $scope.addPrefix = function () {
      if ($scope.entity.params.prefix === undefined)
        $scope.entity.params.prefix = [];
      $scope.entity.params.prefix.push('');
    };

    $scope.deletePrefix = function (prefixIndex) {
      if (prefixIndex === undefined)
        return;
      $scope.entity.params.prefix.splice(prefixIndex, 1);
    };

    $scope.addRecordType = function () {
      if (!$scope.newRecordType || !$scope.newRecordType.value)
        return;
      if ($scope.entity.params.record_type === undefined)
        $scope.entity.params.record_type = [];
      $scope.entity.params.record_type.push($scope.newRecordType.value);
      $scope.newRecordType.value = undefined;
    };

    $scope.deleteRecordType = function (recordTypeIndex) {
      if (recordTypeIndex === undefined)
        return;
      $scope.entity.params.record_type.splice(recordTypeIndex, 1);
    };

    $scope.addCallRate = function () {
      if (!$scope.newCallRate || !$scope.newCallRate.name)
        return;
      if ($scope.entity.rates.call === undefined)
        $scope.entity.rates.call = {};
      var newPriceInterval = {
        rate: [
          {
            interval: undefined,
            price: undefined,
            to: undefined
          }
        ]
      };
      $scope.entity.rates.call[$scope.newCallRate.name] = [newPriceInterval];
      $scope.shown.callRates[$scope.newCallRate.name] = true;
      $scope.newCallRate = {name: undefined};
    };

    $scope.deleteCallRate = function (rateName) {
      if (!rateName)
        return;
      delete $scope.entity.rates.call[rateName];
    };

    $scope.addSMSRate = function () {
      if (!$scope.newSMSRate || !$scope.newSMSRate.name)
        return;
      if ($scope.entity.rates.sms === undefined)
        $scope.entity.rates.sms = {};
      $scope.entity.rates.sms[$scope.newSMSRate.name] = [{unit: "counter", rate: []}];
      $scope.shown.smsRates[$scope.newSMSRate.name] = true;
      $scope.newSMSRate = {name: undefined};
    };

    $scope.deleteSMSRate = function (rateName) {
      if (!rateName)
        return;
      delete $scope.entity.rates.sms[rateName];
    };

    $scope.addCallIntervalPrice = function (rate) {
      if (rate.rate === undefined)
        rate.rate = [];
      var newCallIntervalPrice = {
        interval: undefined,
        price: undefined,
        to: undefined
      };
      rate.rate.push(newCallIntervalPrice);
    };

    $scope.addSMSIntervalPrice = function (rate) {
      if (rate.rate === undefined)
        rate.rate = [];
      var newSMSIntervalPrice = {
        interval: undefined,
        price: undefined,
        to: undefined
      };
      rate.rate.push(newSMSIntervalPrice);
    };

    $scope.deleteSMSIntervalPrice = function (interval_price, smsRate) {
      smsRate.rate = _.without(smsRate.rate, interval_price);
    };

    $scope.addCallPlan = function () {
      if (!$scope.newCallPlan || !$scope.newCallPlan.value)
        return;
      if ($scope.entity.rates.call.plans === undefined)
        $scope.entity.rates.call.plans = [];
      $scope.entity.rates.call.plans.push($scope.newCallPlan.value);
      $scope.newCallPlan.value = undefined;
    };

    $scope.deleteCallPlan = function (planIndex) {
      if (planIndex === undefined)
        return;
      $scope.entity.rates.call.plans.splice(planIndex, 1);
    };

    $scope.addSMSPlan = function () {
      if (!$scope.newSMSPlan || !$scope.newSMSPlan.value)
        return;
      if ($scope.entity.rates.sms.plans === undefined)
        $scope.entity.rates.sms.plans = [];
      $scope.entity.rates.sms.plans.push($scope.newSMSPlan.value);
      $scope.newSMSPlan.value = undefined;
    };

    $scope.deleteSMSPlan = function (planIndex) {
      if (planIndex === undefined)
        return;
      $scope.entity.rates.sms.plans.splice(planIndex, 1);
    };

    $scope.callPlanExists = function (plan) {
      if (plan && $scope.entity && $scope.entity.rates
        && $scope.entity.rates.call && $scope.entity.rates.call[plan]) {
        return true;
      }
      return false;
    };
    $scope.smsPlanExists = function (plan) {
      if (plan && $scope.entity && $scope.entity.rates 
        && $scope.entity.rates.sms && $scope.entity.rates.sms[plan]) {
        return true;
      }
      return false;
    };

    $scope.cancel = function () {
      $window.location = baseUrl + '/admin/rates';
    };
    $scope.saveRate = function () {
      Database.saveEntity($scope.entity, 'rates').then(function (res) {
        $window.location = baseUrl + '/admin/rates';
      }, function (err) {
        alert("Danger! Danger! Beedeebeedeebeedee!");
      });
    };

    $scope.setAdvancedMode = function (mode) {
      $scope.advancedMode = mode;
    };

    $scope.init = function () {
      $scope.advancedMode = false;
      var params = {
        coll: 'rates',
        id: $routeParams.id
      };
      Database.getEntity(params).then(function (res) {
        $scope.entity = res.data;
        if (_.isEmpty($scope.entity.rates)) {
          $scope.entity.rates = {};
        }
      });
      $scope.availableCallUnits = ['seconds', 'minutes', 'hours'];
      Database.getAvailablePlans().then(function (res) {
        $scope.availablePlans = res.data;
      });
      $scope.newOutCircuitGroup = {from: undefined, to: undefined};
      $scope.newPrefix = {value: undefined};
      $scope.newRecordType = {value: undefined};
      $scope.newCallRate = {name: undefined};
      $scope.newCallPlan = {value: undefined};
      $scope.newSMSRate = {name: undefined};
      $scope.newSMSPlan = {value: undefined};
      $scope.shown = {prefix: false,
        callRates: [],
        smsRates: []
      };
    };
  }]);