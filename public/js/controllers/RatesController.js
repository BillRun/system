app.controller('RatesController', ['$scope', 'Database', '$controller', '$location', '$anchorScroll', '$timeout',
  function ($scope, Database, $controller, $location, $anchorScroll, $timeout) {
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
      var r = confirm("Are you sure you want to remove prefix " + $scope.entity.params.prefix[prefixIndex]);
      if (r) $scope.entity.params.prefix.splice(prefixIndex, 1);
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

    $scope.addRate = function (type) {
      var ret = true;
      switch (type) {
        case 'call':
          ret = $scope.addCallRate();
          break;
        case 'sms':
          ret = $scope.addSMSRate();
          break;
        case 'data':
          ret = $scope.addDataRate();
          break;
      }
      return ret;
    };
    $scope.deleteRate = function (type, rateName) {
      var ret = true;
      switch (type) {
        case 'call':
          ret = $scope.deleteCallRate(rateName);
          break;
        case 'sms':
          ret = $scope.deleteSMSRate(rateName);
          break;
        case 'data':
          ret = $scope.deleteDataRate(rateName);
          break;
      }
      return ret;
    };
    $scope.addCallRate = function () {
      if (!$scope.newRate.call || !$scope.newRate.call.name)
        return;
      if ($scope.entity.rates.call === undefined)
        $scope.entity.rates.call = {};
      var newPriceInterval = {
        access: 0,
        interconnect: '',
        unit: $scope.availableCallUnits[0],
        rate: [
          {
            interval: undefined,
            price: undefined,
            to: undefined
          }
        ]
      };
      $scope.entity.rates.call[$scope.newRate.call.name] = newPriceInterval;
      $scope.shown.callRates[$scope.newRate.call.name] = true;
      $scope.newRate.call = {name: undefined};
    };

    $scope.deleteCallRate = function (rateName) {
      if (!rateName)
        return;
      var r = confirm("Are you sure you want to remove " + rateName + "?");
      if (r) delete $scope.entity.rates.call[rateName];
    };

    $scope.addDataRate = function () {
      if (!$scope.newRate.data || !$scope.newRate.data.name)
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
      $scope.entity.rates.data[$scope.newRate.data.name] = newPriceInterval;
      $scope.shown.dataRates[$scope.newRate.data.name] = true;
      $scope.newRate.data = {name: undefined};
    };

    $scope.deleteDataRate = function (rateName) {
      if (!rateName)
        return;
      var r = confirm("Are you sure you want to remove " + rateName + "?");
      if (r) delete $scope.entity.rates.data[rateName];
    };

    $scope.addSMSRate = function () {
      if (!$scope.newRate.sms || !$scope.newRate.sms.name)
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
      $scope.entity.rates.sms[$scope.newRate.sms.name] = newPriceInterval;
      $scope.shown.smsRates[$scope.newRate.sms.name] = true;
      $scope.newRate.sms = {name: undefined};
    };

    $scope.deleteSMSRate = function (rateName) {
      if (!rateName)
        return;
      var r = confirm("Are you sure you want to remove " + rateName + "?");
      if (r) delete $scope.entity.rates.sms[rateName];
    };

    $scope.addCallIntervalPrice = function (rate) {
      if (rate.rate === undefined)
        rate.rate = [];
      if (rate.rate.length === 2) return;
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
    $scope.deleteCallIntervalPrice = function (callRate) {
      if (callRate.rate.length === 1) return;
      callRate.rate.pop();
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

    $scope.planExists = function (type, plan) {
      var ret = true;
      switch (type) {
        case 'call':
          ret = $scope.callPlanExists(plan);
          break;
        case 'sms':
          ret = $scope.smsPlanExists(plan);
          break;
        case 'data':
          ret = $scope.dataPlanExists(plan);
          break;
      }
      return ret;
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
    $scope.dataPlanExists = function (plan) {
      if (plan && $scope.entity && $scope.entity.rates
        && $scope.entity.rates.data && $scope.entity.rates.data[plan]) {
        return true;
      }
      return false;
    };

    $scope.capitalize = function (str) {
      return _.capitalize(str);
    };

    $scope.displayFutureForInterconnect = function (ic) {
      if (ic.future) return "(future)";
      return "";
    };

    $scope.isInterconnect = function () {
      if (!_.result($scope.entity, "params.interconnect")) return false;
      return $scope.entity.params.interconnect;
    };

    $scope.init = function () {
      $scope.shown = {prefix: false,
        callRates: [],
        smsRates: [],
        dataRates: []
      };
      $scope.advancedMode = false;
      $scope.initEdit(function (entity) {
        if (_.isEmpty(entity.rates)) {
          entity.rates = {};
        }
        if (_.isEmpty(entity.params)) {
          entity.params = {};
        }
        if ($location.search().plans && $location.search().plans.length) {
          var plans = JSON.parse($location.search().plans);
          if (plans) {
            _.remove(plans, function (e) {
              return e === "BASE";
            });
            if (plans.length === 1) {
              $scope.shown.callRates[plans] = true;
              $scope.shown.smsRates[plans] = true;
              $scope.shown.dataRates[plans] = true;
              $location.hash(plans);
              $anchorScroll.yOffset = 120;
              $anchorScroll();
              $timeout(function() {
                angular.element('#' + plans).addClass('animated flash');
              }, 100);
            }
          }
        }
      });
      $scope.availableCallUnits = ['seconds', 'minutes'];
      $scope.availableDataUnits = ['bytes'];
      Database.getAvailablePlans().then(function (res) {
        $scope.availablePlans = res.data;
      });
      Database.getAvailableInterconnect().then(function (res) {
        $scope.availableInterconnect = res.data;
      });
      $scope.newOutCircuitGroup = {from: undefined, to: undefined};
      $scope.newRecordType = {value: undefined};
      $scope.newRate = {
        call: {name: undefined},
        sms: {name: undefined},
        data: {name: undefined}
      };
      $scope.newCallPlan = {value: undefined};
      $scope.newSMSPlan = {value: undefined};
      if ($scope.action === "new") $scope.shown.prefix = true;
    };
  }]);