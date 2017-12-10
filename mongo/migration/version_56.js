/* 
 * Version 56 Idempotent DB migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */

db.queue.ensureIndex({'urt': 1, 'type': 1}, {unique: false, sparse: true, background: true});

// BRCD-878: add rates custom fields
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

var additionalParams = db.rates.find({}, {params: 1});
var params = [];
for (var i = 0; i < additionalParams.length(); i++) {
	if (typeof additionalParams[i].params === 'undefined') {
		continue;
	}
	var keys = Object.keys(additionalParams[i].params);
	for (var j in keys) {
		params.push(keys[j]);
	}
}
var p = [...new Set(params)];
		var fields = lastConfig['rates']['fields'];
for (var i in p) {
	var found = false;
	for (var j in fields) {
		if (fields[j].field_name === "params." + p[i]) {
			found = true;
			break;
		}
	}
	if (!found) {
		fields.push({"field_name": "params." + p[i], "multiple": true, "title": p[i], "display": true, "editable": true});
	}
}
lastConfig['rates']['fields'] = fields;
db.config.insert(lastConfig);

// Add firstname/lastname/email account system field if it doesn't yet exist - BRCD-724
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
if ((typeof lastConfig) !== "undefined") {
	delete lastConfig['_id'];
	var found_lastname = false;
	var found_firstname = false;
	var found_email = false;

	lastConfig.subscribers.account.fields.forEach(function (field) {
		if (field.field_name == "lastname") {
			found_lastname = true;
			field.unique = false;
			field.generated = false;
			field.editable = true;
			field.mandatory = true;
			field.system = true;
			field.display = true;
		}
		if (field.field_name == "firstname") {
			found_firstname = true;
			field.unique = false;
			field.generated = false;
			field.editable = true;
			field.mandatory = true;
			field.system = true;
			field.display = true;
		}
		if (field.field_name == "email") {
			found_email = true;
			field.unique = false;
			field.generated = false;
			field.editable = true;
			field.mandatory = true;
			field.system = true;
			field.display = true;
		}
	})

	if (!found_lastname) {
		lastConfig.subscribers.account.fields.push({
			"field_name": "lastname",
			"title": "Last name",
			"generated": false,
			"unique": false,
			"editable": true,
			"mandatory": true,
			"system": true,
			"display": true
		});
	}
	if (!found_firstname) {
		lastConfig.subscribers.account.fields.push({
			"field_name": "firstname",
			"title": "First name",
			"generated": false,
			"unique": false,
			"editable": true,
			"mandatory": true,
			"system": true,
			"display": true
		});
	}
	if (!found_email) {
		lastConfig.subscribers.account.fields.push({
			"field_name": "email",
			"title": "Email",
			"generated": false,
			"unique": false,
			"editable": true,
			"mandatory": true,
			"system": true,
			"display": true
		});
	}

	db.config.insert(lastConfig);
}

// BRCD-851 - add system flag to all system fields
var systemFields = ['sid', 'aid', 'firstname', 'lastname', 'plan', 'plan_activation', 'address', 'country', 'services'];
var conf = lastConfig['subscribers']['subscriber']['fields'];
for (var i in conf) {
	if (systemFields.indexOf(conf[i]['field_name']) !== -1) {
		conf[i]['system'] = true;
	}
}
lastConfig['subscribers']['subscriber']['fields'] = conf;

var systemFields = ['aid', 'firstname', 'lastname', 'email', 'country', 'address', 'zip_code', 'payment_gateway', 'personal_id', 'salutation'];
var conf = lastConfig['subscribers']['account']['fields'];
for (var i in conf) {
	if (systemFields.indexOf(conf[i]['field_name']) !== -1) {
		conf[i]['system'] = true;
	}
}
lastConfig['subscribers']['account']['fields'] = conf;

var systemFields = ['key', 'from', 'to', 'description', 'rates'];
var conf = lastConfig['rates']['fields'];
for (var i in conf) {
	if (systemFields.indexOf(conf[i]['field_name']) !== -1) {
		conf[i]['system'] = true;
	}
}
lastConfig['rates']['fields'] = conf;

var systemFields = ['from', 'to', 'name', 'price', 'description', 'upfront'];
var conf = lastConfig['plans']['fields'];
for (var i in conf) {
	if (systemFields.indexOf(conf[i]['field_name']) !== -1) {
		conf[i]['system'] = true;
	}
}
lastConfig['plans']['fields'] = conf;

var systemFields = ['from', 'to', 'name', 'price', 'description', 'include'];
var conf = lastConfig['services']['fields'];
for (var i in conf) {
	if (systemFields.indexOf(conf[i]['field_name']) !== -1) {
		conf[i]['system'] = true;
	}
}
lastConfig['services']['fields'] = conf;

db.config.insert(lastConfig);

// BRCD-891: Unit of Measures
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
if (lastConfig['usage_types'] && lastConfig['usage_types']['array']) {
	var prevUsageTypes = lastConfig['usage_types']['array'];
	if (prevUsageTypes) {
		var usageTypes = [];
		for (var i in prevUsageTypes) {
			var prevUsageType = prevUsageTypes[i];
			if (typeof prevUsageType.usage_type !== 'undefined') {
				usageTypes.push(prevUsageType);
				continue;
			}
			prevUsageType;
			var system = (["call", "data"].indexOf(prevUsageType.toLowerCase()) !== -1);
			if (prevUsageType.toLowerCase().indexOf("call") !== -1) {
				usageTypes.push({
					"usage_type": prevUsageType,
					"label": prevUsageType,
					"system": system,
					"property_type": "time",
					"invoice_uom": "hhmmss",
					"input_uom": "minutes"
				});
			} else if (prevUsageType.toLowerCase().indexOf("data") !== -1) {
				usageTypes.push({
					"usage_type": prevUsageType,
					"label": prevUsageType,
					"system": system,
					"property_type": "data",
					"invoice_uom": "automatic",
					"input_uom": "mb1024"
				});
			} else {
				usageTypes.push({
					"usage_type": prevUsageType,
					"label": prevUsageType,
					"system": system,
					"property_type": "counter",
					"invoice_uom": "counter",
					"input_uom": "counter"
				});
			}
		}

		lastConfig['usage_types'] = usageTypes;
		lastConfig['property_types'] = [
			{
				system: true,
				type: "time",
				uom: [{unit: 1, name: "seconds", label: "Seconds"}, {unit: 60, name: "minutes", label: "Minutes"}, {unit: 3600, name: "hours", label: "Hours"}, {name: "hhmmss", label: "hh:mm:ss", function_name: "parseTime"}],
				invoice_uom: "hhmmss"
			},
			{
				system: true,
				type: "data",
				uom: [{unit: 1, name: "byte", label: "Byte"}, {unit: 1000, name: "kb1000", label: "KB"}, {unit: 1000000, name: "mb1000", label: "MB"}, {unit: 1000000000, name: "gb1000", label: "GB"}, {unit: 1000000000000, name: "tb1000", label: "TB"}, {unit: 1024, name: "kb1024", label: "KiB"}, {unit: 1048576, name: "mb1024", label: "MiB"}, {unit: 1073741824, name: "gb1024", label: "GiB"}, {unit: 1099511627776, name: "tb1024", label: "TiB"}, {name: "automatic", label: "Automatic", function_name: "parseDataUsage"}],
				invoice_uom: "automatic"
			},
			{
				system: true,
				type: "length",
				uom: [{unit: 1, name: "mm", label: "mm"}, {unit: 10, name: "cm", label: "cm"}, {unit: 1000, name: "m", label: "m"}, {unit: 1000000, name: "km", label: "km"}],
				invoice_uom: "cm"
			},
			{
				system: true,
				type: "counter",
				uom: [{unit: 1, name: "counter", label: "Counter"}],
				invoice_uom: "counter"
			}
		];
	}
}
var fileTypes = lastConfig['file_types'];
for (var i in fileTypes) {
	if (fileTypes[i].processor && fileTypes[i].processor.usaget_mapping) {
		for (var j in fileTypes[i].processor.usaget_mapping) {
			if (!fileTypes[i].processor.usaget_mapping[j].unit) {
				var usaget = fileTypes[i].processor.usaget_mapping[j].usaget;
				var unit = 'counter';
				if (usaget.toLowerCase().includes('call')) {
					unit = 'seconds';
				} else if (usaget.toLowerCase().includes('data')) {
					unit = 'byte';
				}
				fileTypes[i].processor.usaget_mapping[j].unit = unit;
			}
		}
	}
}
db.config.insert(lastConfig);

db.rates.find().forEach(function (rate) {
	for (var usaget in rate.rates) {
		var ratesObj = rate.rates[usaget];
		var unit = 'counter';
		if (usaget.toLowerCase().includes('call')) {
			unit = 'seconds';
		} else if (usaget.toLowerCase().includes('data')) {
			unit = 'byte';
		}
		ratesObj.BASE.rate.forEach(function (step) {
			if (!step.uom_display) {
				step.uom_display = {
					range: unit,
					interval: unit
				};
			}
		});
	}

	db.rates.save(rate);
});

db.plans.find().forEach(function (plan) {
	for (var rate in plan.rates) {
		for (var usaget  in plan.rates[rate]) {
			var ratesObj = plan.rates[rate][usaget];
			var unit = 'counter';
			if (usaget.toLowerCase().includes('call')) {
				unit = 'seconds';
			} else if (usaget.toLowerCase().includes('data')) {
				unit = 'byte';
			}

			ratesObj.rate.forEach(function (step) {
				if (!step.uom_display) {
					step.uom_display = {
						range: unit,
						interval: unit
					};
				}
			});
		}
	}

	if (plan.include && plan.include.groups) {
		for (var group in plan.include.groups) {
			if (plan.include.groups[group].unit) {
				continue;
			}
			for (var key in plan.include.groups[group]) {
				if (["account_shared", "account_pool", "rates", "unit"].indexOf(key) !== -1) {
					continue;
				}
				var unit = 'counter';
				if (key == 'cost') {
					unit = '';
				} else if (key.toLowerCase().includes('call')) {
					unit = 'seconds';
				} else if (key.toLowerCase().includes('data')) {
					unit = 'byte';
				}
				plan.include.groups[group].unit = unit;
			}
		}
	}

	db.plans.save(plan);
});

db.prepaidincludes.find().forEach(function (prepaidinclude) {
	if (prepaidinclude.charging_by == "usagev" && !prepaidinclude.charging_by_usaget_unit) {
		var usaget = prepaidinclude.charging_by_usaget;
		var unit = 'counter';
		if (usaget.toLowerCase().includes('call')) {
			unit = 'seconds';
		} else if (usaget.toLowerCase().includes('data')) {
			unit = 'byte';
		}
		prepaidinclude.charging_by_usaget_unit = unit;
		db.prepaidincludes.save(prepaidinclude);
	}
});

db.services.find().forEach(function (service) {
	if (service.include && service.include.groups) {
		for (var group in service.include.groups) {
			if (service.include.groups[group].unit) {
				continue;
			}
			for (var key in service.include.groups[group]) {
				if (["account_shared", "account_pool", "rates", "unit"].indexOf(key) !== -1) {
					continue;
				}
				var unit = 'counter';
				if (key == 'cost') {
					unit = '';
				} else if (key.toLowerCase().includes('call')) {
					unit = 'seconds';
				} else if (key.toLowerCase().includes('data')) {
					unit = 'byte';
				}
				service.include.groups[group].unit = unit;
			}
		}
	}

	db.services.save(service);
});

//BRCD-924 - update system account/subscriber custom fields
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

var knownFields = {
	"firstname": "First name",
	"lastname": "Last name",
	"country": "Country",
	"address": "Address",
	"email": "E-Mail"
};

var accountFields = lastConfig.subscribers.account.fields;
for (var i in accountFields) {
	var fieldName = accountFields[i].field_name;
	if (knownFields[fieldName] !== undefined) {
		accountFields[i].editable = true;
		accountFields[i].display = true;
		if (accountFields[i].title === undefined) {
			accountFields[i].title = knownFields[fieldName];
		}
	}
}
lastConfig.subscribers.account.fields = accountFields;

var subscriberFields = lastConfig.subscribers.subscriber.fields;
for (var i in subscriberFields) {
	var fieldName = subscriberFields[i].field_name;
	if (knownFields[fieldName] !== undefined) {
		subscriberFields[i].editable = true;
		subscriberFields[i].display = true;
		if (subscriberFields[i].title === undefined) {
			subscriberFields[i].title = knownFields[fieldName];
		}
	}
}
lastConfig.subscribers.subscriber.fields = subscriberFields;

// BRCD-841: add status to realtime response
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
var fileTypes = lastConfig['file_types'];
for (var i in fileTypes) {
	if (fileTypes[i].response && fileTypes[i].response.fields) {
		fileTypes[i].response.fields.push({
			"response_field_name": "status",
			"row_field_name": {
				"classMethod": "getStatus"
			}
		});
	}
}

lastConfig.file_types = fileTypes;
db.config.insert(lastConfig);

// BRCD-933: make all unique custom fields mandatory
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

var accountFields = lastConfig.subscribers.account.fields;
for (var i in accountFields) {
	if (accountFields[i].unique) {
		accountFields[i].mandatory = true;
	}
}
lastConfig.subscribers.account.fields = accountFields;

var subscriberFields = lastConfig.subscribers.subscriber.fields;
for (var i in subscriberFields) {
	if (subscriberFields[i].unique) {
		subscriberFields[i].mandatory = true;
	}
}
lastConfig.subscribers.subscriber.fields = subscriberFields;

var rateFields = lastConfig.rates.fields;
for (var i in rateFields) {
	if (rateFields[i].unique) {
		rateFields[i].mandatory = true;
	}
}
lastConfig.rates.fields = rateFields;

db.config.insert(lastConfig);

// BRCD-552
db.events.ensureIndex({'creation_time': 1}, {unique: false, sparse: true, background: true});

db.rebalance_queue.dropIndex('sid_1_billrun_key_1');
db.rebalance_queue.ensureIndex({"aid": 1, "billrun_key": 1}, {unique: true, "background": true})

// BRCD-976 - move unify to config
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

if (!lastConfig.unify) {
	lastConfig.unify = {
		"unification_fields": {
			"required": {
				"fields": [
					"session_id",
					"urt",
					"request_type"
				],
				"match": {
					"request_type": "\/1|2|3\/"
				}
			},
			"date_seperation": "Ymd",
			"stamp": {
				"value": [
					"session_id",
					"usaget",
					"imsi"
				],
				"field": []
			},
			"fields": [
				{
					"match": {
						"request_type": "\/.*\/"
					},
					"update": [
						{
							"operation": "$setOnInsert",
							"data": [
								"arate",
								"arate_key",
								"usaget",
								"imsi",
								"session_id",
								"urt",
								"plan",
								"connection_type",
								"aid",
								"sid",
								"msisdn"
							]
						},
						{
							"operation": "$set",
							"data": [
								"process_time",
								"granted_return_code",
								"eurt"
							]
						},
						{
							"operation": "$inc",
							"data": [
								"usagev",
								"duration",
								"apr",
								"out_balance_usage",
								"in_balance_usage",
								"aprice"
							]
						}
					]
				}
			]
		}
	};
	db.config.insert(lastConfig);
}

if (db.getName() === 'billing_onesimcard') {
	var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
	delete lastConfig['_id'];

	for (var i in lastConfig.file_types) {
		lastConfig.file_types[i].unify = {
			"unification_fields": {
				"fields": [
					{
						"update": [
							{
								"operation": "$setOnInsert",
								"data": [
									"uf.3GPP_Location_Info",
									"uf.BR_3GPP_SGSN_MNC",
									"uf.BR_3GPP_SGSN_MCC",
									"uf.BR_ICCID",
									"uf.Called_Station_Id",
									"uf.Calling_Station_Id"
								]
							},
							{
								"operation": "$set",
								"data": [
									"uf.Acct_Output_Octets",
									"uf.Acct_Status_Type",
									"uf.Acct_Input_Octets"
								]
							}
						]
					}
				]
			}
		};
	}

	db.config.insert(lastConfig);
}

// BRCD-979: change rebalance field to array
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
for (var i in lastConfig.file_types) {
	if (lastConfig.file_types[i].realtime && !(lastConfig.file_types[i].realtime.used_usagev_field instanceof Array)) {
		lastConfig.file_types[i].realtime.used_usagev_field = [lastConfig.file_types[i].realtime.used_usagev_field];
	}
}
db.config.insert(lastConfig);

// BRCD-1031
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
if (lastConfig.events && lastConfig.events.pricing) {
	delete lastConfig['events']['pricing'];
}
if (lastConfig.events && Object.keys(lastConfig.events).length === 0) {
	delete lastConfig['events'];
}
if (lastConfig.events && !lastConfig.events.settings) {
	lastConfig.events.settings = {
		"http": {
			"url": "",
			"num_of_tries": 3,
			"method": "post",
			"decoder": "json"
		}
	};
}
db.config.insert(lastConfig);

// BRCD-1013: add auto renew collection
db.createCollection('autorenew');
db.autorenew.ensureIndex({ 'from': 1, 'to': 1, 'next_renew': 1}, { unique: false , sparse: true, background: true });
db.autorenew.ensureIndex({ 'sid': 1, 'aid': 1}, { unique: false, sparse: true, background: true });

// BRCD-984 - Change received_time field to date instead of a string in log collection
db.log.find().forEach(function (logItem) {
	var oldDate = logItem.received_time;
	if (oldDate) {
		if (typeof (oldDate) === "string") {
			oldDate = oldDate.replace(" ", 'T');
			if (oldDate.charAt(oldDate.length - 1) !== 'Z') {
				oldDate = oldDate.concat("Z");
			}
			var date = new Date(oldDate);
			logItem.received_time = date;
			db.log.save(logItem);
		}
	}
});
