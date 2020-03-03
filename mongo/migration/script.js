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

db.config.insert(lastConfig);

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

// BRCD-2070 - GSD - getSubscriberDetails
if (!lastConfig.subscribers.subscriber.type) {
	lastConfig.subscribers.subscriber.type = 'db';
}
if (!lastConfig.subscribers.account.type) {
	lastConfig.subscribers.account.type = 'db';
}

db.config.insert(lastConfig);

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
