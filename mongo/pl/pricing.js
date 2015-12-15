// create temp indexes to make it quick
db.tmp_COS_PLANS.ensureIndex({'SUBTYPE_ID': 1, APPLICATION_ID: 1 }, { unique: false , sparse: false, background: true });
db.tmp_PP_TARIFFS.ensureIndex({"PP_TARIFF_ID": 1}, { unique: false , sparse: false, background: true });
db.tmp_COS.ensureIndex({"COS_ID": 1}, { unique: false , sparse: false, background: true });
db.tmp_Prefix_Allocation_ID_clear.ensureIndex({"ALLOCATION_B": 1}, { unique: false , sparse: false, background: true });
db.tmp_SUBTYPE_TRANSLATION.ensureIndex({"LOCATION_ID": 1}, { unique: false , sparse: false, background: true });
db.tmp_PP_PLAN.ensureIndex({"PP_PLAN_ID": 1}, { unique: false , sparse: false, background: true });

// long script to migrate the rates pricing
var _rate, _plan, _plan_id, _usaget, _appid, _prefixes = [], _tariffs = {}, _cos_id;
var _location_id, _subtype;
db.tmp_PPS_PREFIXES.aggregate({$group:{_id:"$BILLING_ALLOCATION", prefixes:{$addToSet:"$PPS_PREFIXES"}}}).forEach(
	function(obj1) {
		_rate_name = obj1._id;
		print("rate name: " + _rate_name);
		_prefixes = obj1.prefixes;
		_tariffs = {};
		db.tmp_Prefix_Allocation_ID_clear.find({ALLOCATION_B: _rate_name}).forEach(
			function(obj2) {
				if (obj2.HOME_OPPS_ID == '0' && obj2.HOME_TPPS_ID == '0') {
					return;
				}
				if (obj2.ALLOCATION_B == 'Anywhere') {
					_location_id = obj2.HOME_TPPS_ID;
				} else {
					_location_id = obj2.HOME_OPPS_ID;
				}
				print("location id: " + _location_id);
				db.tmp_SUBTYPE_TRANSLATION.find({"LOCATION_ID" : _location_id, SPECIAL_FEATURE:"0"}).forEach(
					function(obj3) {
						_subtype = obj3.NEW_SUBTYPE;
						_appid = obj3.APPLICATION_ID;
						db.tmp_COS_PLANS.find({"SUBTYPE_ID":_subtype, "APPLICATION_ID" : _appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
							function(obj4) {
								db.tmp_COS.find({"COS_ID" : obj4.COS_ID}).forEach(function(obj5) {_plan_id = obj5.COS_NAME;});
								db.tmp_PP_PLAN.find({"PP_PLAN_ID" : obj4.PP_PLAN_ID}).sort({TIME_TYPE:-1}).limit(1).forEach(
									function(obj6) {
										db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID":obj6.PP_TARIFF_ID}).forEach(
											function(obj7) {
												switch(obj7.UNIT_TYPE) {
													case "1":
														_usaget = 'cost';
														break;
													case "2":
														// Seconds
														_usaget = 'call';
														break;
													case "3":
														// OCTET
														_usaget = 'data';
														break;
													case "4":
														// SMS
														_usaget = 'sms';
														break;
												}
												print("usaget : " + _usaget);
												print("plan : " + _plan_id);
												if (_tariffs[_usaget] === undefined) _tariffs[_usaget] = {};
												if (_tariffs[_usaget][_plan_id] === undefined) _tariffs[_usaget][_plan_id] = [];
												_tariffs[_usaget][_plan_id].push({
													'access':   obj7.INITIAL_CHARGE,
													'rate':     {
														'to': 2147483647,
														'price': obj7.ADD_CHARGE,
														'interval': obj7.ADD_AMOUNT
													}
												});
											}
										);
									}
								);
							}
						);
					}
				);
			}
		);
		_rate = {
			'from':    ISODate('2015-12-01'),
			'to':      ISODate('2099-12-31 23:59:59'),
			'key':     _rate_name,
			'params':  {
				'prefixes': _prefixes
			},
			'rates': _tariffs
		};
		db.rates.insert(_rate);
	}
);

// migrate customer plans (COS)
db.tmp_COS.find().forEach(
	function(obj) {
		db.plans.insert({
			name:obj.COS_NAME,
			from:ISODate('2015-12-01'),
			to:ISODate('2099-12-31 23:59:59'),
			type:'customer',
			external_id: obj.COS_ID,
			external_code: obj.COS_CODE,
		});
	}
);