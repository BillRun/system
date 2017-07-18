/* 
 * Version 56 Idempotent DB migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */

db.queue.ensureIndex({'urt': 1 , 'type': 1}, { unique: false , sparse: true, background: true });

// BRCD-878: add rates custom fields
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

var additionalParams = db.rates.find({},{params:1});
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
	fields.push({"field_name": "params." + p[i], "multiple":true, "title":p[i], "display":true, "editable":true});
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

var systemFields = ['aid', 'firstname', 'lastname', 'email', 'country', 'address', 'zip_code', 'payment_gateway', 'personal_id','salutation'];
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
