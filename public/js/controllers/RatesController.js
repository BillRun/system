app.controller('RatesController', ['$scope', '$routeParams', 'Database', '$controller',
  function ($scope, $routeParams, Database, $controller) {
    'use strict';

    $controller('EditController', {$scope: $scope});

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
        access: 0,
        interconnect: 0,
        unit: $scope.availableCallUnits[0],
        rate: [
          {
            interval: undefined,
            price: undefined,
            to: undefined
          }
        ]
      };
      $scope.entity.rates.call[$scope.newCallRate.name] = newPriceInterval;
      $scope.shown.callRates[$scope.newCallRate.name] = true;
      $scope.newCallRate = {name: undefined};
    };

    $scope.deleteCallRate = function (rateName) {
      if (!rateName)
        return;
      delete $scope.entity.rates.call[rateName];
    };

    $scope.addDataRate = function () {
      if (!$scope.newDataRate || !$scope.newDataRate.name)
        return;
      if ($scope.entity.rates.data === undefined)
        $scope.entity.rates.data = {};
      var newPriceInterval = {
        access: 0,
        interconnect: 0,
        unit: $scope.availableDataUnits[0],
        rate: [
          {
            interval: undefined,
            price: undefined,
            to: undefined
          }
        ]
      };
      $scope.entity.rates.data[$scope.newDataRate.name] = newPriceInterval;
      $scope.shown.dataRates[$scope.newDataRate.name] = true;
      $scope.newDataRate = {name: undefined};
    };

    $scope.deleteDataRate = function (rateName) {
      if (!rateName)
        return;
      delete $scope.entity.rates.data[rateName];
    };

    $scope.addSMSRate = function () {
      if (!$scope.newSMSRate || !$scope.newSMSRate.name)
        return;
      if ($scope.entity.rates.sms === undefined)
        $scope.entity.rates.sms = {};
      var newPriceInterval = {
        unit: "counter",
        access: 0,
        interconnect: 0,
        rate: [
          {
            interval: undefined,
            price: undefined,
            to: undefined
          }
        ]
      };
      $scope.entity.rates.sms[$scope.newSMSRate.name] = newPriceInterval;
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
    $scope.deleteCallIntervalPrice = function (interval_price, callRate) {
      callRate.rate = _.without(callRate.rate, interval_price);
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

    $scope.capitalize = function (str) {
      return _.capitalize(str);
    };

    $scope.init = function () {
      $scope.advancedMode = false;
      $scope.initEdit(function (entity) {
        if (_.isEmpty(entity.rates)) {
          entity.rates = {};
        }
        if (_.isEmpty(entity.params)) {
          entity.params = {};
        }
      });
      $scope.availableCallUnits = ['seconds', 'minutes'];
      $scope.availableDataUnits = ['bytes'];
      Database.getAvailablePlans().then(function (res) {
        $scope.availablePlans = res.data;
      });
      $scope.newOutCircuitGroup = {from: undefined, to: undefined};
      $scope.newRecordType = {value: undefined};
      $scope.newCallRate = {name: undefined};
      $scope.newCallPlan = {value: undefined};
      $scope.newSMSRate = {name: undefined};
      $scope.newSMSPlan = {value: undefined};
      $scope.newDataRate = {name: undefined};
      $scope.shown = {prefix: false,
        callRates: [],
        smsRates: [],
        dataRates: []
      };
      if ($scope.action === "new") $scope.shown.prefix = true;
    };
  }]);