app.controller('PlansController', ['$scope', '$window', '$routeParams', 'Database', '$controller', '$location', '$rootScope', '$timeout',
	function ($scope, $window, $routeParams, Database, $controller, $location, $rootScope, $timeout) {
		'use strict';

		$controller('EditController', {$scope: $scope});

		$scope.addPlanInclude = function () {
			if ($scope.newInclude && $scope.newInclude.value && $scope.entity.include[$scope.newInclude.type] === undefined) {
				$scope.entity.include[$scope.newInclude.type] = $scope.newInclude.value;
				$scope.newInclude = {type: undefined, value: undefined};
			}
		};

		$scope.deleteInclude = function (name) {
			delete $scope.entity.include[name];
		};

		$scope.deleteGroupParam = function (group_name, param) {
			delete $scope.entity.include.groups[group_name][param];
		};

		$scope.addNewGroup = function () {
			if ($scope.entity.include.groups[$scope.newGroup.name]) {
				return;
			}
			$scope.entity.include.groups[$scope.newGroup.name] = {};
			$scope.newGroup.name = "";
		};

		$scope.deleteGroup = function (group_name) {
			delete $scope.entity.include.groups[group_name];
		};

		$scope.addGroupParam = function (group_name) {
			if (group_name && $scope.newGroupParam[group_name] && $scope.newGroupParam[group_name].value && $scope.entity.include.groups[group_name]) {
				$scope.entity.include.groups[group_name][$scope.newGroupParam[group_name].type] = $scope.newGroupParam[group_name].value;
				$scope.newGroupParam[group_name].type = undefined;
				$scope.newGroupParam[group_name].value = undefined;
			}
		};

		$scope.plansTemplate = function () {
			var type = $routeParams.type;
			$scope.type = type;
			if (type === 'recurring')
				type = 'charging';
			return 'views/plans/' + type + 'edit.html';
		};

		$scope.removeIncludeType = function (index) {
			var r = confirm("Are you sure you want to remove " + $scope.displayData.includeTypes[index].type + " rate?");
			if (!r)
				return;
			$scope.displayData.includeTypes.splice(index, 1);
		};
		$scope.removeIncludeCost = function (index) {
			$scope.entity.include.cost.splice(index, 1);
		};

		$scope.addIncludeType = function () {
			var new_include_type = {
				cost: undefined,
				usagev: undefined,
				pp_includes_name: "",
				pp_includes_external_id: "",
				type: "",
				period: {
					duration: undefined,
					unit: ""
				}
			};
			$scope.displayData.includeTypes.push(new_include_type);
		};

		$scope.includeTypeExists = function (include_type) {
			if (include_type === 'cost')
				return false;
			return !_.isUndefined($scope.entity.include[include_type]);
		};
		
		$scope.removeUnnecessaryData = function() {
			_.forEach($scope.entity.include, function(includeData, includeName) {
				if (!_.isUndefined(includeData['pp_include'])) {
					delete(includeData['pp_include']);
				} else {
					_.forEach(includeData, function (include) {
						if (!_.isUndefined(include.pp_include)) {
							delete(include.pp_include);
						}
					});
				}
			});
		};
		
		$scope.initDurationValue = function(includeType) {
			if (!_.isEmpty(includeType.period.duration)) {
				return;
			}
			if (includeType.pp_include.name === 'CORE BALANCE') {
				includeType.period.duration = 'UNLIMITED';
			} else {
				includeType.period.duration = '';
			}
		};

		$scope.save = function (redirect) {
			$scope.err = {};
			if (_.isEmpty($scope.displayData.includeTypes)) {
				delete $scope.entity.include;
			} else {
				$scope.entity.include = {};
				_.forEach($scope.displayData.includeTypes, function(includeType) {
					var include_type = includeType.type;
					if (_.isUndefined($scope.entity.include[include_type])) {
						$scope.entity.include[include_type] = includeType;
					} else if (_.isArray($scope.entity.include[include_type])) {
						$scope.entity.include[include_type].push(includeType);
					} else {
						$scope.entity.include[include_type] = [$scope.entity.include[include_type], includeType];
					}
				});
			}
			if ($scope.entity.type === "customer" && $scope.disallowed_rates) {
				if (_.isUndefined($scope.entity.disallowed_rates))
					$scope.entity.disallowed_rates = [];
				var filtered = _.filter($scope.availableRates, function (r) {
					return r.ticked;
				});
				$scope.entity.disallowed_rates = _.reduce(filtered,
						function (acc, dr) {
							acc.push(dr.name);
							return acc;
						}, []);
			}
			$scope.removeUnnecessaryData();
			var params = {
				entity: $scope.entity,
				coll: $routeParams.collection,
				type: $routeParams.action,
				duplicate_rates: ($scope.duplicate_rates ? $scope.duplicate_rates.on : false)
			};
			Database.saveEntity(params).then(function (res) {
				if (redirect) {
					$window.location = baseUrl + '/admin/' + $location.search().type + 'plans';
				}
			}, function (err) {
				$scope.err = err;
			});
		};
		$scope.cancel = function () {
			if ($scope.entity.type === "charging" && $scope.entity.recurring) {
				$window.location = baseUrl + '/admin/recurringplans';
				return;
			}
			$window.location = baseUrl + '/admin/' + $scope.entity.type + $routeParams.collection;
		};

		$scope.balanceName = function (id) {
			var found = _.find($scope.ppIncludes, function (bal) {
				return bal.external_id === parseInt(id, 10);
			});
			if (found)
				return found.name;
			return _.capitalize(id.replace(/_/, ' '));
		};

		$scope.getTDHeight = function (rate) {
			var height = 32;
			if (rate.price.calls && !_.isEmpty(rate.price.calls) && !_.isEmpty(rate.price.calls.rate)) {
				height *= rate.price.calls.rate.length;
			}
			if (rate.price.sms && !_.isEmpty(rate.price.sms) && !_.isEmpty(rate.price.sms.rate)) {
				height *= rate.price.sms.rate.length;
			}
			if (rate.price.data && !_.isEmpty(rate.price.data) && !_.isEmpty(rate.price.data.rate)) {
				height *= rate.price.data.rate.length;
			}
			return {
				height: height,
				width: "260px",
				padding: "6px"
			};
		};

		$scope.thresholdExists = function (pp) {
			return !_.isUndefined($scope.entity.pp_threshold[pp.external_id]);
		};

		$scope.addPPIncludeThreshold = function () {
			if ($scope.entity.pp_threshold[$scope.newPPIncludeThreshold.id])
				return;
			$scope.entity.pp_threshold[$scope.newPPIncludeThreshold.id] = 0;
			$scope.newPPIncludeThreshold.id = null;
		};

		$scope.removePPIncludeThreshold = function (pp) {
			var found = _.find($scope.ppIncludes, function (pp_include) {
				return parseInt(pp_include.external_id, 10) === parseInt(pp, 10);
			});
			if (!found)
				return;
			var r = confirm("Are you sure you want to remove threshold for " + found.name + "?");
			if (r) {
				delete $scope.entity.pp_threshold[pp];
			}
		};

		$scope.addNotification = function (id) {
			if (!id)
				return;
			var new_notification = {value: 0, type: "", msg: ""};
			$scope.entity.notifications_threshold[id].length ?
					$scope.entity.notifications_threshold[id].push(new_notification) :
					$scope.entity.notifications_threshold[id] = [new_notification];
		};

		$scope.removeNotification = function (id) {
			if (!id)
				return;
			$scope.entity.notifications_threshold[id].pop();
		};

		$scope.notificationForThresholdExists = function (pp) {
			if (!$scope.entity.notifications_threshold ||
					!$scope.entity.notifications_threshold[pp.external_id])
				return false;
			return $scope.entity.notifications_threshold[pp.external_id].length;
		};

		$scope.addThresholdNotification = function () {
			if ($scope.entity.notifications_threshold[$scope.newThresholdNotification.id] &&
					$scope.entity.notifications_threshold[$scope.newThresholdNotification.id].length)
				return;
			$scope.entity.notifications_threshold[$scope.newThresholdNotification.id] = [];
			$scope.entity.notifications_threshold[$scope.newThresholdNotification.id].push({value: 0, type: "", msg: ""});
			$scope.newThresholdNotification.id = null;
		};
		
		$scope.getPPIncludesIndex = function (pp_include_name, pp_include_external_id) {
				var index = -1;
				angular.forEach($scope.pp_includes, function(pp_include, _index) {
					if (pp_include.name == pp_include_name && pp_include.external_id == pp_include_external_id) {
						index = _index;
						return;
					}
				});
				return index;
		};
		
		$scope.getPPIncludesDefaultValue = function (includeType) {
			var index = $scope.getPPIncludesIndex(includeType.pp_includes_name, includeType.pp_includes_external_id);
			if (index > -1) {
				includeType.pp_include = $scope.pp_includes[index];
			}
		};

		$scope.updatePPIncludes = function (includeType) {
			includeType.pp_includes_name = includeType.pp_include.name;
			includeType.pp_includes_external_id = includeType.pp_include.external_id;
			delete(includeType.cost);
			delete(includeType.usagev);
			delete(includeType.value);
			includeType.period.duration = '';
			includeType.type = (includeType.pp_include.charging_by_usaget !== 'total_cost' ? includeType.pp_include.charging_by_usaget : 'cost')
		};

		$scope.init = function () {
			angular.element('.menu-item-' + $location.search().type + 'plans').addClass('active');
			var params = {
				coll: $routeParams.collection.replace(/_/g, ''),
				id: $routeParams.id,
				type: $routeParams.action
			};
			$scope.advancedMode = false;
			$scope.action = $routeParams.action;
			$scope.displayData = {
				includeTypes: []
			};
			$rootScope.spinner++;
			Database.getAvailablePPIncludes({full_objects: true}).then(function (res) {
				$scope.pp_includes = res.data.ppincludes;
				Database.getEntity(params).then(function (res) {
					if ($routeParams.action !== "new") {
						$scope.entity = res.data.entity;
						if ($scope.entity.type === "charging") {
							_.forEach(['sms', 'call', 'data', 'cost'], function (usaget) {
								if (_.isUndefined($scope.entity.include[usaget]))
									return;
								if (_.isArray($scope.entity.include[usaget])) {
									_.forEach($scope.entity.include[usaget], function (usage, i) {
										if (usage.period.unit === "month") {
											$scope.entity.include[usaget][i].period.unit = "months";
										}
										usage.type = usaget;
										$scope.displayData.includeTypes.push(usage);
									});
									$rootScope.spinner--;
									return;
								} else {
									var usage = $scope.entity.include[usaget];
									if (usage.period.unit === "month") {
											$scope.entity.include[usaget][i].period.unit = "months";
										}
										usage.type = usaget;
										$scope.displayData.includeTypes.push(usage);
								}
								if ($scope.entity.include[usaget].period.unit === "month")
									$scope.entity.include[usaget].period.unit = "months";
							});
						} else if ($scope.entity.type === "customer") {
							if ($scope.entity.data_from_currency) {
								if (_.isUndefined($scope.entity.max_currency)) {
									$scope.entity.max_currency = {
										cost: res.data.default_max_currency.cost,
										period: res.data.default_max_currency.period
									};
								} else {
									if (_.isUndefined($scope.entity.max_currency.cost))
										$scope.entity.max_currency.cost = res.data.default_max_currency.cost;
									if (_.isUndefined($scope.entity.max_currency.period))
										$scope.entity.max_currency.period = res.data.deafult_max_currency.period;
								}
							}
							$scope.disallowed_rates = _.reduce($scope.entity.disallowed_rates,
									function (acc, dr) {
										acc.push({name: dr, ticked: true});
										return acc;
									}, []);
							Database.getAvailableRates().then(function (res) {
								$scope.availableRates = _.reduce(res.data,
										function (acc, rd) {
											acc.push({name: rd,
												ticked: _.includes($scope.entity.disallowed_rates, rd) ?
														true :
														false
											});
											return acc;
										}, []);
							});
							$timeout(function () {
								$rootScope.spinner--;
							}, 0);
						}
						if (_.isUndefined($scope.entity.include) && $scope.entity.recurring != 1)
							$scope.entity.include = {};
						if ($routeParams.type === "customer" && !$scope.entity.pp_threshold)
							$scope.entity.pp_threshold = {};
					} else if ($location.search().type === "charging" || $routeParams.type === 'recurring') {
						$scope.entity = {
							"name": "",
							"external_id": "",
							"service_provider": "",
							"desc": "",
							"type": "charging",
							"operation": "inc",
							"charging_type": [],
							"from": new Date(),
							"to": new Date(),
							"include": {},
							"priority": "0"
						};
						if ($routeParams.type === "recurring") {
							$scope.entity.recurring = 1;
						}
					} else if ($routeParams.type === "customer") {
						$scope.entity = {
							"name": "",
							"from": new Date(),
							"to": new Date(),
							"type": "customer",
							"external_id": "",
							"external_code": "",
							"disallowed_rates": []
						};
					}
					$scope.plan_rates = res.data.plan_rates;
					$scope.authorized_write = res.data.authorized_write;
					$scope.title = _.capitalize($scope.action.replace(/_/g, " ")) + " " + $scope.entity.name + " " + _.capitalize($routeParams.type) + " Plan";
					angular.element('title').text("BillRun - " + $scope.title);
					$rootScope.spinner--;
				}, function (err) {
					alert("Connection error!");
				});
			});

			$scope.availableCostUnits = ['days', 'months'];
			$scope.availableDataInCurrencyPeriodUnits = ['day', 'week', 'month', 'year'];
			$scope.availableOperations = {
				set: {
					value: 'set',
					label: 'set'
				},
				inc: {
					value: 'inc',
					label: 'increment'
				}
			};
			$scope.availableChargingTypes = ['card', 'digital'];
			$scope.newIncludeType = {type: ""};
			$scope.newPPIncludeThreshold = {id: null};
			$scope.newThresholdNotification = {id: null};
			$scope.availableIncludeTypes = ['cost', 'data', 'sms', 'call'];
			Database.getAvailableServiceProviders().then(function (res) {
				$scope.availableServiceProviders = res.data;
			});
			Database.getAvailablePPIncludes({full_objects: true}).then(function (res) {
				$scope.ppIncludes = res.data.ppincludes;
			});
			$scope.action = $routeParams.action.replace(/_/g, ' ');
			$scope.plan_type = $routeParams.type;
			$scope.duplicate_rates = {on: ($scope.action === 'duplicate')};
			$scope.includeTypes = ['call', 'data', 'sms', 'mms'];
			$scope.groupParams = ["data", "call", "incoming_call", "incoming_sms", "sms"];
			$scope.newInclude = {type: undefined, value: undefined};
			$scope.newGroupParam = [];
			$scope.newGroup = {name: ""};
		};
	}]);
