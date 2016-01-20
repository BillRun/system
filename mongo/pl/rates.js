// create temp indexes to make it quick
db.tmp_COS_PLANS.ensureIndex({'SUBTYPE_ID': 1, APPLICATION_ID: 1 }, { unique: false , sparse: false, background: true });
db.tmp_PP_TARIFFS.ensureIndex({"PP_TARIFF_ID": 1}, { unique: false , sparse: false, background: true });
db.tmp_COS.ensureIndex({"COS_ID": 1}, { unique: false , sparse: false, background: true });
db.tmp_Prefix_Allocation_ID_clear.ensureIndex({"ALLOCATION_B": 1}, { unique: false , sparse: false, background: true });
db.tmp_SUBTYPE_TRANSLATION.ensureIndex({"LOCATION_ID": 1}, { unique: false , sparse: false, background: true });
db.tmp_PP_PLAN.ensureIndex({"PP_PLAN_ID": 1}, { unique: false , sparse: false, background: true });

// fix for the tariffs source table because intl is marked with the wrong charge type
//db.tmp_PP_TARIFFS.update({"PP_TARIFF_NAME":/^01/, "CHARGE_TYPE":"1"}, {$set:{"CHARGE_TYPE":"0"}}, {multi:1})

// long script to migrate the rates pricing
var _rate, _plan, _plan_name, _usaget, _appid, _prefixes = [], _tariffs = {}, _cos_id, _unit;
var _location_id, _subtype, _t, _find_tariff, _interconnect,_tariff_ids;

function create_tariff(tariff, interconnect) {

//	switch(tariff.UNIT_TYPE) {
//		case "1":
////			_usaget = 'cost';
//			_unit = 'NIS';
//			break;
//		case "2":
//			// Seconds
//			_unit = 'seconds';
////			_usaget = 'call';
//			break;
//		case "3":
//			// OCTET
//			_unit = 'bytes';
////			_usaget = 'data';
//			break;
//		case "4":
//			// SMS
//			_unit = 'counter';
////			_usaget = 'sms';
//			break;
//	}
	print("usaget : " + _usaget);
	print("tariff : " + tariff.PP_TARIFF_NAME);
	if (tariff.INITIAL_AMOUNT == tariff.ADD_AMOUNT && tariff.INITIAL_CHARGE == tariff.ADD_CHARGE) {
		return {
			'access': 0,
			'unit' : _unit,
			'interconnect': interconnect.INITIAL_CHARGE/interconnect.INITIAL_AMOUNT,
			'rate':     [{
				'to': 2147483647,
				'price': Number(tariff.ADD_CHARGE),
				'interval': Number(tariff.ADD_AMOUNT)
			}]
		};
	}
	return {
		'access':   0,
		'unit' : _unit,
		'interconnect': interconnect.INITIAL_CHARGE/interconnect.INITIAL_AMOUNT,
		'rate':     [{
			'to': Number(tariff.INITIAL_AMOUNT),
			'price': Number(tariff.INITIAL_CHARGE),
			'interval': Number(tariff.INITIAL_AMOUNT)
			},{
			'to': 2147483647,
			'price': Number(tariff.ADD_CHARGE),
			'interval': Number(tariff.ADD_AMOUNT)
		}]
	};

}

function getUsageType(app_id) {
	var _usaget;
	switch(app_id) {
		case "1":
			_usaget = 'call';
			_unit = 'seconds';
			break;
		case "2":
			// Seconds
			_usaget = 'sms';
			_unit = 'counter';
			break;
		case "3":
			// OCTET
			_usaget = 'video_call';
			_unit = 'seconds';
			break;
		case "4":
			// SMS
			_usaget = 'data';
			_unit = 'bytes';
			break;
		default:
			_usaget = 'unknown';
			break;
	}
	return _usaget;
}

//db.tmp_PPS_PREFIXES.aggregate({$match:{BILLING_ALLOCATION:/012_URUGUAY/}}, {$group:{_id:"$BILLING_ALLOCATION", prefixes:{$addToSet:"$PPS_PREFIXES"}}}).forEach(
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
						_usaget = getUsageType(_appid);
						print("usaget: " + _usaget);
						
						_tariff_ids = [];
						// General tariff
						db.tmp_ACTIVITY_AND_PP_PLAN.find({"NEW_SUBTYPE_ID":_subtype, "APPLICATION_ID" : _appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
							function(obj8) {
								_plan_name = 'BASE';
								db.tmp_PP_PLAN.aggregate({$match:{"PP_PLAN_ID" : obj8.PP_PLAN_ID, TIME_TYPE:{$in:["7"]}}}, {$group:{_id:null, tariff_ids:{$addToSet:"$PP_TARIFF_ID"}}}).forEach(function(objx) {_tariff_ids = objx.tariff_ids;});
//								if (typeof tariff_ids == 'undefined' || tariff_ids.length == 0) {
//									return;
//								}
								_interconnect = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "1"})[0];
								if (typeof _interconnect == 'undefined') {
									_interconnect = {INITIAL_CHARGE:0, INITIAL_AMOUNT:1};
								}
								obj10 = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "0"})[0];
								if (typeof obj10 == 'undefined') {
									obj10 = {PP_TARIFF_NAME: "FAKE", INITIAL_CHARGE:0, INITIAL_AMOUNT:1, ADD_CHARGE:0, ADD_AMOUNT:1};
								}
								print('tariffs ids: ' + _tariff_ids.join());
								print("plan id: " + obj8.PP_PLAN_ID);
								print("plan : " + _plan_name);
								print("tariffs id: " + _tariff_ids.join());
								_t = create_tariff(obj10, _interconnect);
								if (_tariffs[_usaget] === undefined)
									_tariffs[_usaget] = {};
								_tariffs[_usaget][_plan_name] = _t;
							}
						);
				
						_tariff_ids = [];

						// plan base tariffs
						db.tmp_COS_PLANS.find({"SUBTYPE_ID":_subtype, "APPLICATION_ID" : _appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
							function(obj4) {
								db.tmp_COS.find({"COS_ID" : obj4.COS_ID}).forEach(function(obj5) {_plan_name = obj5.COS_NAME;});
								db.tmp_PP_PLAN.aggregate({$match:{"PP_PLAN_ID" : obj4.PP_PLAN_ID, TIME_TYPE:{$in:["7"]}}}, {$group:{_id:null, tariff_ids:{$addToSet:"$PP_TARIFF_ID"}}}).forEach(function(objx) {_tariff_ids = objx.tariff_ids;});
//								if (typeof tariff_ids == 'undefined' || tariff_ids.length == 0) {
//									return;
//								}
								_interconnect = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "1"})[0];
								if (typeof _interconnect == 'undefined') {
									_interconnect = {INITIAL_CHARGE:0, INITIAL_AMOUNT:1};
								}
								obj10 = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "0"})[0];
								if (typeof obj10 == 'undefined') {
									return;
								}
								print('tariffs ids: ' + _tariff_ids.join());
								print("plan id: " + obj4.PP_PLAN_ID);
								print("plan : " + _plan_name);
								print("tariffs id: " + _tariff_ids.join());
								_t = create_tariff(obj10, _interconnect);
								if (_tariffs[_usaget] === undefined)
									_tariffs[_usaget] = {};
								_tariffs[_usaget][_plan_name] = _t;
							}
						);

					}
				);
			}
		);
		_rate = {
			'from':    ISODate('2015-12-01'),
			'to':      ISODate('2099-12-31 23:59:59'),
			'key':     _rate_name.replace(/ |-/g, "_").toUpperCase(),
			'params':  {
				'prefix': _prefixes,
				'shabbat': false,
			},
			'rates': _tariffs
		};
		db.rates.insert(_rate);
		
		
		
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
						_usaget = getUsageType(_appid);
						print("usaget: " + _usaget);
						
						_tariff_ids = [];
						// General tariff
						db.tmp_ACTIVITY_AND_PP_PLAN.find({"NEW_SUBTYPE_ID":_subtype, "APPLICATION_ID" : _appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
							function(obj8) {
								_plan_name = 'BASE';
								db.tmp_PP_PLAN.aggregate({$match:{"PP_PLAN_ID" : obj8.PP_PLAN_ID, TIME_TYPE:{$in:["13"]}}}, {$group:{_id:null, tariff_ids:{$addToSet:"$PP_TARIFF_ID"}}}).forEach(function(objx) {_tariff_ids = objx.tariff_ids;});
//								if (typeof tariff_ids == 'undefined' || tariff_ids.length == 0) {
//									return;
//								}
								_interconnect = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "1"})[0];
								if (typeof _interconnect == 'undefined') {
									_interconnect = {INITIAL_CHARGE:0, INITIAL_AMOUNT:1};
								}
								obj10 = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "0"})[0];
								if (typeof obj10 == 'undefined') {
									obj10 = {PP_TARIFF_NAME: "FAKE", INITIAL_CHARGE:0, INITIAL_AMOUNT:1, ADD_CHARGE:0, ADD_AMOUNT:1};
								}
								print('tariffs ids: ' + _tariff_ids.join());
								print("plan id: " + obj8.PP_PLAN_ID);
								print("plan : " + _plan_name);
								print("tariffs id: " + _tariff_ids.join());
								_t = create_tariff(obj10, _interconnect);
								if (_tariffs[_usaget] === undefined)
									_tariffs[_usaget] = {};
								_tariffs[_usaget][_plan_name] = _t;
							}
						);
				
						_tariff_ids = [];

						// plan base tariffs
						db.tmp_COS_PLANS.find({"SUBTYPE_ID":_subtype, "APPLICATION_ID" : _appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
							function(obj4) {
								db.tmp_COS.find({"COS_ID" : obj4.COS_ID}).forEach(function(obj5) {_plan_name = obj5.COS_NAME;});
								db.tmp_PP_PLAN.aggregate({$match:{"PP_PLAN_ID" : obj4.PP_PLAN_ID, TIME_TYPE:{$in:["13"]}}}, {$group:{_id:null, tariff_ids:{$addToSet:"$PP_TARIFF_ID"}}}).forEach(function(objx) {_tariff_ids = objx.tariff_ids;});
//								if (typeof tariff_ids == 'undefined' || tariff_ids.length == 0) {
//									return;
//								}
								_interconnect = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "1"})[0];
								if (typeof _interconnect == 'undefined') {
									_interconnect = {INITIAL_CHARGE:0, INITIAL_AMOUNT:1};
								}
								obj10 = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "0"})[0];
								if (typeof obj10 == 'undefined') {
									return;
								}
								print('tariffs ids: ' + _tariff_ids.join());
								print("plan id: " + obj4.PP_PLAN_ID);
								print("plan : " + _plan_name);
								print("tariffs id: " + _tariff_ids.join());
								_t = create_tariff(obj10, _interconnect);
								if (_tariffs[_usaget] === undefined)
									_tariffs[_usaget] = {};
								_tariffs[_usaget][_plan_name] = _t;
							}
						);

					}
				);
			}
		);
		_rate = {
			'from':    ISODate('2015-12-01'),
			'to':      ISODate('2099-12-31 23:59:59'),
			'key':     _rate_name.replace(/ |-/g, "_").toUpperCase() + '_SHABBAT',
			'params':  {
				'prefix': _prefixes,
				'shabbat': true,
			},
			'rates': _tariffs
		};
		_tt = true;
		db.rates.find({'rates':{$eq:_tariffs}}).limit(1).forEach(function() {_tt = false})
		if (_tt) {
			db.rates.insert(_rate);
		}
	}
);