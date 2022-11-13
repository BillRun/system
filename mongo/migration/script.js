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
// =============================================================================

// BRCD-1077 Add new custom 'tariff_category' field to Products(Rates).
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
var fields = lastConfig['rates']['fields'];
var found = false;
var invoice_label_found = false;
for (var field_key in fields) {
	if (fields[field_key].field_name === "tariff_category") {
		found = true;
	}
	if (fields[field_key].field_name === "invoice_label") {
		invoice_label_found = true;
		fields[field_key].default_value = "";
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
if(!invoice_label_found) {
	fields.push({
		"system":true,
		"display":true,
		"editable":true,
		"field_name":"invoice_label",
		"default_value":"",
		"show_in_list":true,
		"title":"Invoice label"
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
db.rates.update({'tariff_category': {$exists: false}},{$set:{'tariff_category':'retail'}},{multi:1});

// BRCD-938: Option to not generate pdfs for the cycle
if (typeof lastConfig['billrun']['generate_pdf']  === 'undefined') {
	lastConfig['billrun']['generate_pdf'] = {"v": true ,"t" : "Boolean"};
}

// BRCD-441 -Add plugin support
if (!lastConfig['plugins']) {
	lastConfig.plugins = ["calcCpuPlugin", "csiPlugin", "autorenewPlugin"];
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
	lastConfig.invoice_export.header = "/application/views/invoices/header/header_tpl.html";
	lastConfig.invoice_export.footer = "/application/views/invoices/footer/footer_tpl.html";
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
				db.subscribers.save(obj2);
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

// BRCD-1415 - add system field to account (invoice_shipping_method)
var fields = lastConfig['subscribers']['account']['fields'];
var found = false;
for (var field_key in fields) {
	if (fields[field_key].field_name === "invoice_shipping_method") {
		found = true;
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


db.rebalance_queue.ensureIndex({"creation_date": 1}, {unique: false, "background": true})

// BRCD-1443 - Wrong billrun field after a rebalance
db.billrun.update({'attributes.invoice_type':{$ne:'immediate'}, billrun_key:{$regex:/^[0-9]{14}$/}},{$set:{'attributes.invoice_type': 'immediate'}},{multi:1});
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
		
		db.subscribers.save(obj);
	}
);
// BRCD-1552 collection
if (typeof lastConfig['collection'] === 'undefined') {
	lastConfig['collection'] = {'settings': {}};
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
db.counters.dropIndex("coll_1_oid_1");
db.counters.ensureIndex({coll: 1, key: 1}, { sparse: false, background: true});

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
db.bills.ensureIndex({'invoice_id': 1 }, { unique: false, background: true});

// BRCD-1516 - Charge command filtration
db.bills.ensureIndex({'billrun_key': 1 }, { unique: false, background: true});
db.bills.ensureIndex({'invoice_date': 1 }, { unique: false, background: true});

// BRCD-1552 collection
db.collection_steps.dropIndex("aid_1");
db.collection_steps.dropIndex("trigger_date_1_done_1");
db.collection_steps.ensureIndex({'trigger_date': 1}, { unique: false , sparse: true, background: true });
db.collection_steps.ensureIndex({'extra_params.aid':1 }, { unique: false , sparse: true, background: true });

//BRCD-1541 - Insert bill to db with field 'paid' set to 'false'
db.bills.update({type: 'inv', paid: {$exists: false}, due: {$gte: 0}}, {$set: {paid: '0'}}, {multi: true});

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
			db.subscribers.save(sub);
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
	
	db.taxes.insert(vat);
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
		db.subscribers.dropIndex(index.name);
		db.subscribers.ensureIndex({'sid': 1}, { unique: false, sparse: true, background: true });
	}
	else if ((indexFields.length == 1) && index.key.aid && index.sparse) {
		db.subscribers.dropIndex(index.name);
		db.subscribers.ensureIndex({'aid': 1 }, { unique: false, sparse: false, background: true });
	}
})

// BRCD-1717
//if (db.lines.stats().sharded) {
//	sh.shardCollection("billing.subscribers", { "aid" : 1 } );
//}

// Migrate audit records in log collection into separated audit collection
db.log.find({"source":"audit"}).forEach(
	function(obj) {
		db.audit.save(obj);
		db.log.remove(obj._id);
	}
);

// BRCD-1837: convert rates' "vatable" field to new tax mapping
db.rates.update({tax:{$exists:0},$or:[{vatable:true},{vatable:{$exists:0}}]},{$set:{tax:[{type:"vat",taxation:"global"}]},$unset:{vatable:1}}, {multi: true});
db.rates.update({tax:{$exists:0},vatable:false},{$set:{tax:[{type:"vat",taxation:"no"}]},$unset:{vatable:1}}, {multi: true});
db.services.update({tax:{$exists:0},$or:[{vatable:true},{vatable:{$exists:0}}]},{$set:{tax:[{type:"vat",taxation:"global"}]},$unset:{vatable:1}}, {multi: true});
db.services.update({tax:{$exists:0},vatable:false},{$set:{tax:[{type:"vat",taxation:"no"}]},$unset:{vatable:1}}, {multi: true});

// taxes collection indexes
db.createCollection('taxes');
db.taxes.ensureIndex({'key':1, 'from': 1, 'to': 1}, { unique: true, background: true });
db.taxes.ensureIndex({'from': 1, 'to': 1 }, { unique: false , sparse: true, background: true });
db.taxes.ensureIndex({'to': 1 }, { unique: false , sparse: true, background: true });

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
		db.discounts.save(obj);
	}
)

// BRCD-1971 - update prorated field
db.plans.find({ "prorated": { $exists: true } }).forEach(function (plan) {
	plan.prorated_start = plan.prorated;
	plan.prorated_end = plan.prorated;
	plan.prorated_termination = plan.prorated;
	delete plan.prorated;
	db.plans.save(plan);
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
            db.balances.update({_id:obj._id}, _update_query);
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
				$set: set,
			};
			
			const options = {
				upsert: true
			};

			db.balances.update(query, update, options);

			// remove group from monthly balance
			delete balance['balance']['groups'][group['name']];
			if (typeof Object.keys(balance['balance']['groups'])[0] == 'undefined') {
				delete balance['balance']['groups'];
			}
			balance['updated_by_script'] = ISODate();
			db.balances.save(balance);
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
if(lastConfig.invoice_export && /\.html$/.test(lastConfig.invoice_export.header)) {
	lastConfig.invoice_export.header = "/header/header_tpl.phtml";
}
if(lastConfig.invoice_export && /\.html$/.test(lastConfig.invoice_export.footer)) {
	lastConfig.invoice_export.footer = "/footer/footer_tpl.phtml";
}

db.archive.dropIndex('sid_1_session_id_1_request_num_-1')
db.archive.dropIndex('session_id_1_request_num_-1')
db.archive.dropIndex('sid_1_call_reference_1')
db.archive.dropIndex('call_reference_1')
if (db.serverStatus().ok == 0) {
	print('Cannot shard archive collection - no permission')
} else if (db.serverStatus().process == 'mongos') {
	sh.shardCollection("billing.archive", {"stamp": 1});
	// BRCD-2099 - sharding rates, billrun and balances
	sh.shardCollection("billing.rates", { "key" : 1 } );
	sh.shardCollection("billing.billrun", { "aid" : 1, "billrun_key" : 1 } );
	sh.shardCollection("billing.balances",{ "aid" : 1, "sid" : 1 }  );
}
/*** BRCD-2634 Fix limited cycle(s) service (addon) align to the cycle. ***/
lastConfig = runOnce(lastConfig, 'BRCD-2634', function () {
    // Find all services that are limited by cycles and align to the cycle
    var _limited_aligned_cycles_services = db.services.distinct("name", {balance_period:{$exists:0}, "price.to":{$ne:"UNLIMITED"}});
    //printjson(_limited_aligned_cycles_services);
    // we are assuming that the script will be run until 2030 (services will be created until 2030), and will be expired until 2050 (limited cycles applied)
    db.subscribers.find({to:{$gt:ISODate()}, services:{$elemMatch:{name:{$in:_limited_aligned_cycles_services}, to:{$gt:ISODate("2050-01-01")}, creation_time:{$lt:ISODate("2030-01-01")}}}}).forEach(
                function(obj) {
    //                printjson(obj); // debug log
                    for (var subServiceObj in obj.services) {
    //                    print("handle " + subServiceObj + " " + obj.services[subServiceObj].name);
                        serviceObj = db.services.findOne({name:obj.services[subServiceObj].name, to:{$gt:ISODate()}});
                        cycleCount = serviceObj.price[serviceObj.price.length-1].to;
    //                    print("add months: " + cycleCount);
                        if (cycleCount != 'UNLIMITED' && !(serviceObj.hasOwnProperty('balance_period'))) {
//                            print("to before: " + obj.services[subServiceObj].to);
                            obj.services[subServiceObj].to = new Date(obj.services[subServiceObj].from);
                            obj.services[subServiceObj].to.setMonth(obj.services[subServiceObj].to.getMonth()+parseInt(cycleCount));
                            obj.services[subServiceObj].to.setDate(lastConfig.billrun.charging_day.v)
                            obj.services[subServiceObj].to.setHours(0,0,0,0);
    //                        print("to after: " + obj.services[subServiceObj].to);
                        }
                    }
    //                printjson(obj); // debug log
                    db.subscribers.save(obj);
                }
    );
});

//BRCD-2042 - charge.not_before migration script
db.bills.find({'charge.not_before':{$exists:0}, 'due_date':{$exists:1}}).forEach(
	function(obj) {
		if (typeof obj['charge'] === 'undefined') {
			obj['charge'] = {};
		}
		obj['charge']['not_before'] = obj['due_date'];
		db.bills.save(obj);
	}
)
db.billrun.find({'charge.not_before':{$exists:0}, 'due_date':{$exists:1}}).forEach(
	function(obj) {
		if (typeof obj['charge'] === 'undefined') {
			obj['charge'] = {};
		}
		obj['charge']['not_before'] = obj['due_date'];
		db.billrun.save(obj);
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
		db.bills.save(bill);
	}
});

// BRCD-2772 - add webhooks supports all audit collection field should be lowercase
db.audit.update({"collection" : "Login"}, {$set:{"collection":"login"}}, {"multi":1});

//BRCD-2855 Oauth support
lastConfig = runOnce(lastConfig, 'BRCD-2855', function () {
    // create collections
    db.createCollection("oauth_clients");
    db.createCollection("oauth_access_tokens");
    db.createCollection("oauth_authorization_codes");
    db.createCollection("oauth_refresh_tokens");
    db.createCollection("oauth_users");
    db.createCollection("oauth_scopes");
    db.createCollection("oauth_jwt");

    // create indexes
    db.oauth_clients.ensureIndex({'client_id': 1 });
    db.oauth_access_tokens.ensureIndex({'access_token': 1 });
    db.oauth_authorization_codes.ensureIndex({'authorization_code': 1 });
    db.oauth_refresh_tokens.ensureIndex({'refresh_token': 1 });
    db.oauth_users.ensureIndex({'username': 1 });
    db.oauth_scopes.ensureIndex({'oauth_scopes': 1 });
    
    var _obj;
    for (var secretKey in lastConfig.shared_secret) {
        secret = lastConfig.shared_secret[secretKey]
        if (secret.name == null || secret.name == '') {
            continue;
        }
        _obj = {
            "client_id": secret.name,
            "client_secret": secret.key,
            "grant_types": null,
            "scope": null,
            "user_id": null
        };
        db.oauth_clients.insert(_obj)
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
for (var i = 0; i < lastConfig.plugins.length; i++) {
	if (lastConfig.plugins[i]['name'] === "debtCollectionPlugin") {
		if (lastConfig.plugins[i]['configuration'] !== undefined){
			lastConfig.plugins[i]['configuration'] = {};
		}
		if (lastConfig.plugins[i]['configuration']['values'] !== undefined){
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

db.config.insert(lastConfig);
db.lines.ensureIndex({'sid' : 1, 'billrun' : 1, 'urt' : 1}, { unique: false , sparse: false, background: true });
