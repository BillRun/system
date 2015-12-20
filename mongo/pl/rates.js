// create temp indexes to make it quick
db.tmp_COS_PLANS.ensureIndex({'SUBTYPE_ID': 1, APPLICATION_ID: 1 }, { unique: false , sparse: false, background: true });
db.tmp_PP_TARIFFS.ensureIndex({"PP_TARIFF_ID": 1}, { unique: false , sparse: false, background: true });
db.tmp_COS.ensureIndex({"COS_ID": 1}, { unique: false , sparse: false, background: true });
db.tmp_Prefix_Allocation_ID_clear.ensureIndex({"ALLOCATION_B": 1}, { unique: false , sparse: false, background: true });
db.tmp_SUBTYPE_TRANSLATION.ensureIndex({"LOCATION_ID": 1}, { unique: false , sparse: false, background: true });
db.tmp_PP_PLAN.ensureIndex({"PP_PLAN_ID": 1}, { unique: false , sparse: false, background: true });

// long script to migrate the rates pricing
var _rate, _plan, _plan_name, _usaget, _appid, _prefixes = [], _tariffs = {}, _cos_id, _unit;
var _location_id, _subtype, _t, _find_tariff;

function create_tariff(tariff) {

	if (tariff.PP_TARIFF_NAME.toLowerCase().contains('inter ') !== false 
			|| tariff.PP_TARIFF_NAME.toLowerCase().contains('interconnect') !== false
			|| tariff.PP_TARIFF_NAME.toLowerCase().contains('zero_airtime') !== false
		) {
		return false;
	}
	switch(tariff.UNIT_TYPE) {
		case "1":
			_usaget = 'cost';
			_unit = 'NIS';
			break;
		case "2":
			// Seconds
			_unit = 'seconds';
			_usaget = 'call';
			break;
		case "3":
			// OCTET
			_unit = 'bytes';
			_usaget = 'data';
			break;
		case "4":
			// SMS
			_unit = 'counter';
			_usaget = 'sms';
			break;
	}
	print("usaget : " + _usaget);
	print("tariff : " + tariff.PP_TARIFF_NAME);
	if (tariff.INITIAL_AMOUNT == tariff.ADD_AMOUNT && tariff.INITIAL_CHARGE == tariff.ADD_CHARGE) {
		return [{
			'access': 0,
			'unit' : _unit,
			'rate':     {
				'to': 2147483647,
				'price': tariff.ADD_CHARGE,
				'interval': tariff.ADD_AMOUNT
			}
		}];
	}
	return [{
		'access':   0,
		'unit' : _unit,
		'rate':     {
			'to': tariff.INITIAL_AMOUNT,
			'price': tariff.INITIAL_CHARGE,
			'interval': tariff.INITIAL_AMOUNT
		}
	},{
		'access':   0,
		'unit' : _unit,
		'rate':     {
			'to': 2147483647,
			'price': tariff.ADD_CHARGE,
			'interval': tariff.ADD_AMOUNT
		}

	}];

}

//db.tmp_PPS_PREFIXES.aggregate({$match:{BILLING_ALLOCATION:/^Bezeq144$/}}, {$group:{_id:"$BILLING_ALLOCATION", prefixes:{$addToSet:"$PPS_PREFIXES"}}}).forEach(
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
						print("sub type: " + _subtype);
						print("app id: " + _appid);
						
						// General tariff
						db.tmp_ACTIVITY_AND_PP_PLAN.find({"NEW_SUBTYPE_ID":_subtype, "APPLICATION_ID" : _appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
							function(obj8) {
								_plan_name = 'BASE';
								_find_tariff = false;
								db.tmp_PP_PLAN.find({"PP_PLAN_ID": obj8.PP_PLAN_ID}).sort({TIME_TYPE: -1}).limit(1).forEach(
									function (obj9) {
										if (_find_tariff !== false) return;
										db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": obj9.PP_TARIFF_ID}).forEach(
											function (obj10) {
												print("plan : " + _plan_name);
												_t = create_tariff(obj10);
												if (_t !== false) {
													_find_tariff = true;
													if (_tariffs[_usaget] === undefined)
														_tariffs[_usaget] = {};
													if (_tariffs[_usaget][_plan_name] === undefined)
														_tariffs[_usaget][_plan_name] = [];
													Array.prototype.push.apply(_tariffs[_usaget][_plan_name], _t);
												}
											}
										);
									}
								);

							}
						);

						// plan base tariffs
						db.tmp_COS_PLANS.find({"SUBTYPE_ID":_subtype, "APPLICATION_ID" : _appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
							function(obj4) {
								db.tmp_COS.find({"COS_ID" : obj4.COS_ID}).forEach(function(obj5) {_plan_name = obj5.COS_NAME;});
								_find_tariff = false;
								db.tmp_PP_PLAN.find({"PP_PLAN_ID" : obj4.PP_PLAN_ID}).sort({TIME_TYPE:-1}).forEach(
									function(obj6) {
										if (_find_tariff !== false) return;
										db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID":obj6.PP_TARIFF_ID}).forEach(
											function(obj7) {
												print("plan : " + obj4.COS_ID + " " + _plan_name + " " + obj4.PP_PLAN_ID);
												_t = create_tariff(obj7);
												if (_t !== false) {
													_find_tariff = true;
													if (_tariffs[_usaget] === undefined) _tariffs[_usaget] = {};
													if (_tariffs[_usaget][_plan_name] === undefined) _tariffs[_usaget][_plan_name] = [];
													Array.prototype.push.apply(_tariffs[_usaget][_plan_name], _t);
												}
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
				'prefix': _prefixes
			},
			'rates': _tariffs
		};
		db.rates.insert(_rate);
	}
);