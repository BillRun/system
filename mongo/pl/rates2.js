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
var _location_id, _subtype, _t, _find_tariff, _interconnect,_tariff_ids, _interconnect_ar = {}, _interconnect_shabbat, _interconnect_shabbat_ar = {}, _rate_name;
var _shabbat_cos_ar = [
	"PP_Haredim_Minute",
	"PP_Haredim_12",
	"PP_Haredim_Hul_Minute",
	"PP_Haredim_Hul_12",
	"PP_Haredim_69",
	"PP_Haredim_Hul_69",
	"PP_Haredim_Fix_49",
	"PP_Haredim_Flat_84"
];

////////////////////////////////////////////////////////////////////////////////////
// method to compare arrays
// Warn if overriding existing method
if(Array.prototype.equals)
    console.warn("Overriding existing Array.prototype.equals. Possible causes: New API defines the method, there's a framework conflict or you've got double inclusions in your code.");
// attach the .equals method to Array's prototype to call it on any array
Array.prototype.equals = function (array) {
    // if the other array is a falsy value, return
    if (!array)
        return false;

    // compare lengths - can save a lot of time 
    if (this.length != array.length)
        return false;

    for (var i = 0, l=this.length; i < l; i++) {
        // Check if we have nested arrays
        if (this[i] instanceof Array && array[i] instanceof Array) {
            // recurse into the nested arrays
            if (!this[i].equals(array[i]))
                return false;       
        }           
        else if (this[i] != array[i]) { 
            // Warning - two different object instances will never be equal: {x:20} != {x:20}
            return false;   
        }           
    }       
    return true;
}
// Hide method from for-in loops
Object.defineProperty(Array.prototype, "equals", {enumerable: false});
////////////////////////////////////////////////////////////////////////////////////

function create_tariff(tariff, interconnect, shabbat) {
	var _access = 0; var _amount, _amount2, ret;
	tariff.INITIAL_CHARGE = Number(tariff.INITIAL_CHARGE);
	tariff.INITIAL_AMOUNT = Number(tariff.INITIAL_AMOUNT);
	tariff.ADD_CHARGE     = Number(tariff.ADD_CHARGE);
	tariff.ADD_AMOUNT     = Number(tariff.ADD_AMOUNT);
	if (typeof interconnect.PP_TARIFF_NAME != 'undefined') {
		_interconnect_name = standardKey(interconnect.PP_TARIFF_NAME + '_INTERCONNECT');
		print("Interconnect: " + _interconnect_name);
		if (interconnect.PP_TARIFF_NAME.substring(0, 2) == '01') { 
			tariff = interconnect;
			_interconnect_name = null;
		}
	} else {
		print("No interconnect!!!");
		_interconnect_name = null;
	}

	print("create_tariff usaget : " + _usaget);
	print("create_tariff tariff : " + tariff.PP_TARIFF_NAME);
	_amount = tariff.INITIAL_AMOUNT;
	_amount2 = tariff.ADD_AMOUNT;
	
	if (tariff.INITIAL_AMOUNT == tariff.ADD_AMOUNT && tariff.INITIAL_CHARGE == tariff.ADD_CHARGE) {
		ret = {
			'access': _access,
			'unit' : _unit,
			'interconnect': _interconnect_name,
			'rate':     [{
				'to': 2147483647,
				'price': Number(tariff.ADD_CHARGE),
				'interval': Number(_amount2)
			}]
		};
	} else {
		ret = {
			'access':   _access,
			'unit' : _unit,
			'interconnect': _interconnect_name,
			'rate':     [{
				'to': Number(_amount),
				'price': Number(tariff.INITIAL_CHARGE),
				'interval': Number(_amount)
				},{
				'to': 2147483647,
				'price': Number(tariff.ADD_CHARGE),
				'interval': Number(_amount2)
			}]
		};
	}
	if (shabbat/* && (
			shabbat.INITIAL_AMOUNT != tariff.INITIAL_AMOUNT
			|| shabbat.ADD_AMOUNT != tariff.ADD_AMOUNT
			|| shabbat.INITIAL_CHARGE != tariff.INITIAL_CHARGE
			|| shabbat.ADD_CHARGE != tariff.ADD_CHARGE
		)*/) {
		print("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++");
		ret['shabbat'] = [{
				'to': Number(shabbat.INITIAL_AMOUNT),
				'price': Number(shabbat.INITIAL_CHARGE),
				'interval': Number(shabbat.INITIAL_AMOUNT)
				},{
				'to': 2147483647,
				'price': Number(shabbat.ADD_CHARGE),
				'interval': Number(shabbat.ADD_AMOUNT)
		}];
	}
	return ret;
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

function create_interconnect(interconnect_ar, interconnect, usaget, plan) {
	if (typeof interconnect_ar[interconnect.PP_TARIFF_NAME] == 'undefined') {
		interconnect_ar[interconnect.PP_TARIFF_NAME] = {};
	}

	interconnect_ar[interconnect.PP_TARIFF_NAME][usaget] = interconnect;

	obj = {
		'key':	   standardKey(interconnect.PP_TARIFF_NAME+'_INTERCONNECT'),
		'from':    ISODate('2016-03-01'),
		'to':      ISODate('2099-12-31 23:59:59'),
		'params' : {
//							'prefix': _prefixes,
			'interconnect' : true
		},
		'rates': {}
	};
	obj['rates'][usaget] = {
		'BASE' : {
			"access": 0,
			"unit": "seconds",
			"rate": [
				{
					"to": Number(interconnect.INITIAL_AMOUNT),
					"price": Number(interconnect.INITIAL_CHARGE),
					"interval": Number(interconnect.INITIAL_AMOUNT)
				},
				{
					"to": 2147483647,
					"price": Number(interconnect.ADD_CHARGE),
					"interval": Number(interconnect.ADD_AMOUNT)
				},
			]

		}
	};

	_upsert = {
			'$setOnInsert' : {
				'key':	   standardKey(interconnect.PP_TARIFF_NAME+'_INTERCONNECT'),
				'from':    ISODate('2016-03-01'),
				'to':      ISODate('2099-12-31 23:59:59'),
				'params' : {
//					'prefix': _prefixes,
					'interconnect' : true
				},
			},
			'$set' : {}
	};
	_upsert['$set'] = {};
	_upsert['$set']['rates.' + usaget + '.' + plan] = {
		"access": 0,
		"unit": "seconds",
		"rate": [
			{
				"to": Number(interconnect.INITIAL_AMOUNT),
				"price": Number(interconnect.INITIAL_CHARGE),
				"interval": Number(interconnect.INITIAL_AMOUNT)
			},
			{
				"to": 2147483647,
				"price": Number(_interconnect.ADD_CHARGE),
				"interval": Number(interconnect.ADD_AMOUNT)
			},
		]

	};
	db.rates.update({'key':standardKey(interconnect.PP_TARIFF_NAME+'_INTERCONNECT')} , _upsert, {upsert:1});
}

function _plan(plan_id, plan_name, usaget, tariffs) {
	print("===============");
	var unit_type = "7", unit_type_shabbat = "13";
	var _tariff_ids = [];
	db.tmp_PP_PLAN.aggregate({$match:{"PP_PLAN_ID" : plan_id, TIME_TYPE:{$in:[unit_type]}}}, {$group:{_id:null, tariff_ids:{$addToSet:"$PP_TARIFF_ID"}}}).forEach(function(objx) {_tariff_ids = objx.tariff_ids;});
	
	if (_shabbat_cos_ar.indexOf(plan_name) >= 0) {
		db.tmp_PP_PLAN.aggregate({$match: {"PP_PLAN_ID": plan_id, TIME_TYPE: {$in: [unit_type_shabbat]}}}, {$group: {_id: null, tariff_ids: {$addToSet: "$PP_TARIFF_ID"}}}).forEach(function (objy) {
			_shabbat_tariff_ids = objy.tariff_ids;
		});
		print(_rate_name + " shabbat tarrifs: " + _shabbat_tariff_ids.toSource());
		if (!_shabbat_tariff_ids.equals(_tariff_ids)) {
			print("special shabbat tarrifs");
			obj11 = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_shabbat_tariff_ids}, "CHARGE_TYPE" : "0"})[0];
//			print(obj11.toSource());
			_interconnect_shabbat = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_shabbat_tariff_ids}, "CHARGE_TYPE" : "1"})[0];
		} else {
			print("same shabbat tarrifs");
			obj11 = false;
			_interconnect_shabbat = false;
		}
	} else {
		obj11 = false;
		_interconnect_shabbat = false;
	}
	
	_interconnect = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "1"})[0];
	obj10 = db.tmp_PP_TARIFFS.find({"PP_TARIFF_ID": {$in:_tariff_ids}, "CHARGE_TYPE" : "0"})[0];
	if (typeof obj10 == 'undefined') {
		obj10 = {PP_TARIFF_NAME: "FAKE", INITIAL_CHARGE:0, INITIAL_AMOUNT:1, ADD_CHARGE:0, ADD_AMOUNT:1};
	}
	if (typeof _interconnect != 'undefined') {
		print('tariffs ids: ' + _tariff_ids.join());
		print("plan id: " + plan_id);
		print("plan : " + plan_name);
		print("interconnect name: " + _interconnect.PP_TARIFF_NAME);
		_t = create_tariff(obj10, _interconnect, obj11);
		if (tariffs[usaget] === undefined) {
			tariffs[usaget] = {};
		}
		tariffs[usaget][plan_name] = _t;
		if (_interconnect.PP_TARIFF_NAME.substring(0, 2) != '01' && 
				(
				typeof _interconnect_ar[_interconnect.PP_TARIFF_NAME] == 'undefined' 
				|| 
				typeof _interconnect_ar[_interconnect.PP_TARIFF_NAME][usaget] == 'undefined'
				)
			) {
				create_interconnect(_interconnect_ar, _interconnect, usaget, 'BASE');
			}
	} else {
		print('tariffs ids: ' + _tariff_ids.join());
		print("plan id: " + plan_id);
		print("plan : " + plan_name);
		_t = create_tariff(obj10, {}, obj11);
		if (tariffs[usaget] === undefined)
			tariffs[usaget] = {};
		tariffs[usaget][plan_name] = _t;
	}
}

function activity_and_plan(subtype, appid, usaget, tariffs) {
	db.tmp_ACTIVITY_AND_PP_PLAN.find({"NEW_SUBTYPE_ID":subtype, "APPLICATION_ID" : appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
		function(obj8) {
			_plan(obj8.PP_PLAN_ID, 'BASE', usaget, tariffs);
		}
	);
}

function cos_plans(subtype, appid, usaget, tariffs) {
	db.tmp_COS_PLANS.find({"SUBTYPE_ID":subtype, "APPLICATION_ID" : appid, PP_PLAN_ID:{$exists:1, $ne:''}}).forEach(
		function(obj4) {
			db.tmp_COS.find({"COS_ID" : obj4.COS_ID}).forEach(function(obj5) {_plan_name = obj5.COS_NAME;});
			_plan(obj4.PP_PLAN_ID, _plan_name, usaget, tariffs);
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

function isEmpty(obj) {
	return Object.keys(obj).length == 0;
}

function standardKey(rate_name) {
	return rate_name.replace(/ |-/g, "_").toUpperCase();
}

db.tmp_PPS_PREFIXES.aggregate({$match:{BILLING_ALLOCATION:/Voice Bezeq/}}, {$group:{_id:"$BILLING_ALLOCATION", prefixes:{$addToSet:"$PPS_PREFIXES"}}}).forEach(
//db.tmp_PPS_PREFIXES.aggregate({$group:{_id:"$BILLING_ALLOCATION", prefixes:{$addToSet:"$PPS_PREFIXES"}}}).forEach(
	function(obj1) {
	print("==========================================");
		_rate_name = obj1._id;
		print("rate name: " + _rate_name);
		_prefixes = obj1.prefixes;
		_tariffs = {}; 
		_tariffs_interconnect = {};

		for (var i = 0; i < _prefixes.length; i++) {
			_prefixes[i] = _prefixes[i].replace(/^0000/g, "A");
		}
		_prefixes = getUniqueArray(_prefixes);

//		======================================================================================================================================================
//		non shabbat
		db.tmp_Prefix_Allocation_ID_clear.find({$or:[{ALLOCATION_B: _rate_name}, {ALLOCATION_A: _rate_name, ALLOCATION_B: "Anywhere"}]}).forEach(
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
						
						activity_and_plan(_subtype, _appid, _usaget, _tariffs);
						cos_plans(_subtype, _appid, _usaget, _tariffs);
				
					}
				);
			}
		);

		_rate = {
			'from':    ISODate('2016-03-01'),
			'to':      ISODate('2099-12-31 23:59:59'),
			'key':     standardKey(_rate_name),
			'params':  {
				'prefix': _prefixes
			},
			'rates': _tariffs
		};
		db.rates.insert(_rate);

	}
);

// insert data manually (only 1 rates, 2 plans inside - BASE and one plan)
db.rates.insert({
	"key" : "INTERNET_BILL_BY_VOLUME",
	"from" : ISODate("2012-06-01T00:00:00Z"),
	"to" : ISODate("2099-08-28T17:23:55Z"),
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
	}
})
db.rates.remove({rates:{}});
var _interconnect_non_chargable = ["A_INTERCONNECT_BEZEQ_T1_INTERCONNECT", "A_INTERCONNECT_BEZEQ_T2_INTERCONNECT", "A_INTERCONNECT_JAWWAL_INTERCONNECT", "A_INTERCONNECT_OTHERS_INTERCONNECT", "A_INTERCONNECT_VATANIA_INTERCONNECT", "A_INTER_1700_T1_INTERCONNECT", "A_INTER_1700_T2_INTERCONNECT"];
db.rates.update({key:{$in:_interconnect_non_chargable}}, {$set:{"params.chargable": false}}, {multi:1})
db.rates.insert(
	{
		"from" : ISODate("2016-03-01"),
		"to" : ISODate("2099-12-31T23:59:59Z"),
		"key" : "FUNDIAL2",
		"params" : {
			"prefix" : [
				"60000"
			],
			"premium" : true
		},
		"rates" : {
			"call" : {
				"BASE" : {
					"access" : 0,
					"unit" : "seconds",
					"interconnect" : "A_ZERO_INTERCONNECT",
					"rate" : [
						{
							"to" : 20,
							"price" : 5.9,
							"interval" : 20
						},
						{
							"to" : 2147483647,
							"price" : 0,
							"interval" : 20
						}
					]
				}
			}
		}
	}
);
// set premium rates (exclude from some wallets)
//var _premium_rates = ["1700","1ST_CLASS_VPN","BEZEQ1","FOREIGN_DISCOUNT_2","FOREIGNERS","GPRS_LOCATION","INTERNET_BILL_BY_VOLUME","JFC_FREE_CALLS","NEPAL-VPN","PELEPHONE","PHIL-VPN","PROGENYB","PROGENYO","RL_FREE_CALLS_B","RL_FREE_CALLS_PEL","SHARON_VPN","SMS_BEZEQ","SMS_OTHER","SMS_PELE","TALK_VPN","VOICE_BEZEQ","VOICE_CELLCOM","VOICE_MIRS","VOICE_PARTNER","VOICE_RAMI_LEVY","VOICE_CELLULAR_ISRAEL","VOICEMAIL"];
//db.rates.update({key:{$nin:_premium_rates}, "params.interconnect": {$exists:0}}, {$set:{"params.premium": true}}, {multi:1});
