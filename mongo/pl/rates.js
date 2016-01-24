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
	var _access = 0;
	tariff.INITIAL_CHARGE = Number(tariff.INITIAL_CHARGE);
	tariff.INITIAL_AMOUNT = Number(tariff.INITIAL_AMOUNT);
	tariff.ADD_CHARGE     = Number(tariff.ADD_CHARGE);
	tariff.ADD_AMOUNT     = Number(tariff.ADD_AMOUNT);
	if (typeof interconnect == 'object' && (interconnect.INITIAL_CHARGE != "0" || interconnect.ADD_CHARGE != "0")) {
		if (interconnect.ADD_CHARGE == "0") {
			_access = Number(interconnect.INITIAL_CHARGE);
		} else {
			_access = 0;
			_interconnect = interconnect.ADD_CHARGE;
			tariff.INITIAL_CHARGE += Number(interconnect.INITIAL_CHARGE);
			tariff.ADD_CHARGE     += Number(interconnect.ADD_CHARGE);
		}
	}

	print("create_tariff usaget : " + _usaget);
	print("create_tariff tariff : " + tariff.PP_TARIFF_NAME);
	if (tariff.INITIAL_AMOUNT == tariff.ADD_AMOUNT && tariff.INITIAL_CHARGE == tariff.ADD_CHARGE) {
		return {
			'access': _access,
			'unit' : _unit,
			'rate':     [{
				'to': 2147483647,
				'price': Number(tariff.ADD_CHARGE),
				'interval': Number(tariff.ADD_AMOUNT)
			}]
		};
	}
	return {
		'access':   _access,
		'unit' : _unit,
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

function _plan(plan_id, plan_name, usaget, shabbat, tariffs, tariffs_interconnect) {
	var unit_type
	if (shabbat) {
		unit_type = "13";
	} else {
		unit_type = "7";
	}
	var _tariff_ids = [];
	db.tmp_PP_PLAN.aggregate({$match:{"PP_PLAN_ID" : plan_id, TIME_TYPE:{$in:[unit_type]}}}, {$group:{_id:null, tariff_ids:{$addToSet:"$PP_TARIFF_ID"}}}).forEach(function(objx) {_tariff_ids = objx.tariff_ids;});
	_interconnect = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "1"})[0];
	obj10 = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "0"})[0];
	if (typeof obj10 == 'undefined') {
		if (shabbat) {
			return;
		} else {
			obj10 = {PP_TARIFF_NAME: "FAKE", INITIAL_CHARGE:0, INITIAL_AMOUNT:1, ADD_CHARGE:0, ADD_AMOUNT:1};
		}
	}
	if (typeof _interconnect != 'undefined') {
		_t = create_tariff(obj10, _interconnect);
		if (tariffs_interconnect[usaget] === undefined)
			tariffs_interconnect[usaget] = {};
		tariffs_interconnect[usaget][plan_name] = _t;
	}

	print('tariffs ids: ' + _tariff_ids.join());
	print("plan id: " + plan_id);
	print("plan : " + plan_name);
	_t = create_tariff(obj10);
	if (tariffs[usaget] === undefined)
		tariffs[usaget] = {};
	tariffs[usaget][plan_name] = _t;
}

function activity_and_plan(subtype, appid, usaget, shabbat, tariffs, tariffs_interconnect) {
	db.tmp_ACTIVITY_AND_PP_PLAN.find({"NEW_SUBTYPE_ID":subtype, "APPLICATION_ID" : appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
		function(obj8) {
			_plan(obj8.PP_PLAN_ID, 'BASE', usaget, shabbat, tariffs, tariffs_interconnect);
		}
	);
}

function cos_plans(subtype, appid, usaget, shabbat, tariffs, tariffs_interconnect) {
	db.tmp_COS_PLANS.find({"SUBTYPE_ID":subtype, "APPLICATION_ID" : appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
		function(obj4) {
			db.tmp_COS.find({"COS_ID" : obj4.COS_ID}).forEach(function(obj5) {_plan_name = obj5.COS_NAME;});
			_plan(obj4.PP_PLAN_ID, _plan_name, usaget, shabbat, tariffs, tariffs_interconnect);
		}
	);
}

function getUniqueArray(arr) {
	var hash = {}, result = [];
	for ( var i = 0, l = arr.length; i < l; ++i ) {
		if ( !hash.hasOwnProperty(arr[i]) ) {
			hash[ arr[i] ] = true;
			result.push(arr[i]);
		}
	}
	return result;
}

//db.tmp_PPS_PREFIXES.aggregate({$match:{BILLING_ALLOCATION:/012_URU/}}, {$group:{_id:"$BILLING_ALLOCATION", prefixes:{$addToSet:"$PPS_PREFIXES"}}}).forEach(
db.tmp_PPS_PREFIXES.aggregate({$group:{_id:"$BILLING_ALLOCATION", prefixes:{$addToSet:"$PPS_PREFIXES"}}}).forEach(
	function(obj1) {
		_rate_name = obj1._id;
		print("rate name: " + _rate_name);
		_prefixes = obj1.prefixes;
		_tariffs = {}; 
		_tariffs_interconnect = {};

//		======================================================================================================================================================
//		non shabbat
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
						_usaget = getUsageType(_appid);
						print("sub type: " + _subtype);
						print("app id: " + _appid);
						print("usaget: " + _usaget);
						
						activity_and_plan(_subtype, _appid, _usaget, false, _tariffs, _tariffs_interconnect);
						cos_plans(_subtype, _appid, _usaget, false, _tariffs, _tariffs_interconnect);
				
					}
				);
			}
		);

		_tariffs_shabbat = {};
		_tariffs_shabbat_interconnect = {};
//		======================================================================================================================================================
//		shabbat
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
						_usaget = getUsageType(_appid);
						print("sub type: " + _subtype);
						print("app id: " + _appid);
						print("usaget: " + _usaget);
						
						activity_and_plan(_subtype, _appid, _usaget, false, _tariffs_shabbat, _tariffs_shabbat_interconnect);
						cos_plans(_subtype, _appid, _usaget, false, _tariffs_shabbat, _tariffs_shabbat_interconnect);

					}
				);
			}
		);

		for (var i = 0; i < _prefixes.length; i++) {
			_prefixes[i] = _prefixes[i].replace(/^0000/g, "A");
		}
		_prefixes = getUniqueArray(_prefixes);

		_rate = {
			'from':    ISODate('2016-01-01'),
			'to':      ISODate('2099-12-31 23:59:59'),
			'key':     _rate_name.replace(/ |-/g, "_").toUpperCase(),
			'params':  {
				'prefix': _prefixes,
				'shabbat': false,
				'interconnect': false,
			},
			'rates': _tariffs
		};
		db.rates.insert(_rate);

		_rate = {
			'from':    ISODate('2016-01-01'),
			'to':      ISODate('2099-12-31 23:59:59'),
			'key':     _rate_name.replace(/ |-/g, "_").toUpperCase() + '_INTERCONNECT',
			'params':  {
				'prefix': _prefixes,
				'shabbat': false,
				'interconnect': true,
			},
			'rates': _tariffs_interconnect
		};
		db.rates.insert(_rate);

		_rate = {
			'from':    ISODate('2016-01-01'),
			'to':      ISODate('2099-12-31 23:59:59'),
			'key':     _rate_name.replace(/ |-/g, "_").toUpperCase() + '_SHABBAT',
			'params':  {
				'prefix': _prefixes,
				'shabbat': true,
				'interconnect': false,
			},
			'rates': _tariffs_shabbat
		};
		db.rates.insert(_rate);

		_rate = {
			'from':    ISODate('2016-01-01'),
			'to':      ISODate('2099-12-31 23:59:59'),
			'key':     _rate_name.replace(/ |-/g, "_").toUpperCase() + '_SHABBAT_INTERCONNECT',
			'params':  {
				'prefix': _prefixes,
				'shabbat': true,
				'interconnect': true,
			},
			'rates': _tariffs_shabbat_interconnect
		};
		db.rates.insert(_rate);

	}
);

// insert data manually (only 1 rates, 2 plans inside - BASE and one plan)
db.rates.insert({
	"from" : ISODate("2012-06-01T00:00:00Z"),
	"key" : "INTERNET_BILL_BY_VOLUME",
	"params" : {
		"sgsn_addresses" : /^(?=91\.135\.)/
	},
	"rates" : {
		"data" : {
			"BASE" : {
				"rate" : [
					{
						"to" : 2147483647,
						"price" : 0.000976,
						"interval" : 1024
					}
				],
				"unit" : "bytes"
			},
			"PP_PreTalk" : {
				"rate" : [
					{
						"to" : 2147483647,
						"price" : 0.000244,
						"interval" : 1024
					}
				],
				"unit" : "bytes"
			}
		}
	},
	"to" : ISODate("2113-08-28T17:23:55Z")
})