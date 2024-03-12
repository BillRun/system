/* 
 * General (idempotent) DB migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */

// =============================== Helper functions ============================

function addFieldToConfig(lastConf, fieldConf, entityName) {
	if (typeof lastConf[entityName] === 'undefined') {
		lastConf[entityName] = {'fields': []};
	}
	var fields = lastConf[entityName]['fields'];
	var found = false;
	for (var field_key in fields) {
		if (fields[field_key].field_name === fieldConf.field_name) {
			found = true;
		}
	}
	if(!found) {
		fields.push(fieldConf);
	}
	lastConf[entityName]['fields'] = fields;

	return lastConf;
}

function removeFieldFromConfig(lastConf, field_names, entityName) {
	if (typeof lastConf[entityName] === 'undefined') {
		return lastConf;
	}
	field_names_to_delete = Array.isArray(field_names) ? field_names : [field_names];
	var fields = lastConf[entityName]['fields'];
	lastConf[entityName]['fields'] = fields.filter(field => !field_names_to_delete.includes(field.field_name));
	return lastConf;
}

// Perform specific migrations only once
// Important note: runOnce is guaranteed to run some migration code once per task code only if the whole migration script completes without errors.
function runOnce(lastConfig, taskCode, callback) {
    print("running task " + taskCode);
    if (typeof lastConfig.past_migration_tasks === 'undefined') {
        lastConfig['past_migration_tasks'] = [];
    }
    taskCode = taskCode.toUpperCase();
    if (!lastConfig.past_migration_tasks.includes(taskCode)) {
        if (new RegExp(/.*-\d+$/).test(taskCode)) {
            callback();
            lastConfig.past_migration_tasks.push(taskCode);
        }
        else {
            print('Illegal task code ' + taskCode);
        }
    }
    return lastConfig;
}

function _createCollection(newcoll) {
    var _existsingColls = db.getCollectionNames();
    if (_existsingColls.indexOf(newcoll) >= 0) { // collection already exists
        return false;
    }
    return db.createCollection(newcoll);
}

function _collectionSave(coll, record) {
    if (!Object.hasOwn(record, '_id')) {
        coll.insertOne(record);
    } else {
        coll.replaceOne({"_id":record._id}, record, {"upsert":true}); // upsert in case of someone save in parallel
    }
}

function _dropIndex(collname, indexname) {
    indexes = db.getCollection(collname).getIndexes();
    let i = indexes.find(obj => obj.name === indexname);
    if (i === undefined) {
        return false;
    }
    return db.getCollection(collname).dropIndex(indexname);
}

// =============================================================================
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty().next();
delete lastConfig	['_id'];
// =============================================================================

// BRCD-1077 Add new custom 'tariff_category' field to Products(Rates).
var fields = lastConfig['rates']['fields'];
var found = false;
for (var field_key in fields) {
	if (fields[field_key].field_name === "tariff_category") {
		found = true;
	}
}
if(!found) {
	fields.push({
		"system":false,
		"select_list":true,
		"display":true,
		"editable":true,
		"field_name":"tariff_category",
		"default_value":"retail",
		"show_in_list":true,
		"title":"Tariff category",
		"mandatory":true,
		"select_options":"retail",
		"changeable_props": ["select_options"]
	});
}

lastConfig['rates']['fields'] = fields;

var invoice_language_field = {
		"system":true,
		"display":true,
		"editable":true,
		"field_name":"invoice_language",
		"default_value":"en_GB",
		"show_in_list":false,
		"title":"Invoice language"
	}
lastConfig = addFieldToConfig(lastConfig, invoice_language_field, 'account');
// BRCD-1078: add rate categories
for (var i in lastConfig['file_types']) {
	var firstKey = Object.keys(lastConfig['file_types'][i]['rate_calculators'])[0];
	var secKey = Object.keys(lastConfig['file_types'][i]['rate_calculators'][firstKey])[0];
	if (secKey == 0) {
		lastConfig['file_types'][i]['rate_calculators']['retail'] = {};
	for (var usaget in lastConfig['file_types'][i]['rate_calculators']) {
			if (usaget === 'retail') {
				continue;
			}
			lastConfig['file_types'][i]['rate_calculators']['retail'][usaget] = lastConfig['file_types'][i]['rate_calculators'][usaget];
			delete lastConfig['file_types'][i]['rate_calculators'][usaget];
		}
	}
}

// BRCD-1077 update all products(Rates) tariff_category field.
db.rates.updateMany({'tariff_category': {$exists: false}},{$set:{'tariff_category':'retail'}});

// BRCD-938: Option to not generate pdfs for the cycle
if (typeof lastConfig['billrun']['generate_pdf']  === 'undefined') {
	lastConfig['billrun']['generate_pdf'] = {"v": true ,"t" : "Boolean"};
}

// BRCD-441 -Add plugin support
if (!lastConfig['plugins']) {
	lastConfig.plugins = ["calcCpuPlugin", "csiPlugin", "autorenewPlugin", "notificationsPlugin"];
}

for (var i = 0; i < lastConfig['plugins'].length; i++) {
	if (typeof lastConfig['plugins'][i] === 'string') {
		if (lastConfig['plugins'][i] === "calcCpuPlugin") {
			lastConfig['plugins'][i] = {
				"name": "calcCpuPlugin",
				"enabled": true,
				"system": true,
				"hide_from_ui": true
			};
		} else if (["csiPlugin", "autorenewPlugin"].includes(lastConfig['plugins'][i]['name'])) {
			lastConfig['plugins'][i] = {
				"name": lastConfig['plugins'][i],
				"enabled": true,
				"system": true,
				"hide_from_ui": false
			};
		} else if (lastConfig['plugins'][i] === "notificationsPlugin") {
			lastConfig['plugins'][i] = {
				"name": lastConfig['plugins'][i],
				"enabled": false,
				"system": true,
				"hide_from_ui": false
			};
		} else {
				lastConfig['plugins'][i] = {
				"name": lastConfig['plugins'][i],
				"enabled": true,
				"system": false,
				"hide_from_ui": false
			};
		}
	}
}
//-------------------------------------------------------------------
// BRCD-1278 - backward support for new template
if(lastConfig.invoice_export) {
	if((!lastConfig.invoice_export.status || !lastConfig.invoice_export.status.header) &&
		!lastConfig.invoice_export.header) {
			lastConfig.invoice_export.header = "/application/views/invoices/header/header_tpl.html";
	}
	if((!lastConfig.invoice_export.status || !lastConfig.invoice_export.status.footer) &&
		!lastConfig.invoice_export.footer) {
			lastConfig.invoice_export.footer = "/application/views/invoices/footer/footer_tpl.html";
	}
}

//BRCD-1229 - Input processor re-enabled when not requested
for (var i in lastConfig['file_types']) {
	if (lastConfig['file_types'][i]['enabled'] === undefined) {
		lastConfig['file_types'][i]['enabled'] = true;
	}
}

// BRCD-1278 : add minutes:seconds support  for time display
var found =false;
for(var i in lastConfig["property_types"][0]["uom"]) {
		if(lastConfig["property_types"][0]["uom"][i]['name'] == "mmss" ) {
				found = true;
		}
}
if(!found) { 
		lastConfig["property_types"][0]["uom"].push({"name":"mmss","label":"mm:ss","function_name":"parseTime","arguments":{"format":"_I:s"}});
}
lastConfig["property_types"][0]['invoice_uom'] = "mmss";

// BRCD-1152: Add service activation date to each cdr generated on the billing cycle
if(!lastConfig['lines']) {
	lastConfig['lines'] = {};
}
if(!lastConfig['lines']['fields']) {
	lastConfig['lines']['fields'] = [];
}
var idx = 0;
for (var i in lastConfig['lines']['fields']) {
	if (lastConfig['lines']['fields'][i]['field_name'] == 'foreign.activation_date') {
		idx = i;
		break;
	}
	idx = i+1;
}
var addField = {
	field_name : "foreign.activation_date",
	foreign : { 
		entity : "service",
		field  :"start",
		translate : {
			type : "unixTimeToString",
			format : "Y-m-d H:i:s"
		}
	}
};
if(lastConfig['lines']['fields'].length > idx) {
	lastConfig['lines']['fields'][idx] = addField;
} else {
	lastConfig['lines']['fields'].push(addField);
}

// BRCD-1353: CreditGuard fixes
var paymentGateways = lastConfig['payment_gateways'];
for (var paymentGateway in paymentGateways) {
	if (paymentGateways[paymentGateway].name === "CreditGuard" && paymentGateways[paymentGateway]['params']['terminal_id'] !== undefined) {
		if (paymentGateways[paymentGateway]['params']['redirect_terminal'] === undefined || paymentGateways[paymentGateway]['params']['charging_terminal'] === undefined) {
			paymentGateways[paymentGateway]['params']['redirect_terminal'] = paymentGateways[paymentGateway]['params']['terminal_id'];
			paymentGateways[paymentGateway]['params']['charging_terminal'] = paymentGateways[paymentGateway]['params']['terminal_id'];
			delete paymentGateways[paymentGateway]['params']['terminal_id'];
		}
	}
}

// BRCD-1390 - Add activation_date field to subscriber
db.subscribers.find({activation_date:{$exists:0}, type:'subscriber'}).forEach(
	function(obj) {
		var activationDate = -1;
		db.subscribers.find({sid:obj.sid, aid:obj.aid, activation_date:{$exists:0}}).sort({'from': 1}).forEach(
			function(obj2) {
				if (activationDate == -1) {
					activationDate = obj2.from;
				}
				obj2.activation_date = activationDate;
				_collectionSave(db.subscribers, obj2);
			}
		);
	}
);

// BRCD-1402 - Add activation_date field to subscriber
if(lastConfig.invoice_export) {
	if(lastConfig.invoice_export.header && lastConfig.invoice_export.header.match(/^\/application\/views\/invoices/)) {
		lastConfig.invoice_export.header = lastConfig.invoice_export.header.replace(/^\/application\/views\/invoices/,'');
	}
	if(lastConfig.invoice_export.footer && lastConfig.invoice_export.footer.match(/^\/application\/views\/invoices/)) {
		lastConfig.invoice_export.footer =lastConfig.invoice_export.footer.replace(/^\/application\/views\/invoices/,'');
	}
}

// BRCD-2251 remove old vatable filed
lastConfig = removeFieldFromConfig(lastConfig, 'vatable', 'services');

//BRCD-1272 - Generate Creditguard transactions in csv file + handle rejections file
for (var i in lastConfig['payment_gateways']) {
	if (lastConfig["payment_gateways"][i]['name'] == "CreditGuard") {
		if (typeof lastConfig['payment_gateways'][i]['receiver']  === 'undefined' && typeof lastConfig['payment_gateways'][i]['export']  === 'undefined' ) {
			lastConfig["payment_gateways"][i].receiver = {};
			lastConfig["payment_gateways"][i].export = {};
		}
	}
}

//BRCD-1411 - Multiple conditions for usage type mapping.
var fileTypes = lastConfig['file_types'];
for (var fileType in fileTypes) {
	if (typeof fileTypes[fileType]['processor']['usaget_mapping'] !== 'undefined') {
		var usagetMapping = fileTypes[fileType]['processor']['usaget_mapping'];
		for (var mapping in usagetMapping) {
			if (typeof fileTypes[fileType]['processor']['usaget_mapping'][mapping]['conditions'] === 'undefined') {
				var conditions = [];
				var condition = {
					"src_field": usagetMapping[mapping]["src_field"],
					"pattern": usagetMapping[mapping]["pattern"],
					"op": "$eq",
				};
				conditions.push(condition);
				fileTypes[fileType]['processor']['usaget_mapping'][mapping]["conditions"] = conditions;
				delete fileTypes[fileType]['processor']['usaget_mapping'][mapping]["src_field"];
				delete fileTypes[fileType]['processor']['usaget_mapping'][mapping]["pattern"];
			}
		}
	}
}

// BRCD-1415 - add invoice when ready email template
if(!lastConfig.email_templates) {
	lastConfig.email_templates = {
    "invoice_ready": {
      "subject": "Your invoice is ready",
      "content": "<pre>\nHello [[customer_firstname]],\n\nThe invoice for [[cycle_range]] is ready and is attached to this email.\nFor any questions, please contact us at [[company_email]].\n\n[[company_name]]</pre>\n",
      "html_translation": [
        "invoice_id",
        "invoice_total",
        "invoice_due_date",
        "cycle_range",
        "company_email",
        "company_name"
      ]
    }
  };
}
// BRCD-2364: Customer invoicing_day field, Should be a system field, not visible for editing by default.
//The possible values are 1-28 - should be enforced using the existing "Select list" feature
var invoicingDayField = {
	"field_name": "invoicing_day",
	"title": "Invoicing Day",
	"mandatory": false,
	"system": true,
	"show_in_list": true,
	"select_list": true,
	"editable": false,
	"display": false,
	"select_options": "1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28",
	"default_value":null
};

lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], invoicingDayField, 'account');

// BRCD-1415 - add system field to account (invoice_shipping_method)
var fields = lastConfig['subscribers']['account']['fields'];
var found = false;
for (var field_key in fields) {
	if (fields[field_key].field_name === "invoice_shipping_method") {
		found = true;
	}
	if (fields[field_key].field_name === "invoicing_day") {
		fields[field_key].default_value = null;
	}
}
if(!found) {
	fields.push({
		"system":false,
		"select_list":true,
		"display":true,
		"editable":true,
		"field_name":"invoice_shipping_method",
		"default_value":"email",
		"show_in_list":true,
		"title":"Invoice shipping method",
		"mandatory":false,
		"select_options":"email",
		"changeable_props": ["select_options"]
	});
}
lastConfig['subscribers']['account']['fields'] = fields;


// BRCD-1458 - Add support for hh:mm:ss, mm:ss "units" in input processor volume stage.
var propertyTypes = lastConfig['property_types'];
for (var i in propertyTypes) {
	if (propertyTypes[i]['type'] === 'time') {
		var timeProperty = lastConfig['property_types'][i];
		if (timeProperty['uom']) {
			for (var j in timeProperty['uom']) {
				if (timeProperty['uom'][j]['name'] === 'hhmmss' || timeProperty['uom'][j]['name'] === 'mmss') {
					lastConfig['property_types'][i]['uom'][j]['convertFunction'] = 'formatedTimeToSeconds'; 
				}
			}
		}
	}
}


db.rebalance_queue.createIndex({"creation_date": 1}, {unique: false, "background": true})

// BRCD-1443 - Wrong billrun field after a rebalance
db.billrun.updateMany({'attributes.invoice_type':{$ne:'immediate'}, billrun_key:{$regex:/^[0-9]{14}$/}},{$set:{'attributes.invoice_type': 'immediate'}});
// BRCD-1457 - Fix creation_time field in subscriber services
db.subscribers.find({type: 'subscriber', 'services.creation_time.sec': {$exists:1}}).forEach(
	function(obj) {
		var services = obj.services;
		for (var service in services) {
			if (obj['services'][service]['creation_time'] === undefined) {
				obj['services'][service]['creation_time'] = obj['services'][service]['from'];
			} else if (obj['services'][service]['creation_time']['sec'] !== undefined) {
				var sec = obj['services'][service]['creation_time']['sec'];
				var usec = obj['services'][service]['creation_time']['usec'];
				var milliseconds = sec * 1000 + usec;
				obj['services'][service]['creation_time'] = new Date(milliseconds);
			}
		}
		
		_collectionSave(db.subscribers, obj);
	}
);
// BRCD-1552 collection
if (typeof lastConfig['collection'] === 'undefined') {
	lastConfig['collection'] = {'settings': {}};
}
if (typeof lastConfig['collection']['settings'] === 'undefined') {
	lastConfig['collection']['settings'] = {};
}
if (typeof lastConfig['collection']['min_debt'] !== 'undefined' && lastConfig['collection']['settings']['min_debt'] === 'undefined') {
    lastConfig['collection']['settings']['min_debt'] = lastConfig['collection']['min_debt'];
}
delete lastConfig['collection']['min_debt'];
// BRCD-1562 - steps trigget time
if (typeof lastConfig['collection']['settings']['run_on_holidays'] === 'undefined') {
    lastConfig['collection']['settings']['run_on_holidays'] = true;
}
if (typeof lastConfig['collection']['settings']['run_on_days'] === 'undefined') {
    lastConfig['collection']['settings']['run_on_days'] = [true,true,true,true,true,true,true];
}
if (typeof lastConfig['collection']['settings']['run_on_hours'] === 'undefined') {
    lastConfig['collection']['settings']['run_on_hours'] = [];
}
_dropIndex("counters", "coll_1_oid_1")
db.counters.createIndex({coll: 1, key: 1}, { sparse: false, background: true});

// BRCD-1475 - Choose CDR fields that will be saved under 'uf'
for (var i in lastConfig['file_types']) {
	var fileType = lastConfig['file_types'][i];
	for (var j in fileType['parser']['structure']) {
		if (fileType['parser']['structure'][j]['checked'] === undefined) {
			lastConfig['file_types'][i]['parser']['structure'][j]['checked'] = true;
		}
	}
}

//BRCD-1420 : Integrate Invoice usage details from the CRM with the billing
var detailedField = {
					"select_list" : false,
					"display" : true,
					"editable" : true,
					"generated":false,
					"multiple" : false,
					"system":true,
					"field_name" : "invoice_detailed",
					"unique" : false,
					"show_in_list":false,
					"title" : "Detailed Invoice",
					"type" : "boolean",
					"mandatory" : false,
					"select_options" : ""
};

lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], detailedField, 'account');

// BRCD-2404: Fix payment gateways redirection for token (add payment gateway to custom field)
var paymentGateway = {
	"field_name" : "payment_gateway",
	"system" : true,
	"editable" : false,
}

lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], paymentGateway, 'account');



// BRCD-1636 Add new custom 'play' field to Subscribers.
var fields = lastConfig['subscribers']['subscriber']['fields'];
var found = false;
for (var field_key in fields) {
	if (fields[field_key].field_name === "play") {
		found = true;
	}
}
if(!found) {
	fields.push({
		"system":false,
		"display":true,
		"editable":true,
		"field_name":"play",
		"show_in_list":true,
		"title":"Play",
                "multiple" : false,
	});
}
lastConfig['subscribers']['subscriber']['fields'] = fields;

// BRCD-1512 - Fix bills' linking fields / take into account linking fields when charging
db.bills.createIndex({'invoice_id': 1 }, { unique: false, background: true});

// BRCD-1516 - Charge command filtration
db.bills.createIndex({'billrun_key': 1 }, { unique: false, background: true});
db.bills.createIndex({'invoice_date': 1 }, { unique: false, background: true});

// BRCD-1552 collection
_dropIndex("collection_steps", "aid_1");
_dropIndex("collection_steps", "trigger_date_1_done_1");
db.collection_steps.createIndex({'trigger_date': 1}, { unique: false , sparse: true, background: true });
db.collection_steps.createIndex({'extra_params.aid':1 }, { unique: false , sparse: true, background: true });

//BRCD-1541 - Insert bill to db with field 'paid' set to 'false'
db.bills.updateMany({type: 'inv', paid: {$exists: false}, due: {$gte: 0}}, {$set: {paid: '0'}});

//BRCD-1621 - Service quantity based quota
var subscribers = db.subscribers.find({type:'subscriber', "services":{$type:4, $ne:[]}, $where: function() {
	var services = this.services; 
		var hasStringQuantity = false; 
		services.forEach(function (service) {
			if (typeof service.quantity === "string") {
				hasStringQuantity = true;
			}
		});
		return hasStringQuantity;
}});
subscribers.forEach(function (sub) {
	var services = sub.services;
	services.forEach(function (service) {
		if (service.quantity) {
			service.quantity = Number(service.quantity);
			_collectionSave(db.subscribers, sub);
		}
	});
});

//// BRCD-1624: add default Plays to config
//if (typeof lastConfig.plays == 'undefined') {
//	lastConfig.plays = [
//		{"name": "Default", "enabled": true, "default": true }
//	];
//}

//BRCD-1643: add email template for fraud notification
if (typeof lastConfig.email_templates.fraud_notification == 'undefined') {
	lastConfig.email_templates.fraud_notification = {
		subject: "Event [[event_code]] was triggered",
		content: "<pre>\n[[fraud_event_details]]</pre>\n",
	};
}

//BRCD-1613 - Configurable VAT label on invoice
var vatLabel = lastConfig['taxation']['vat_label'];
if (!vatLabel) {
	lastConfig['taxation']['vat_label'] = 'Vat';
}

// BRCD-1682 - Add new custom 'play' field to Proucts/Services/Plans
var entities = ['plans', 'rates', 'services'];
for (var i in entities) {
	var entity = entities[i];
	var fields = lastConfig[entity]['fields'];
	var found = false;
	for (var field_key in fields) {
		if (fields[field_key].field_name === "play") {
			found = true;
		}
	}
	if(!found) {
		fields.push({
			"system":false,
			"display":true,
			"editable":true,
			"field_name":"play",
			"show_in_list":true,
			"title":"Play",
                        "multiple" : ['plans', 'services'].includes(entity),
		});
	}
	lastConfig[entity]['fields'] = fields;
}

// #727 set subscriber services fields to be multiple to use Push/Pull option
var subscriberServicesFieldIndex = lastConfig['subscribers']['subscriber']['fields'].findIndex(field => field.field_name === "services");
if (typeof lastConfig['subscribers']['subscriber']['fields'][subscriberServicesFieldIndex]["multiple"] === 'undefined') {
    lastConfig['subscribers']['subscriber']['fields'][subscriberServicesFieldIndex]["multiple"] = true;
}

// BRCD-1835: add default TAX key
if (typeof lastConfig['taxation'] === 'undefined') {
	lastConfig.taxation = {};
}
if (typeof lastConfig['taxation']['default'] === 'undefined') {
	lastConfig.taxation.default = {};
}
if (typeof lastConfig['taxation']['default']['key'] === 'undefined') {
	lastConfig.taxation.default.key = "DEFAULT_TAX";
}

// BRCD-1837: convert legacy VAT taxation to default taxation rate
if (lastConfig['taxation']['tax_type'] == 'vat') {
	var vatRate = lastConfig['taxation']['vat']['v'];
	var vatLabel = typeof lastConfig['taxation']['vat_label'] !== 'undefined' ? lastConfig['taxation']['vat_label'] : "Vat";
	
	lastConfig.taxation = {
		"tax_type": "usage",
		"default": {
			"key": "DEFAULT_VAT"
		}
	};
	
	var vatFrom = new Date('2019-01-01');
	var vatTo = new Date('2119-01-01');
	var vat = {
		key: "DEFAULT_VAT",
		from: vatFrom,
		creation_time: vatFrom,
		to: vatTo,
		description: vatLabel,
		rate: vatRate,
		params: {}
	};
	
	db.taxes.insertOne(vat);
}

//BRCD-1834 : Add tax field
var taxField ={
    "system":true,
    "field_name" : "tax"
};
lastConfig = addFieldToConfig(lastConfig, taxField, 'rates');
lastConfig = addFieldToConfig(lastConfig, taxField, 'plans');
lastConfig = addFieldToConfig(lastConfig, taxField, 'services');
//BRCD-1832 - Dummy priorities 
var defaultVatMapping = {
    vat: {
			  "priorities": [],
        "default_fallback": true
    }
};
if (typeof lastConfig['taxation']['mapping'] === 'undefined') {
    lastConfig['taxation']['mapping'] = defaultVatMapping;
}

// BRCD-1843 - Service is taxable but shown as non-taxable
var servicesFields = lastConfig['services']['fields'];
if (servicesFields) {
	servicesFields.forEach(function (field){
		if (field['field_name'] === 'vatable') {
			field['default_value'] = true;
		}
	});
	lastConfig['services']['fields'] = servicesFields;
}

// BRCD-1917 - add discount fields
var discountsFields = [{
	"field_name": "from",
	"system": true,
	"mandatory": true,
	"type": "date"
}, {
	"field_name": "to",
	"system": true,
	"mandatory": true,
	"type": "date"
}, {
	"field_name": "key",
	"system": true,
	"mandatory": true
}, {
	"field_name": "description",
	"system": true,
	"mandatory": true
}];
for (var fieldIdx in discountsFields) {
	lastConfig = addFieldToConfig(lastConfig, discountsFields[fieldIdx], 'discounts');
}
// BRCD-1969 - add account allowances field
var accountField = {
	"field_name": "allowances",
	"system": true,
	"display": false,
};
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], accountField, 'account');
// BRCD-1917 - add system flag true to discount system fields
var discountFields = lastConfig['discounts']['fields'];
if (discountFields) {
	var discountsSystemFields = ['key', 'from', 'to', 'description'];
	discountFields.forEach(function (field){
		if (discountsSystemFields.includes(field['field_name']) && typeof field['system'] === 'undefined') {
			field['system'] = true;
		}
	});
	lastConfig['discounts']['fields'] = discountFields;
}

//BRCD-1942 : Add Charge fields 
var chargeFields = [{
	"field_name": "from",
	"system": true,
	"mandatory": true,
	"type": "date"
	}, {
	"field_name": "to",
	"system": true,
	"mandatory": true,
	"type": "date"
	}, {
	"field_name": "key",
	"system": true,
	"mandatory": true
	}, {
	"field_name": "description",
	"system": true,
	"mandatory": true
}];
for (var fieldIdx in chargeFields) {
	lastConfig = addFieldToConfig(lastConfig, chargeFields[fieldIdx], 'charges');
}

//BRCD-1858 - UI for denials receiver for Credit Guard
for (var i in lastConfig['payment_gateways']) {
	if (lastConfig["payment_gateways"][i]['name'] == "CreditGuard") {
			if (typeof lastConfig['payment_gateways'][i]['transactions'] === 'undefined') {
					lastConfig['payment_gateways'][i]['transactions'] = {};
			}
			if (typeof lastConfig['payment_gateways'][i]['transactions']['receiver'] === 'undefined')	{
				lastConfig["payment_gateways"][i]['transactions'].receiver = [];
			}

			if (typeof lastConfig['payment_gateways'][i]['denials'] === 'undefined') {
					lastConfig['payment_gateways'][i]['denials'] = {};
			}
			if (typeof lastConfig['payment_gateways'][i]['denials']['receiver'] === 'undefined') {
				lastConfig["payment_gateways"][i]['denials'].receiver = [];
			}
			delete(lastConfig['payment_gateways'][i]['receiver']);
	}
}

var formerPlanField ={
					"system":true,
					"select_list" : false,
					"display" : false,
					"editable" : false,
					"multiple" : false,
					"field_name" : "former_plan",
					"unique" : false,
					"title" : "Former plan",
					"mandatory" : false,
	};
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], formerPlanField, 'subscriber');

// BRCD-2021 - Invoice translations support
const invoices = lastConfig['billrun']['invoices'];
if (invoices) {
	const language = invoices['language'];
	if (language) {
		const def = language['default'];
		if (!def) {
			lastConfig.billrun.invoices.language.default = 'en_GB';
		}
	} else {
		lastConfig.billrun.invoices.language = {'default': 'en_GB'};
	}
} else {
	lastConfig['billrun']['invoices'] = {'language': {'default': 'en_GB'}};
}

// BRCD - 2129: add embed_tax field
if (typeof lastConfig['taxes'] !== 'undefined' && typeof lastConfig['taxes']['fields'] !== 'undefined') {
	var embedTaxField = {
		"field_name": "embed_tax",
		"system": true,
		"title": "Embed Tax",
		"mandatory": true,
		"type": "boolean",
		"editable": true,
		"display": true,
		"description": "In case the tax should be embedded (included in the customer price), please check the box"
	};
	lastConfig = addFieldToConfig(lastConfig, embedTaxField, 'taxes')
}
// BRCD-1717
db.subscribers.getIndexes().forEach(function(index){
	var indexFields = Object.keys(index.key);
	if (index.unique && indexFields.length == 3 && indexFields[0] == 'sid' && indexFields[1] == 'from' && indexFields[2] == 'aid') {
                _dropIndex("subscribers", index.name)
		db.subscribers.createIndex({'sid': 1}, { unique: false, sparse: true, background: true });
	}
	else if ((indexFields.length == 1) && index.key.aid && index.sparse) {
                _dropIndex("subscribers", index.name)
		db.subscribers.createIndex({'aid': 1 }, { unique: false, sparse: false, background: true });
	}
})

// BRCD-1717
//if (db.lines.stats().sharded) {
//	sh.shardCollection("billing.subscribers", { "aid" : 1 } );
//}

// Migrate audit records in log collection into separated audit collection
db.log.find({"source":"audit"}).forEach(
	function(obj) {
		db.audit.insertOne(obj);
		db.log.deleteOne({_id:obj._id});
	}
);

// BRCD-1837: convert rates' "vatable" field to new tax mapping
db.rates.updateMany({tax:{$exists:0},$or:[{vatable:true},{vatable:{$exists:0}}]},{$set:{tax:[{type:"vat",taxation:"global"}]},$unset:{vatable:1}});
db.rates.updateMany({tax:{$exists:0},vatable:false},{$set:{tax:[{type:"vat",taxation:"no"}]},$unset:{vatable:1}});
db.services.updateMany({tax:{$exists:0},$or:[{vatable:true},{vatable:{$exists:0}}]},{$set:{tax:[{type:"vat",taxation:"global"}]},$unset:{vatable:1}});
db.services.updateMany({tax:{$exists:0},vatable:false},{$set:{tax:[{type:"vat",taxation:"no"}]},$unset:{vatable:1}});

// taxes collection indexes
_createCollection('taxes');
db.taxes.createIndex({'key':1, 'from': 1, 'to': 1}, { unique: true, background: true });
db.taxes.createIndex({'from': 1, 'to': 1 }, { unique: false , sparse: true, background: true });
db.taxes.createIndex({'to': 1 }, { unique: false , sparse: true, background: true });

lastConfig = runOnce(lastConfig, 'BRCD-3678-1', function () {
    //Suggestions Collection
    _createCollection('suggestions');
    _dropIndex("suggestions", "aid_1_sid_1_billrun_key_1_status_1_key_1_recalculationType_1_estimated_billrun_1")
    _dropIndex("suggestions", "aid_1_sid_1_billrun_key_1_status_1_key_1_recalculationType_1")
    db.suggestions.createIndex({'aid': 1, 'sid': 1, 'billrun_key': 1, 'status': 1, 'key':1, 'recalculation_type':1, 'estimated_billrun':1}, { unique: false , background: true});
    db.suggestions.createIndex({'status': 1 }, { unique: false , background: true});
});
// BRCD-1936: Migrate old discount structure to new discount structure
function isEmpty(obj) {
    for(var key in obj) {
        if(obj.hasOwnProperty(key))
            return false;
    }
    return true;
}

db.discounts.find({"discount_subject":{$exists: true}}).forEach(
	function(obj) {
		var subjectService;
		if (obj.discount_subject.service !== undefined) {
			subjectService = obj.discount_subject.service;
		} else {
			subjectService = {};
		}
		var subjectPlan;
		if (obj.discount_subject.plan !== undefined) {
			subjectPlan = obj.discount_subject.plan;
		} else {
			subjectPlan = {};
		}
		var oldParams = obj.params;
		obj.type = obj.discount_type;
		if (obj.prorated == false) {
			obj.proration = "no";
		} else {
			obj.proration = "inherited";
		}
		var plansInSubject = {};
		for (var planName in subjectPlan) {
			if (subjectPlan[planName].value !== undefined) {
				plansInSubject[planName] = subjectPlan[planName];
			} else {
				var plan = {};
				plan[planName] = {"value": subjectPlan[planName]};
				plansInSubject[planName] = {"value": subjectPlan[planName]};
			}
		}
		var servicesInSubject = {};
		for (var serviceName in subjectService) {
			if (subjectService[serviceName].value !== undefined) {
				servicesInSubject[serviceName] = subjectService[serviceName];
			} else {
				var service = {};
				service[serviceName] = {"value": subjectService[serviceName]};
				servicesInSubject[serviceName] = {"value": subjectService[serviceName]};
			}
		}
		obj.subject = {};
		if (isEmpty(plansInSubject) === false) {
			obj.subject.plan = plansInSubject;
		}
		if (isEmpty(servicesInSubject) === false) {
			obj.subject.service = servicesInSubject;
		}
		if (isEmpty(obj.subject)) {
			delete obj.subject;
		}
		var conditionObject = {};
		obj.params = {};
		var fieldsObject = {};
		var servicesValues = {};
		conditionObject["subscriber"] = {};
		if (oldParams.plan !== undefined) {
			fieldsObject = [{"field": "plan", "op": "eq", "value": oldParams.plan}];
			conditionObject["subscriber"]["fields"] = fieldsObject;
		}
		var serviceObject = {};
		var serviceValue = [];
		var servicesArray = [];
		if (oldParams.service !== undefined) {
			var serviceCondAmount = oldParams.service.length;
			for (var i = 0; i < serviceCondAmount; i++) {
				servicesArray.push(oldParams.service[i]);
			}
			serviceValue.push({"field": "name", "op": "in", "value":servicesArray})
			servicesValues = {"fields": serviceValue};
			serviceObject['any'] = [servicesValues];
			conditionObject["subscriber"]["service"] = serviceObject;
		}
		if (isEmpty(fieldsObject) === false || isEmpty(serviceObject) === false) {
			conditionObject["subscriber"] = [conditionObject["subscriber"]];
			obj.params.conditions = [conditionObject];
		}
		delete obj.discount_type;
		delete obj.discount_subject;
		delete obj.prorated;
		_collectionSave(db.discounts, obj);
	}
)

// BRCD-1971 - update prorated field
db.plans.find({ "prorated": { $exists: true } }).forEach(function (plan) {
	plan.prorated_start = plan.prorated;
	plan.prorated_end = plan.prorated;
	plan.prorated_termination = plan.prorated;
	delete plan.prorated;
	_collectionSave(db.plans, plan);
});
// BRCD-1241: convert events to new structure
if (typeof lastConfig.events !== 'undefined') {
	for (var eventType in lastConfig.events) {
		for (var eventId in lastConfig.events[eventType]) {
			for (var conditionId in lastConfig.events[eventType][eventId].conditions) {
				if (typeof lastConfig.events[eventType][eventId].conditions[conditionId].paths == 'undefined') {
					lastConfig.events[eventType][eventId].conditions[conditionId].paths = [{
							'path': lastConfig.events[eventType][eventId].conditions[conditionId].path,
					}];
					delete lastConfig.events[eventType][eventId].conditions[conditionId].path;
				}
			}
		}
	}
}
// BRCD-2367 : Fix for in_collection field is rejected when quering the account
if (lastConfig.subscribers !== undefined && lastConfig.subscribers.account !== undefined && lastConfig.subscribers.account.fields !== undefined) {
	var brcd_2367_accInCollVal = {
		"field_name" : "in_collection",
		"system" : true,
		"display" : false
	};
	if(!lastConfig.subscribers.account.fields.some(elm => elm.field_name === brcd_2367_accInCollVal.field_name )) {
		lastConfig.subscribers.account.fields.push(brcd_2367_accInCollVal);
	}
}

// BRCD-2070 - GSD - getSubscriberDetails
if (!lastConfig.subscribers.subscriber.type) {
	lastConfig.subscribers.subscriber.type = 'db';
}
if (!lastConfig.subscribers.account.type) {
	lastConfig.subscribers.account.type = 'db';
}
lastConfig = runOnce(lastConfig, 'BRCD-2556', function () {

// BRCD-1246 fix deprecated out plan balance structure
for (var i in lastConfig['usage_types']) {
//    print("BRCD-1246 " + i);
    var _usage_type = lastConfig['usage_types'][i].usage_type;
    var _balance_unset_key = "balance.totals.out_plan_" + _usage_type;
    var _balance_set_key = "balance.totals." + _usage_type;
    var _current_date = new Date();
    var _3months_ago = new Date(_current_date.setDate(_current_date.getDate()-90));
//    print(_balance_unset_key);
    var _query = {};
    _query[_balance_unset_key] = {"$exists": true};
    _query['to'] = {"$gte": _3months_ago};
    db.balances.find(_query).forEach(
        function(obj) {
            print("balance id: " + obj._id + " sid: " + obj.sid + " balance unset key " + _balance_unset_key);
            var _inc_query_part = {}, _set_query_part = {}, _update_query = {}, _inc_entry_key = "$inc", _set_entry_key = "$set", _unset_entry_key = "$unset";
            for (var j in obj.balance.totals['out_plan_' + _usage_type]) {
                _inc_query_part[_balance_set_key + "." + j] = obj.balance.totals['out_plan_' + _usage_type][j];
            }
            _set_query_part['BRCD-1246_out_plan_' + _usage_type] = obj.balance.totals['out_plan_' + _usage_type]; // this will keep old entry with new name
            _update_query[_inc_entry_key] = _inc_query_part;
            _update_query[_set_entry_key] = _set_query_part;
            _update_query[_unset_entry_key] = {};
            _update_query[_unset_entry_key][_balance_unset_key] = 1;
//            printjson(_update_query);
            db.balances.updateOne({_id:obj._id}, _update_query);
        }
    );
}
// ============================= BRCD-2556: split balances to monthly and add-on balance =====================================
const time = ISODate();
const services = getServices();

services.forEach(function (service) {
	const groups = getServiceGroups(service);
	groups.forEach(function (group) {
		const balances = getBalances(group);
		balances.forEach(function (balance) {
			// update/create add-on specific balance
			const query = {
				aid: balance['aid'],
				sid: balance['sid'],
				from: balance['from'],
				to: balance['to'],
				period: balance['period'],
				service_name: service['name'],
				connection_type: 'postpaid',
				added_by_script: {$exists: true},
				priority: {
					$exists: true,
					$ne: 0
				}
			};
			
			const setOnInsert = {
				aid: balance['aid'],
				sid: balance['sid'],
				from: balance['from'],
				to: balance['to'],
				period: balance['period'],
				start_period: balance['start_period'],
				connection_type: balance['connection_type'],
				current_plan: balance['current_plan'],
				plan_description: balance['plan_description'],
				priority: service['service_id'] || Math.floor(Math.random() * 10000000000000000) + 1,
				service_name: service['name'],
				added_by_script: ISODate(),
				['balance.groups.' + group['name'] + '.total']: balance['balance']['groups'][group['name']]['total'],
			};
			
			const inc = {
				['balance.groups.' + group['name'] + '.count']: balance['balance']['groups'][group['name']]['count'],
			};
			
			const set = {
				['balance.groups.' + group['name'] + '.left']: balance['balance']['groups'][group['name']]['left'],
			};
			
			// since monetary groups has no usaget - we can't update totals of monthly and add-on balances
			// this might cause bugs if we have a case of monetary group, and event on usagev
			// totals->[USAGET]->usagev will be incorrect in monthly and add-on balances
			if (group['type'] == 'cost') {
				inc['balance.groups.' + group['name'] + '.cost'] = balance['balance']['groups'][group['name']]['cost'];
				setOnInsert['balance.cost'] = 0;
			} else {
				setOnInsert['balance.totals.' + group['usaget'] + '.cost'] = 0;
				inc['balance.totals.' + group['usaget'] + '.usagev'] = balance['balance']['groups'][group['name']]['usagev'];
				inc['balance.totals.' + group['usaget'] + '.count'] = balance['balance']['groups'][group['name']]['count'];
				inc['balance.groups.' + group['name'] + '.usagev'] = balance['balance']['groups'][group['name']]['usagev'];
				
				// update monthly balance totals
				balance['balance']['totals'][group['usaget']]['usagev'] -= balance['balance']['groups'][group['name']]['usagev'];
				balance['balance']['totals'][group['usaget']]['count'] -= balance['balance']['groups'][group['name']]['count'];
			}
			
			const update = {
				$setOnInsert: setOnInsert,
				$inc: inc,
				$set: set
			};
			
			const options = {
				upsert: true
			};

			db.balances.updateOne(query, update, options);

			// remove group from monthly balance
			delete balance['balance']['groups'][group['name']];
			if (typeof Object.keys(balance['balance']['groups'])[0] == 'undefined') {
				delete balance['balance']['groups'];
			}
			balance['updated_by_script'] = ISODate();
			_collectionSave(db.balances, balance);
		});
	});
});

// get services aligned to cycle that are not included in any plan
function getServices() {
	const ret = [];
	const alignedToCycleServices = db.services.find({
		from: {$lte: time},
		to: {$gt: time},
		'include.groups': {$exists: true},
		$or: [
			{balance_period: {$exists: false}},
			{balance_period: 'default'}
		]
	});
	var servicesIncludedInPlans = db.plans.aggregate([
		{
			$match: {
				from: {$lte: time},
				to: {$gt: time},
				'include.services': {$exists: true}
			}
		},
		{
			$unwind: '$include.services'
		},
		{
			$project: {
				_id: 0,
				service: '$include.services'
			}
		}
	]);

	const servicesIncludedInPlansNames = servicesIncludedInPlans.map(x => x['service']);
	alignedToCycleServices.forEach(function (service) {
		if (servicesIncludedInPlansNames.indexOf(service['name']) == -1) {
			ret.push(service);
		}
	});

	return ret;
}

// get monthly balances with existing group
function getBalances(group) {
	const ret = db.balances.find({
		from: {$lte: time},
		to: {$gt: time},
		updated_by_script: {$exists: false},
		['balance.groups.' + group['name']]: {$exists: true},
		priority: 0
	});

	return ret;
}

function getServiceGroups(service) {
	const ret = [];
	if (typeof service['include'] == 'undefined' || typeof service['include']['groups'] == 'undefined') {
		return ret;
	}

	for (var group in service['include']['groups']) {
		const type = typeof service['include']['groups'][group]['cost'] !== 'undefined' ? 'cost' : 'usaget';
		ret.push({
			name: group,
			type,
			usaget: type == 'usaget' ? Object.keys(service['include']['groups'][group]['usage_types'])[0] : '',
			value: type == 'usaget' ? service['include']['groups'][group]['value'] : '',
			cost: type == 'cost' ? service['include']['groups'][group]['cost'] : '',
		});
	}

	return ret;
}
// ============================= BRCD-2556: END ==============================================================================
});

lastConfig = runOnce(lastConfig, 'BRCD-2791', function () {
	db.queue.find({
						calc_time: {$ne: false}
			}).forEach(function(line){
			if (typeof line['calc_time'] === "number") {
				line['calc_time'] = new Date(line['calc_time'] * 1000);
			}
			_collectionSave(db.queue, line);
		});
});

// BRCD-2491 convert Import mappers to not use '.' as mongo key
if (typeof lastConfig.import !== 'undefined' && typeof lastConfig.import.mapping !== 'undefined' && Array.isArray(lastConfig.import.mapping)) {
	const mapping = lastConfig.import.mapping;
	mapping.forEach((mapper, key) => {
		if (typeof mapper.map !== 'undefined') {
			if (!Array.isArray(mapper.map)) {
				let convertedMapper = [];
				Object.keys(mapper.map).forEach((field_name) => {
					convertedMapper.push({field: field_name,value: mapper.map[field_name]});
				});
				mapping[key].map = convertedMapper;
			}
		}
		if (typeof mapper.multiFieldAction !== 'undefined') {
			if (!Array.isArray(mapper.multiFieldAction)) {
				let convertedMultiFieldAction = [];
				Object.keys(mapper.multiFieldAction).forEach((field_name) => {
					convertedMultiFieldAction.push({field: field_name,value: mapper.multiFieldAction[field_name]});
				});
				mapping[key].multiFieldAction = convertedMultiFieldAction;
			}
		}
	});
	lastConfig.import.mapping = mapping;
}
// BRCD-3227 Add new custom 'rounding_rules' field to Products(Rates)
lastConfig = runOnce(lastConfig, 'BRCD-3227', function () {
    var fields = lastConfig['rates']['fields'];
    var found = false;
    for (var field_key in fields) {
            if (fields[field_key].field_name === "rounding_rules") {
                    found = true;
            }
    }
    if(!found) {
            fields.push({
                    "system":true,
                    "field_name":"rounding_rules",
            });
    }
    lastConfig['rates']['fields'] = fields;
});
// BRCD-2888 -adjusting config to the new invoice templates
if(lastConfig.invoice_export && /\/header\/header_tpl\.html$/.test(lastConfig.invoice_export.header)) {
	lastConfig.invoice_export.header = "/header/header_tpl.phtml";
}
if(lastConfig.invoice_export && /\/footer\/footer_tpl\.html$/.test(lastConfig.invoice_export.footer)) {
	lastConfig.invoice_export.footer = "/footer/footer_tpl.phtml";
}
// BRCD-2888 -adjusting config to the new invoice templates
if(lastConfig.invoice_export && /\.html$/.test(lastConfig.invoice_export.header)) {
	lastConfig.invoice_export.header = "/header/header_tpl.phtml";
}
if(lastConfig.invoice_export && /\.html$/.test(lastConfig.invoice_export.footer)) {
	lastConfig.invoice_export.footer = "/footer/footer_tpl.phtml";
}

_dropIndex("archive", "sid_1_session_id_1_request_num_");
_dropIndex("archive", "session_id_1_request_num_");
_dropIndex("archive", "sid_1_call_reference_1");
_dropIndex("archive", "call_reference_1");
if (db.serverStatus().ok == 0) {
	print('Cannot shard archive collection - no permission')
} else if (db.serverStatus().process == 'mongos') {
    // please run manually the sharding.js instead of the lagacy commands below
//	var _dbName = db.getName();
//	sh.shardCollection(_dbName + ".archive", {"stamp": 1});
//	// BRCD-2099 - sharding rates, billrun and balances
//	sh.shardCollection(_dbName + ".rates", { "key" : 1 } );
//	sh.shardCollection(_dbName + ".billrun", { "aid" : 1, "billrun_key" : 1 } );
//	sh.shardCollection(_dbName + ".balances",{ "aid" : 1, "sid" : 1 }  );
//        // BRCD-2244 audit sharding
//	sh.shardCollection(_dbName + ".audit",  { "stamp" : 1 } );
//        // BRCD-2185 sharding queue as added support for sharded collection transaction
//	sh.shardCollection(_dbName + ".queue", { "stamp" : 1 } );
}
/*** BRCD-2634 Fix limited cycle(s) service (addon) align to the cycle. ***/
lastConfig = runOnce(lastConfig, 'BRCD-2634', function () {
	// Find all services that are limited by cycles and align to the cycle
	var _limited_aligned_cycles_services = db.services.distinct("name", { balance_period: { $exists: 0 }, "price.to": { $ne: "UNLIMITED" } });
	//printjson(_limited_aligned_cycles_services);
	// we are assuming that the script will be run until 2030 (services will be created until 2030), and will be expired until 2050 (limited cycles applied)
	db.subscribers.find({ to: { $gt: ISODate() }, services: { $elemMatch: { name: { $in: _limited_aligned_cycles_services }, to: { $gt: ISODate("2050-01-01") }, creation_time: { $lt: ISODate("2030-01-01") } } } }).forEach(
		function (obj) {
			//                printjson(obj); // debug log
			for (var subServiceObj in obj.services) {
				//                    print("handle " + subServiceObj + " " + obj.services[subServiceObj].name);
				serviceObj = db.services.findOne({ name: obj.services[subServiceObj].name, to: { $gt: ISODate() } });
				if (serviceObj) {
					cycleCount = serviceObj.price[serviceObj.price.length - 1].to;
					//                    print("add months: " + cycleCount);
					if (cycleCount != 'UNLIMITED' && !(serviceObj.hasOwnProperty('balance_period'))) {
						//                            print("to before: " + obj.services[subServiceObj].to);
						var origToDay = obj.services[subServiceObj].to.getDate();
						obj.services[subServiceObj].to = new Date(obj.services[subServiceObj].from);
						obj.services[subServiceObj].to.setMonth(obj.services[subServiceObj].from.getMonth() + parseInt(cycleCount));
						//did  we  rolled  over to the next month
						if (origToDay - 1 > obj.services[subServiceObj].to.getDate()) {
							obj.services[subServiceObj].to.setMonth(obj.services[subServiceObj].from.getMonth() + parseInt(cycleCount) + 1);
							obj.services[subServiceObj].to.setDate(lastConfig.billrun.charging_day.v)
							obj.services[subServiceObj].to.setHours(0, 0, 0, 0);
						}
						//obj.services[subServiceObj].to.setDate(lastConfig.billrun.charging_day.v)
						//obj.services[subServiceObj].to.setHours(0,0,0,0);
						//                        print("to after: " + obj.services[subServiceObj].to);
					}
				}
			}
			//                printjson(obj); // debug log
			_collectionSave(db.subscribers, obj);
		}
	);
});

db.subscribers.createIndex({'invoicing_day': 1 }, { unique: false, sparse: false, background: true });
db.billrun.createIndex( { 'billrun_key': -1, 'attributes.invoicing_day': -1 },{unique: false, background: true });
_dropIndex("billrun", "billrun_key_-1");
//BRCD-2042 - charge.not_before migration script
db.bills.find({'charge.not_before':{$exists:0}, 'due_date':{$exists:1}}).forEach(
	function(obj) {
		if (typeof obj['charge'] === 'undefined') {
			obj['charge'] = {};
		}
		obj['charge']['not_before'] = obj['due_date'];
		_collectionSave(db.bills, obj);
	}
)
db.billrun.find({'charge.not_before':{$exists:0}, 'due_date':{$exists:1}}).forEach(
	function(obj) {
		if (typeof obj['charge'] === 'undefined') {
			obj['charge'] = {};
		}
		obj['charge']['not_before'] = obj['due_date'];
		_collectionSave(db.billrun, obj);
	}
)

//BRCD-2452 reformat paid_by and pays objects to array format
var bills = db.bills.find({
	$or: [
		{"pays.inv": {$exists: 1}},
		{"pays.rec": {$exists: 1}},
		{"paid_by.inv": {$exists: 1}},
		{"paid_by.rec": {$exists: 1}}
	]
});
bills.forEach(function (bill) {
	var relatedBills = [];
	var currentBillsKey;

	if (typeof bill['pays'] !== 'undefined') {
		currentBillsKey = 'pays';
	} else if (typeof bill['paid_by'] !== 'undefined') {
		currentBillsKey = 'paid_by';
	}

	if (typeof bill[currentBillsKey] != 'undefined') {
		for (type in bill[currentBillsKey]) {
			for (id in bill[currentBillsKey][type]) {
				relatedBills.push({
					"type": type,
					"id": type === 'inv' ? parseInt(id) : id,
					"amount": parseFloat(bill[currentBillsKey][type][id])
				});
			}
		}

		bill[currentBillsKey] = relatedBills;
		_collectionSave(db.bills, bill);
	}
});

// BRCD-2772 - add webhooks supports all audit collection field should be lowercase
db.audit.updateMany({"collection" : "Login"}, {$set:{"collection":"login"}});

//BRCD-2855 Oauth support
lastConfig = runOnce(lastConfig, 'BRCD-2855', function () {
    // create collections
    _createCollection("oauth_clients");
    _createCollection("oauth_access_tokens");
    _createCollection("oauth_authorization_codes");
    _createCollection("oauth_refresh_tokens");
    _createCollection("oauth_users");
    _createCollection("oauth_scopes");
    _createCollection("oauth_jwt");

    // create indexes
    db.oauth_clients.createIndex({'client_id': 1 });
    db.oauth_access_tokens.createIndex({'access_token': 1 });
    db.oauth_authorization_codes.createIndex({'authorization_code': 1 });
    db.oauth_refresh_tokens.createIndex({'refresh_token': 1 });
    db.oauth_users.createIndex({'username': 1 });
    db.oauth_scopes.createIndex({'oauth_scopes': 1 });
    
    var _obj;
    for (var secretKey in lastConfig.shared_secret) {
        secret = lastConfig.shared_secret[secretKey]
        if (secret.name == null || secret.name == '') {
            continue;
        }
        _obj = {
            "client_id": secret.name,
            "client_secret": secret.key,
            "grant_types": 'client_credentials',
            "scope": 'global',
            "user_id": null
        };
        db.oauth_clients.insertOne(_obj)
    }

})
// BRCD-2772 add webhooks plugin to the UI
runOnce(lastConfig, 'BRCD-2772', function () {
    _webhookPluginsSettings = {
        "name": "webhooksPlugin",
        "enabled": false,
        "system": true,
        "hide_from_ui": false
    };
    lastConfig['plugins'].push(_webhookPluginsSettings);
});

// BRCD-2936: add email authentication template
if (typeof lastConfig['email_templates']['email_authentication'] === 'undefined') {
	lastConfig['email_templates']['email_authentication'] = {
		'subject': 'BillRun Customer Portal - Email Address Verification',
		'content': '<pre>\nHello [[name]],\n\nPlease verify your E-mail address by clicking on the link below:\nhttp://billrun/callback?token=[[token]]\n\nFor any questions, please contact us at [[company_email]].\n\n[[company_name]]</pre>\n',
		'html_translation': [
			'name',
			'token',
			'verification_link',
			'company_email',
        	'company_name',
		]
	};
}
lastConfig = runOnce(lastConfig, 'BRCD-3527', function () {
    var inCollectionField = 
            {
                    "field_name": "in_collection",
                    "system": true,
                    "display": false
            };
    lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], inCollectionField, 'account');
		});

// BRCD-3325 : Add default condition - the "rejection_required" condition doesn't exist.
lastConfig = runOnce(lastConfig, 'BRCD-3325', function () {
    var rejection_required_cond = {
        "field": "aid",
				"op" : "exists",
				"value" : false
    };
		lastConfig['collection']['settings']['rejection_required'] = {'conditions':{'customers':[rejection_required_cond]}};
});
db.lines.createIndex({'sid' : 1, 'billrun' : 1, 'urt' : 1}, { unique: false , sparse: false, background: true });

//BRCD-3307:Refactoring : remove "balance_effective_date" field from payments
runOnce(lastConfig, 'BRCD-3307', function () {
	db.bills.find({'balance_effective_date': {$exists: 1}}).forEach(
			function (obj) {
				obj['urt'] = obj['balance_effective_date'];
				delete obj['balance_effective_date'];
				_collectionSave(db.bills, obj);
			}
	)
});

lastConfig = runOnce(lastConfig, 'BRCD-3806', function () {
    //Suggestions Collection
    _dropIndex("suggestions", "aid_1_sid_1_billrun_key_1_status_1_key_1_recalculation_type_1_estimated_billrun_1")
    db.suggestions.createIndex({'aid': 1, 'sid': 1, 'billrun_key': 1, 'status': 1, 'key':1, 'recalculation_type':1, 'estimated_billrun':1}, { unique: false , background: true});
});

// BRCD-3618 configure full_calculation date field
lastConfig = runOnce(lastConfig, 'BRCD-3618', function () {
	lastConfig['lines']['reference_fields'] = ['full_calculation'];
});

// BRCD-3432 add BillRun' metabase plugin
runOnce(lastConfig, 'BRCD-3432', function () {
    var mbPluginsSettings = {
        "name": "metabaseReportsPlugin",
        "enabled": false,
        "system": true,
        "hide_from_ui": true,
				"configuration" : {
					"values" : {
						"metabase_details" : {},
						"export" : {},
						"added_data" : {},
						"reports" : []
					}
				}
    };
    lastConfig['plugins'].push(mbPluginsSettings);
});
// BRCD-3325 : Add default condition - the "rejection_required" condition doesn't exist.
runOnce(lastConfig, 'BRCD-3325', function () {
    var rejection_required_cond = {
        "field": "aid",
				"op" : "exists",
				"value" : false
    };
		lastConfig['collection']['settings']['rejection_required'] = {'conditions':{'customers':[rejection_required_cond]}};
});

runOnce(lastConfig, 'BRCD-3413', function () {
        if(lastConfig['email_templates']['invoice_ready']['placeholders'] === undefined){
            lastConfig['email_templates']['invoice_ready']['placeholders'] = [];
        }
	lastConfig['email_templates']['invoice_ready']['placeholders'].push(
            {
                name: "start_date",
                title: "Billing cycle start date",
                path: "start_date",
                type: "date",
                system:true
            }, 
            {
                name: "end_date",
                title: "Billing cycle end date",
                path: "end_date",
                type: "date",
                system:true
            },
            {
                name: "invoice_current_balance",
                title: "Invoice current balance",
                path: "totals.current_balance.after_vat",
                system:true
            }, 
            {
                name: "invoice_due_date",
                title: "Invoice due date",
                path: "due_date",
                type: "date",
                system:true
            }
        );
});

//BRCD-3421: migrate webhooks from config to separate collection
runOnce(lastConfig, 'BRCD-3421', function () {
    // create webhooks collection
    _createCollection('webhooks');
    db.webhooks.createIndex({'webhook_id': 1}, { unique: true , background: true});
    db.webhooks.createIndex({'module' : 1, 'action' : 1 }, { unique: false , background: true});

    if (!lastConfig.hasOwnProperty('plugins')) {
        return;
    }
    
    searchIndex = lastConfig.plugins.findIndex((plugin) => plugin.name == 'webhooksPlugin');
    if (searchIndex === false || searchIndex === -1) {
        return;
    }
    if (!lastConfig.plugins[searchIndex].hasOwnProperty('configuration') || 
            !lastConfig.plugins[searchIndex].configuration.hasOwnProperty('values') ||
            !lastConfig.plugins[searchIndex].configuration.values.hasOwnProperty('config')) {
        return;
    }
    var _insertWebhooks = lastConfig.plugins[searchIndex].configuration.values.config;
    if (!_insertWebhooks || !_insertWebhooks.length) {
        return;
    }
    db.webhooks.insertMany(_insertWebhooks);
});
db.lines.createIndex({'sid' : 1, 'billrun' : 1, 'urt' : 1}, { unique: false , sparse: false, background: true });
//BRCD-2336: Can't "closeandnew" a prepaid bucket
lastConfig = runOnce(lastConfig, 'BRCD-2336', function () {

    db.prepaidincludes.dropIndexes();
    db.prepaidincludes.createIndex({from : 1, to: 1, name : 1, external_id : 1}, {unique: true});
    db.prepaidincludes.createIndex({external_id : 1}, {unique: false});
    db.prepaidincludes.createIndex({name : 1}, {unique: false});
});
var invoice_lang_field = {
	"select_list": true,
	"display": true,
	"editable": true,
	"system": true,
	"field_name": "invoice_language",
	"default_value": "en_GB",
	"show_in_list": true,
	"title": "Invoice language",
	"mandatory": true,
	"changeable_props": [
		"select_options"
	],
	"select_options": "en_GB,fr_CH,de_CH"
};
lastConfig['subscribers'] = addFieldToConfig(lastConfig['subscribers'], invoice_lang_field, 'account');

// BRCD-3942
var debtCollectionPluginFound = false;
for (var i = 0; i < lastConfig.plugins.length; i++) {
	if (lastConfig.plugins[i]['name'] === "debtCollectionPlugin") {
		debtCollectionPluginFound = true;
		if (lastConfig.plugins[i]['configuration'] === undefined){
			lastConfig.plugins[i]['configuration'] = {};
		}
		if (lastConfig.plugins[i]['configuration']['values'] === undefined){
			lastConfig.plugins[i]['configuration']['values'] = {};
		}
		if (lastConfig.plugins[i]['configuration']['values']['immediateEnter'] === undefined){
			lastConfig.plugins[i]['configuration']['values']['immediateEnter'] = false;
		}
		if (lastConfig.plugins[i]['configuration']['values']['immediateExit'] === undefined){
			lastConfig.plugins[i]['configuration']['values']['immediateExit'] = true;
		}
	}
}

if (!debtCollectionPluginFound) {
	lastConfig.plugins.push({
		'name' : 'debtCollectionPlugin',
		'enabled' : true,
		'system' : true,
		'hide_from_ui' : false,
		'configuration' : {'values' : {'immediateEnter' : false, 'immediateExit' : true}}
	})
}

// BRCD-3890 Remove invoice_label' core field
lastConfig = runOnce(lastConfig, 'BRCD-3890', function () {
	lastConfig = removeFieldFromConfig(lastConfig, 'invoice_label', 'rates');
	lastConfig = removeFieldFromConfig(lastConfig, 'invoice_label', 'plans');
	lastConfig = removeFieldFromConfig(lastConfig, 'invoice_label', 'services');
	lastConfig = removeFieldFromConfig(lastConfig, 'invoice_label', 'discounts');
	lastConfig = removeFieldFromConfig(lastConfig, 'invoice_label', 'charges');
});

// BRCD-4010 : Set default value for missing instance_name
lastConfig = runOnce(lastConfig, 'BRCD-4010', function () {
	db.subscribers.find({
		'payment_gateway.active': {$exists: 1},
		'payment_gateway.active.instance_name': {$exists: 0},
		type: 'account'
	}).forEach(
		function(account) {
			account.payment_gateway.active.instance_name = account.payment_gateway.active.name;
			_collectionSave(db.subscribers, account);
		}
	);
});

lastConfig = runOnce(lastConfig, 'BRCD-4172', function () {
	db.bills.createIndex({'urt': 1 }, { unique: false, background: true});
})

// BRCD-4102 Migrate all cancel bills to be 
lastConfig = runOnce(lastConfig, 'BRCD-4102', function () {
	var cancelBills = db.bills.find({cancel:{$exists:1}, urt:ISODate("1970-01-01T00:00:00.000Z")});
	var bulkUpdate = [];
	var maxWriteBatchSize =db.runCommand(
		{
		  hello: 1
		}
	 )['maxWriteBatchSize'];
	var _cancelBillsCount = cancelBills.toArray().length;
	print("Starts to update " + _cancelBillsCount + " bills");
	if (_cancelBillsCount === 0) {
		return;
	}
	for (var i=0; i<cancelBills.toArray().length; i++) {
	    var update = { "updateOne" : {
	        "filter" : {"_id" : cancelBills[i]['_id']},
	        "update" :  {"$set" : {"urt" : cancelBills[i]['_id'].getTimestamp()}}
	    }};
	    bulkUpdate.push(update);
		if (i!=0 && i%maxWriteBatchSize==0) {
			db.bills.bulkWrite(bulkUpdate);
			print("Updated " + maxWriteBatchSize + " cancellation bills, continue..")
			bulkUpdate = []
		}
	}
	db.bills.bulkWrite(bulkUpdate);
	print("Updated total of " + i + " bills!")
});
lastConfig = runOnce(lastConfig, 'BRCD-4126', function () {
	db.oauth_clients.updateMany({"grant_types" : null, "scope" : {"$ne":"selfcare account"}}, {$set:{"grant_types" : "client_credentials"}});
	db.oauth_clients.updateMany({"scope" : null}, {$set:{"scope" : "global"}});
});

// BRCD-4297 Correct end date of services with limited cycles
lastConfig = runOnce(lastConfig, 'BRCD-4297', function () {
	function addMonthsToDate(fromDate, monthsToAdd) {
		const newDate = new Date(fromDate); // Create a new Date object from the provided fromDate
	  
		// Add the specified number of months to the newDate
		newDate.setMonth(newDate.getMonth() + parseInt(monthsToAdd));
	  
		return newDate.toISOString(); // Return the new date as an ISO string
	  }
	
	var limited_cycle_services = db.services.aggregate([{$match: {balance_period: {$exists: false}, prorated: true, price: {$size: 1, $elemMatch: {to: {$ne: "UNLIMITED"}}}}}, {$group: {_id: "$name", month_limit: {$addToSet: "$price.to"}}}, {$match: {month_limit: {$size: 1}}}, {$unwind: "$month_limit"},{$unwind: "$month_limit"}])
	var today = new Date();
	var lastYear = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
	var lastYearISO = lastYear.toISOString();
	
	var all_service_keys = [];
	var service_and_cycle_limit = [];
	
	limited_cycle_services.forEach(service => {
		all_service_keys.push(service._id);
		service_and_cycle_limit[service._id] = service.month_limit;
	});
	
	print("Subscriber ID, Service Key, Start Date, cycles, Original End Date, Corrected End Date")
	var subscribers = db.subscribers.find({'services.name': {$in: all_service_keys}, to: {$gt: ISODate(lastYearISO)}});
	subscribers.forEach(subscriber => {
		for (var i = 0; i < subscriber.services.length; i++) {
			if(all_service_keys.includes(subscriber.services[i].name)) {
				var corrected_end_date = addMonthsToDate(subscriber.services[i].from, service_and_cycle_limit[subscriber.services[i].name]);
				if (subscriber.services[i].to.toISOString() != corrected_end_date) {
					print(subscriber.sid + "," + subscriber.services[i].name + "," + subscriber.services[i].from.toISOString() + "," + service_and_cycle_limit[subscriber.services[i].name] + "," + subscriber.services[i].to.toISOString() + "," + corrected_end_date);
					subscriber.services[i].to = ISODate(corrected_end_date);
				}
			}
		}
		db.subscribers.save(subscriber);
	})
	
	var services_with_revisions_with_differernt_cycles = db.services.aggregate([{$match: {balance_period: {$exists: false}, prorated: true, price: {$elemMatch: {to: {$ne: "UNLIMITED"}}}}}, {$group: {_id: "$name", month_limit: {$addToSet: "$price.to"}}}, {$match: {$expr: {$gt: [{$size: "$month_limit"}, 1]}}}])
	services_with_revisions_with_differernt_cycles.forEach(service => {
		printjson("BRCD-4297: Service with that the month limit has been changed and will require a more complex fix: " + service._id);
	});
});

// BRCD-4217 Migrate all rejection bills urt
lastConfig = runOnce(lastConfig, 'BRCD-4217', function () {
	print("BRCD-4217 - Migrating rejection bills urt..")
	var rejectionBills = db.bills.find({rejection:true, urt:ISODate("1970-01-01T00:00:00.000Z")});
	var bulkUpdate = [];
	var maxWriteBatchSize = 1000;
	var _rejectionBillsCount = rejectionBills.toArray().length;
	print("Starts to update " + _rejectionBillsCount + " bills");
	if (_rejectionBillsCount === 0) {
		return;
	}
	for (var i=0; i<rejectionBills.toArray().length; i++) {
	    var update = { "updateOne" : {
	        "filter" : {"_id" : rejectionBills[i]['_id']},
	        "update" :  {"$set" : {"urt" : rejectionBills[i]['_id'].getTimestamp()}}
	    }};
	    bulkUpdate.push(update);
		if (i!=0 && i%maxWriteBatchSize==0) {
			db.bills.bulkWrite(bulkUpdate);
			print("Updated " + maxWriteBatchSize + " rejection bills, continue..")
			bulkUpdate = []
		}
	}
	db.bills.bulkWrite(bulkUpdate);
	print("Updated total of " + i + " bills!")
});


// BRCD-4266 - Set default searchable fields for dynamic entity lists
lastConfig = runOnce(lastConfig, 'BRCD-4266', function () {
	print("START\tBRCD-4266 - Set default searchable fields for dynamic entity lists..");
	// Account
	if (typeof lastConfig['subscribers'] !== 'undefined' && typeof lastConfig['subscribers']['account'] !== 'undefined' && typeof lastConfig['subscribers']['account']['fields'] !== 'undefined') {
		var accountFields = lastConfig['subscribers']['account']['fields'];
		var defaultAccountSearchableFields = ['aid', 'firstname', 'lastname', 'first_name', 'last_name'];
		for (var field_key in accountFields) {
			if (defaultAccountSearchableFields.includes(accountFields[field_key].field_name)) {
				accountFields[field_key].searchable = true;
			}
		}
		lastConfig['subscribers']['account']['fields'] = accountFields;
		print("\t* update account fields");
	}

	// Subscriber
	if (typeof lastConfig['subscribers'] !== 'undefined' && typeof lastConfig['subscribers']['subscriber'] !== 'undefined' && typeof lastConfig['subscribers']['subscriber']['fields'] !== 'undefined') {
		var subscriberFields = lastConfig['subscribers']['subscriber']['fields'];
		var defaultSubscriberSearchableFields = ['sid', 'firstname', 'lastname', 'first_name', 'last_name'];
		for (var field_key in subscriberFields) {
			if (defaultSubscriberSearchableFields.includes(subscriberFields[field_key].field_name)) {
				subscriberFields[field_key].searchable = true;
			}
		}
		lastConfig['subscribers']['subscriber']['fields'] = subscriberFields;
		print("\t* update subscriber fields");
	}

	// Tax
	if (typeof lastConfig['taxes'] !== 'undefined' && typeof lastConfig['taxes']['fields'] !== 'undefined') {
		var taxesFields = lastConfig['taxes']['fields'];
		var defaultTaxesSearchableFields = ['description', 'key'];
		for (var field_key in taxesFields) {
			if (defaultTaxesSearchableFields.includes(taxesFields[field_key].field_name)) {
				taxesFields[field_key].searchable = true;
			}
		}
		lastConfig['taxes']['fields'] = taxesFields;
		print("\t* update taxes fields");
	}

	// discounts
	if (typeof lastConfig['discounts'] !== 'undefined' && typeof lastConfig['discounts']['fields'] !== 'undefined') {
		var discountsFields = lastConfig['discounts']['fields'];
		var defaultDiscountsSearchableFields = ['description', 'key'];
		for (var field_key in discountsFields) {
			if (defaultDiscountsSearchableFields.includes(discountsFields[field_key].field_name)) {
				discountsFields[field_key].searchable = true;
			}
		}
		lastConfig['discounts']['fields'] = discountsFields;
		print("\t* update discounts fields");
	}

	// Plans
	if (typeof lastConfig['plans'] !== 'undefined' && typeof lastConfig['plans']['fields'] !== 'undefined') {
		var plansFields = lastConfig['plans']['fields'];
		var defaultPlansSearchableFields = ['name', 'description'];
		for (var field_key in plansFields) {
			if (defaultPlansSearchableFields.includes(plansFields[field_key].field_name)) {
				plansFields[field_key].searchable = true;
			}
		}
		lastConfig['plans']['fields'] = plansFields;
		print("\t* update plans fields");
	}

	// Services
	if (typeof lastConfig['services'] !== 'undefined' && typeof lastConfig['services']['fields'] !== 'undefined' ) {
		var servicesFields = lastConfig['services']['fields'];
		var defaultServicesSearchableFields = ['description', 'name'];
		for (var field_key in servicesFields) {
			if (defaultServicesSearchableFields.includes(servicesFields[field_key].field_name)) {
				servicesFields[field_key].searchable = true;
			}
		}
		lastConfig['services']['fields'] = servicesFields;
		print("\t* update services fields");
	}

	// Rates
	if (typeof lastConfig['rates'] !== 'undefined' && typeof lastConfig['rates']['fields'] !== 'undefined' ) {
		var ratesFields = lastConfig['rates']['fields'];
		var defaultRatesSearchableFields = ['key', 'description'];
		for (var field_key in ratesFields) {
			if (defaultRatesSearchableFields.includes(ratesFields[field_key].field_name)) {
				ratesFields[field_key].searchable = true;
			}
		}
		lastConfig['rates']['fields'] = ratesFields;
		print("\t* update rates fields");
	}
	print("DONE\tBRCD-4266");
});

runOnce(lastConfig, 'BRCD-4368', function () {
	print("Adding first_installment field to credit installments with only 1 installment");
	db.lines.find({type:"credit", installment_no:1, first_installment:{$exists:false}}).forEach(function(doc) {db.lines.update({ _id: doc._id },{ $set: { first_installment: doc.stamp } });});
	print("Finished updating installments");
});

//BRCD-4306 MB plugin shouldn't be hide from UI
runOnce(lastConfig, 'BRCD-4306', function () {
	for (var i = 0; i < lastConfig['plugins'].length; i++) {
		if (lastConfig['plugins'][i]['name'] == "metabaseReportsPlugin") {
			lastConfig['plugins'][i]['hide_from_ui'] = false;
		}
	}
});

db.config.insertOne(lastConfig);
db.lines.createIndex({'aid': 1, 'billrun': 1, 'urt' : 1}, { unique: false , sparse: false, background: true });
_dropIndex("lines", "aid_1_urt_1");
db.rebalance_queue.createIndex({"creation_date": 1, "end_time" : 1}, {unique: false, "background": true});
_dropIndex("rebalance_queue", "aid_1_billrun_key_1");
db.rebalance_queue.createIndex({"aid": 1, "billrun_key": 1}, {unique: false, "background": true});
