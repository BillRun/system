app.controller('RatesController', ['$scope', 'Database', '$controller', '$location', '$anchorScroll', '$timeout', '$rootScope', '$http', '$window', '$uibModal',
	function ($scope, Database, $controller, $location, $anchorScroll, $timeout, $rootScope, $http, $window, $uibModal) {
		'use strict';

		$controller('EditController', {$scope: $scope});

		$scope.save = function () {
			$scope.err = {};
			var params = {
				entity: $scope.entity,
				coll: "rates",
				type: $scope.action,
				duplicate_rates: ($scope.duplicate_rates ? $scope.duplicate_rates.on : false),
			};
			if (params.entity.from) {
				params.entity.from = moment(params.entity.from).startOf('day').format();
			}
			Database.saveEntity(params).then(function (res) {
				$window.location = baseUrl + '/admin/rates';
			}, function (err) {
				$scope.err = err;
			});
		};

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
			if (r)
				$scope.entity.params.prefix.splice(prefixIndex, 1);
		};

		$scope.isPrefixExists = function (prefixIndex) {
			if (prefixIndex === undefined)
				return;
			var prefix = $scope.entity.params.prefix[prefixIndex];
			_.forEach($scope.entity.params.prefix, function (_pref, _index) {
				if (_index !== prefixIndex && prefix === _pref) {
					alert("Prefix '" + prefix + "' already exists in this rate");
					return;
				}
			});
			var key = $scope.entity.key;
			Database.getRatesWithSamePrefix({prefix: prefix, key: key}).then(function (res) {
				var rates = res.data;
				if (rates.length) {
					alert("Prefix '" + prefix + "' already exists in the following rate\/s: " + rates);
				}
			});
		};
		
		$scope.addMcc = function () {
			if ($scope.entity.params.mcc === undefined)
				$scope.entity.params.mcc = [];
			$scope.entity.params.mcc.push('');
		};

		$scope.deleteMcc = function (mccIndex) {
			if (mccIndex === undefined)
				return;
			var r = confirm("Are you sure you want to remove mcc " + $scope.entity.params.mcc[mccIndex]);
			if (r)
				$scope.entity.params.mcc.splice(mccIndex, 1);
		};

		$scope.isMccExists = function (mccIndex) {
			if (mccIndex === undefined)
				return;
			var mcc = $scope.entity.params.mcc[mccIndex];
			_.forEach($scope.entity.params.mcc, function (_mcc, _index) {
				if (_index !== mccIndex && mcc === _mcc) {
					alert("Mcc '" + mcc + "' already exists in this rate");
					return;
				}
			});
			var key = $scope.entity.key;
			Database.getRatesWithSameMcc({mcc: mcc, key: key}).then(function (res) {
				var rates = res.data;
				if (rates.length) {
					alert("Mcc '" + mcc + "' already exists in the following rate\/s: " + rates);
				}
			});
		};
		
		$scope.addMsc = function () {
			if ($scope.entity.params.msc === undefined)
				$scope.entity.params.msc = [];
			$scope.entity.params.msc.push('');
		};

		$scope.deleteMsc = function (mscIndex) {
			if (mscIndex === undefined)
				return;
			var r = confirm("Are you sure you want to remove msc " + $scope.entity.params.msc[mscIndex]);
			if (r)
				$scope.entity.params.msc.splice(mscIndex, 1);
		};

		$scope.isMscExists = function (mscIndex) {
			if (mscIndex === undefined)
				return;
			var msc = $scope.entity.params.msc[mscIndex];
			_.forEach($scope.entity.params.msc, function (_msc, _index) {
				if (_index !== mscIndex && msc === _msc) {
					alert("Msc '" + msc + "' already exists in this rate");
					return;
				}
			});
			var key = $scope.entity.key;
			Database.getRatesWithSameMsc({msc: msc, key: key}).then(function (res) {
				var rates = res.data;
				if (rates.length) {
					alert("Msc '" + msc + "' already exists in the following rate\/s: " + rates);
				}
			});
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
				case 'video_call':
					ret = $scope.addVideoCallRate();
					break;
				case 'roaming_incoming_call':
					ret = $scope.addRoamingIncomingCallRate();
					break;
				case 'roaming_call':
					ret = $scope.addRoamingCallRate();
					break;
				case 'roaming_callback':
					ret = $scope.addRoamingCallbackRate();
					break;
				case 'roaming_callback_short':
					ret = $scope.addRoamingCallbackShortRate();
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
				case 'video_call':
					ret = $scope.deleteVideoCallRate(rateName);
					break;
				case 'roaming_incoming_call':
					ret = $scope.deleteRoamingIncomingCallRate(rateName);
					break;
				case 'roaming_call':
					ret = $scope.deleteRoamingCallRate(rateName);
					break;
				case 'roaming_callback':
					ret = $scope.deleteRoamingCallbackRate(rateName);
					break;
				case 'roaming_callback_short':
					ret = $scope.deleteRoamingCallbackShortRate(rateName);
					break;
				case 'sms':
					ret = $scope.deleteSMSRate(rateName);
					break;
				case 'data':
					ret = $scope.deleteDataRate(rateName);
					break;
                                case 'video_call':
                                        ret = $scope.deleteVideoCallRate(rateName);
                                        break;
			}
			return ret;
		};
		$scope.addCallRate = function () {
			if (!$scope.newRate.call || !$scope.newRate.call.name)
				return;
			if ($scope.entity.rates.call === undefined || angular.isArray($scope.entity.rates.call))
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
			if (r)
				delete $scope.entity.rates.call[rateName];
		};
		
		$scope.addVideoCallRate = function () {
			if (!$scope.newRate.video_call || !$scope.newRate.video_call.name)
				return;
			if ($scope.entity.rates.video_call === undefined || angular.isArray($scope.entity.rates.video_call))
				$scope.entity.rates.video_call = {};
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
			$scope.entity.rates.video_call[$scope.newRate.video_call.name] = newPriceInterval;
			$scope.shown.video_callRates[$scope.newRate.video_call.name] = true;
			$scope.newRate.video_call = {name: undefined};
		};

		$scope.deleteVideoCallRate = function (rateName) {
			if (!rateName)
				return;
			var r = confirm("Are you sure you want to remove " + rateName + "?");
			if (r)
				delete $scope.entity.rates.video_call[rateName];
		};
		
		$scope.addRoamingIncomingCallRate = function () {
			if (!$scope.newRate.roaming_incoming_call || !$scope.newRate.roaming_incoming_call.name)
				return;
			if ($scope.entity.rates.roaming_incoming_call === undefined || angular.isArray($scope.entity.rates.roaming_incoming_call))
				$scope.entity.rates.roaming_incoming_call = {};
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
			$scope.entity.rates.roaming_incoming_call[$scope.newRate.roaming_incoming_call.name] = newPriceInterval;
			$scope.shown.roaming_incoming_callRates[$scope.newRate.roaming_incoming_call.name] = true;
			$scope.newRate.roaming_incoming_call = {name: undefined};
		};

		$scope.deleteRoamingIncomingCallRate = function (rateName) {
			if (!rateName)
				return;
			var r = confirm("Are you sure you want to remove " + rateName + "?");
			if (r)
				delete $scope.entity.rates.roaming_incoming_call[rateName];
		};
		
		$scope.addRoamingCallRate = function () {
			if (!$scope.newRate.roaming_call || !$scope.newRate.roaming_call.name)
				return;
			if ($scope.entity.rates.roaming_call === undefined || angular.isArray($scope.entity.rates.roaming_call))
				$scope.entity.rates.roaming_call = {};
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
			$scope.entity.rates.roaming_call[$scope.newRate.roaming_call.name] = newPriceInterval;
			$scope.shown.roaming_callRates[$scope.newRate.roaming_call.name] = true;
			$scope.newRate.roaming_call= {name: undefined};
		};

		$scope.addRoamingCallbackRate = function () {
			if (!$scope.newRate.roaming_callback || !$scope.newRate.roaming_callback.name)
				return;
			if ($scope.entity.rates.roaming_callback === undefined || angular.isArray($scope.entity.rates.roaming_callback))
				$scope.entity.rates.roaming_callback = {};
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
			$scope.entity.rates.roaming_callback[$scope.newRate.roaming_callback.name] = newPriceInterval;
			$scope.shown.roaming_callbackRates[$scope.newRate.roaming_callback.name] = true;
			$scope.newRate.roaming_callback= {name: undefined};
		};

		$scope.addRoamingCallbackShortRate = function () {
			
			if (!$scope.newRate.roaming_callback_short || !$scope.newRate.roaming_callback_short.name)
				return;
			if ($scope.entity.rates.roaming_callback_short === undefined)
				$scope.entity.rates.roaming_callback_short = {};
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
			$scope.entity.rates.roaming_callback_short[$scope.newRate.roaming_callback_short.name] = newPriceInterval;
			$scope.shown.roaming_callback_shortRates[$scope.newRate.roaming_callback_short.name] = true;
			$scope.newRate.roaming_callback_short = {name: undefined};
			
		};

		$scope.deleteRoamingCallRate = function (rateName) {
			if (!rateName)
				return;
			var r = confirm("Are you sure you want to remove " + rateName + "?");
			if (r)
				delete $scope.entity.rates.roaming_call[rateName];
		};
		
		$scope.deleteRoamingCallbackRate = function (rateName) {
			if (!rateName)
				return;
			var r = confirm("Are you sure you want to remove " + rateName + "?");
			if (r)
				delete $scope.entity.rates.roaming_callback[rateName];
		};

		$scope.deleteRoamingCallbackShortRate = function (rateName) {
			if (!rateName)
				return;
			var r = confirm("Are you sure you want to remove " + rateName + "?");
			if (r)
				delete $scope.entity.rates.roaming_callback_short[rateName];
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
			if (r)
				delete $scope.entity.rates.data[rateName];
		};

		$scope.addSMSRate = function () {
			if (!$scope.newRate.sms || !$scope.newRate.sms.name)
				return;
			if ($scope.entity.rates.sms === undefined || angular.isArray($scope.entity.rates.sms))
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
			if (r)
				delete $scope.entity.rates.sms[rateName];
		};
		$scope.deleteVideoCallRate = function (rateName) {
			if (!rateName)
				return;
			var r = confirm("Are you sure you want to remove " + rateName + "?");
			if (r)
				delete $scope.entity.rates.video_call[rateName];
		};

		$scope.addCallIntervalPrice = function (rate) {
			if (rate.rate === undefined)
				rate.rate = [];
			if (rate.rate.length === 2)
				return;
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
			if (callRate.rate.length === 1)
				return;
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

		$scope.deleteVideoCallPlan = function (planIndex) {
			if (planIndex === undefined)
				return;
			$scope.entity.rates.video_call.plans.splice(planIndex, 1);
		};

		$scope.planExists = function (type, plan) {
			var ret = true;
			switch (type) {
				case 'call':
					ret = $scope.callPlanExists(plan);
					break;
				case 'video_call':
					ret = $scope.videoCallPlanExists(plan);
					break;
				case 'roaming_incoming_call':
					ret = $scope.roamingIncomingCallPlanExists(plan);
					break;
				case 'roaming_call':
					ret = $scope.roamingCallPlanExists(plan);
					break;
				case 'roaming_callback':
					ret = $scope.roamingCallbackPlanExists(plan);
					break;
				case 'roaming_callback_short':
					ret = $scope.roamingCallbackShortPlanExists(plan);
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
		$scope.videoCallPlanExists = function (plan) {
			if (plan && $scope.entity && $scope.entity.rates
					&& $scope.entity.rates.video_call && $scope.entity.rates.video_call[plan]) {
				return true;
			}
			return false;
		};
		$scope.roamingIncomingCallPlanExists = function (plan) {
			if (plan && $scope.entity && $scope.entity.rates
					&& $scope.entity.rates.roaming_incoming_call && $scope.entity.rates.roaming_incoming_call[plan]) {
				return true;
			}
			return false;
		};
		$scope.roamingCallPlanExists = function (plan) {
			if (plan && $scope.entity && $scope.entity.rates
					&& $scope.entity.rates.roaming_call && $scope.entity.rates.roaming_call[plan]) {
				return true;
			}
			return false;
		};
		$scope.roamingCallbackPlanExists = function (plan) {
			if (plan && $scope.entity && $scope.entity.rates
					&& $scope.entity.rates.roaming_callback && $scope.entity.rates.roaming_callback[plan]) {
				return true;
			}
			return false;
		};
		$scope.roamingCallbackShortPlanExists = function (plan) {
			if (plan && $scope.entity && $scope.entity.rates
					&& $scope.entity.rates.roaming_callback_short && $scope.entity.rates.roaming_callback_short[plan]) {
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

		$scope.titlize = function (str) {
			return _.capitalize(str.replace(/_/g, ' '));
		};

		$scope.displayFutureForInterconnect = function (ic) {
			if (ic.future)
				return "(future)";
			return "";
		};

		$scope.isInterconnect = function () {
			if (!_.result($scope.entity, "params.interconnect"))
				return false;
			return $scope.entity.params.interconnect;
		};

		$scope.showInterconnectDetails = function (interconnect, type, plan) {
			if (!interconnect)
				return;
			$http.get('/admin/getRate', {params: {interconnect_key: interconnect}}).then(function (res) {
				var modalInstance = $uibModal.open({
					//templateUrl: 'interconnectDetails.html',
					template: "	<div class='modal-header'>\
		<h3 class='modal-title'>{{interconnect.key}}</h3>\
	</div>\
	<div class='modal-body'>\
		<table class='table table-striped table-bordered data-rates-table'>\
			<thead>\
				<tr>\
					<th>Interval</th>\
					<th>Price</th>\
					<th>To</th>\
				</tr>\
			</thead>\
			<tbody>\
				<tr ng-repeat='rate in interconnect_rate'>\
					<td>{{rate.interval}}</td>\
					<td>{{rate.price}}</td>\
					<td>{{rate.to}}</td>\
			  </tr>\
			</tbody>\
		</table>\
	</div>\
	<div class='modal-footer'>\
		<button class='btn btn-primary' type='button' ng-click='ok()'>OK</button>\
	</div>",
					controller: function ($scope, $uibModalInstance, interconnect, type, plan) {
						$scope.interconnect = interconnect;
						$scope.plan = plan;
						$scope.type = type;
						if (_.isUndefined($scope.interconnect.rates[$scope.type][$scope.plan]))
							$scope.plan = "BASE";
						$scope.interconnect_rate = interconnect.rates[$scope.type][$scope.plan].rate;
						$scope.ok = function () {
							$uibModalInstance.dismiss('cancel');
						};
					},
					size: 'md',
					resolve: {
						interconnect: function () {
							return res.data.interconnect;
						},
						type: function () {
							return type;
						},
						plan: function () {
							return plan;
						}
					}
				});
			});
		};

		$scope.init = function () {
			$rootScope.spinner++;
			$scope.shown = {prefix: false,
				mcc: false,
				msc: false,
				callRates: [],
				smsRates: [],
				dataRates: [],
				video_callRates: [],
				roaming_incoming_callRates: [],
				roaming_callRates: [],
				roaming_callbackRates: [],
				roaming_callback_shortRates: []
			};
			$scope.advancedMode = false;
			$scope.initEdit(function (entity) {
				if (_.isEmpty(entity.rates)) {
					entity.rates = {};
				}
				if (_.isEmpty(entity.params)) {
					entity.params = {};
				}
				if ($scope.action === "close_and_new") {
					var tomorrow = new Date();
					tomorrow.setDate(tomorrow.getDate() + 1);
					entity.from = tomorrow;
				}
				$scope.title = _.capitalize($scope.action.replace(/_/g, " ")) + " " + $scope.entity.key + " Rate";
				angular.element('title').text("BillRun - " + $scope.title);
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
							$scope.shown.video_callRates[plans] = true;
							$scope.shown.roaming_incoming_callRates[plans] = true;
							$scope.shown.roaming_callRates[plans] = true;
							$scope.shown.roaming_callbackRates[plans] = true;
							$scope.shown.roaming_callback_shortRates[plans] = true;
							$location.hash(plans);
							$anchorScroll.yOffset = 120;
							$anchorScroll();
							$timeout(function () {
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
				$timeout(function () {
					$rootScope.spinner--;
				}, 0);
			});
			Database.getAvailableInterconnect().then(function (res) {
				$scope.availableInterconnect = res.data;
				$scope.availableInterconnect = [{future: false, key: ""}].concat($scope.availableInterconnect);
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
			if ($scope.action === "new")
				$scope.shown.prefix = true;
		};
	}]);