var _rate, _plan, _plan_id, _usaget, _appid, _prefixes = [], _tariffs = {};
db.tmp_PP_TARIFFS.find({}).forEach(
	function(obj1) {
		print("tariff name: " + obj1.PP_TARIFF_NAME);
		_rate = {};
		_prefixes = [];
		print("tariff id: " + obj1.PP_TARIFF_ID);
		db.tmp_PP_PLAN.find({PP_TARIFF_ID:obj1.PP_TARIFF_ID}).sort({TIME_TYPE:-1}).limit(1).forEach(function (plan) {_plan_id = plan.PP_PLAN_ID});
		print("plan_id: " + _plan_id);
		if (!_plan_id) return;
		switch(obj1.UNIT_TYPE) {
			case "1":
				_appid = '0';
				_usaget = 'cost';
				break;
			case "2":
				// Seconds
				_appid = '1';
				_usaget = 'call';
				break;
			case "3":
				// OCTET
				_appid = '4';
				_usaget = 'data';
				break;
			case "4":
				// SMS
				_appid = '2';
				_usaget = 'sms';
				break;
		}
		db.tmp_ACTIVITY_AND_PP_PLAN.find({PP_PLAN_ID:_plan_id}).forEach(
			function(obj2) {
				_appid = obj2.APPLICATION_ID;
				_allocations = [];
				db.tmp_SUBTYPE_TRANSLATION.find({"NEW_SUBTYPE" : obj2.NEW_SUBTYPE_ID, "APPLICATION_ID" : _appid}).forEach(
					function(obj3) {
						_allocations.push(obj3.LOCATION_ID);
					}
				);
				db.tmp_Prefix_Allocation_ID_clear.find({HOME_OPPS_ID:{$in:_allocations}}).forEach(
					function(obj4) {
						db.tmp_PPS_PREFIXES.find({BILLING_ALLOCATION:obj4.ALLOCATION_B}).forEach(
							function(obj5) {
								_prefixes.push(obj5.PPS_PREFIXES);
							}
						);
					}
				);
			}
		);
		_tariffs = {};
		_tariffs[_usaget] = {
			'access':   obj1.INITIAL_CHARGE,
			'rate':     {
				'to': 2147483647,
				'price': obj1.ADD_CHARGE,
				'interval': obj1.ADD_AMOUNT
			}
		}
		_rate = {
			'from':    ISODate('2015-12-01'),
			'to':      ISODate('2099-12-31 23:59:59'),
			'key':     obj1.PP_TARIFF_NAME,
			'params':  {
				'destination_prefixes': _prefixes
			},
			'rates': _tariffs
		};
		db.rates.insert(_rate);
	}
);
