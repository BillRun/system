/* 
 * Idempotent migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */

// BRCD-576 (Deactivate / reactivate payment gateway)
db.subscribers.find({type: 'account', payment_gateway: {$exists: 1}, 'payment_gateway.active': {$exists: 0}}).forEach(
		function (obj) {
			var ele = {active: obj.payment_gateway};
			obj.payment_gateway = ele;
			db.subscribers.save(obj);
		}
);

// BRCD-594 (Override product price in plan / plan includes products - migration script)
//  run over rates
//  fill gropus dictionary
//  move override prices from rate to plan
var gruopD = {};
db.rates.find({'to': {$gt: new Date()}}).forEach(function (product) {
	var rates = product.rates;
	for (var type in rates) {
		for (var planKey in rates[type]) {
			if (planKey == "groups") {
				for (var gruop in rates[type][planKey]) {
					var groupName = rates[type][planKey][gruop];
					if (!gruopD.hasOwnProperty(groupName)) {
						gruopD[groupName] = [];
					}
					var alreadyExist = false;
					for (var i = 0; i < gruopD[groupName].length; i++) {
						if (gruopD[groupName][i] == product.key)
							alreadyExist = true;
					}
					if (!alreadyExist)
						gruopD[groupName].push(product.key);
				}
				delete rates[type][planKey];
			} else if (planKey != "BASE") {
				planDoc = db.plans.findOne({'name': planKey, 'to': {$gt: new Date()}});
				if (planDoc != null) {
					if (!planDoc.hasOwnProperty('rates')) {
						planDoc['rates'] = {};
					}
					if (!planDoc['rates'].hasOwnProperty(product.key)) {
						planDoc['rates'][product.key] = {};
					}
					planDoc['rates'][product.key][type] = rates[type][planKey];
				}
				db.plans.save(planDoc);
				delete rates[type][planKey];
			}
		}
	}
	product.rates = rates;
	db.rates.save(product);
});
//  run over plans
//  add rates array to each group
db.plans.find({'to': {$gt: new Date()}}).forEach(function (plan) {
	if (plan.hasOwnProperty("include")) {
		var groupsInPlan = plan.include.groups;
		for (var group in groupsInPlan) {
			if (gruopD.hasOwnProperty(group)) {
				groupsInPlan[group]["rates"] = gruopD[group];
			}
		}
		plan.include.groups = groupsInPlan;
		db.plans.save(plan);
	}
});
//  run over services
//  add rates array to each group
db.services.find({'to': {$gt: new Date()}}).forEach(function (service) {
	if (service.hasOwnProperty("include")) {
		var groupsInService = service.include.groups;
		for (var group in groupsInService) {
			if (gruopD.hasOwnProperty(group)) {
				groupsInService[group]["rates"] = gruopD[group];
			}
		}
		service.include.groups = groupsInService;
		db.services.save(service);
	}
});

// Add zip code account system field if it doesn't yet exist
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
if ((typeof lastConfig) !== "undefined") {
	delete lastConfig['_id'];
	var found_zip_code = false;
	lastConfig.subscribers.account.fields.forEach(function (field) {
		if (field.field_name == "zip_code") {
			found_zip_code = true;
			field.unique = false;
			field.generated = false;
			field.editable = true;
			field.mandatory = true;
			field.system = true;
			field.show_in_list = false;
			field.display = true;
		}
	})
	if (!found_zip_code) {
		lastConfig.subscribers.account.fields.push({
			"field_name": "zip_code",
			"title": "Zip code",
			"generated": false,
			"unique": false,
			"editable": true,
			"mandatory": true,
			"system": true,
			"show_in_list": false,
			"display": true
		});
	}

	var found_tax_field = false;
	lastConfig.rates.fields.forEach(function (field) {
		if (field.field_name == "tax") {
			found_tax_field = true;
		}
	})
	if (!found_tax_field) {
		lastConfig.rates.fields.push({
			"field_name": "tax",
		});
	}

	db.config.insert(lastConfig);
}

// BRCD-614
var serviceDic = {}
db.subscribers.find({type: "subscriber", services: {$ne: [], $exists: 1}}).forEach(function (obj) {
	var services = [];
	for (var i in obj.services) {
		var srvname = obj.services[i].name ? obj.services[i].name : obj.services[i];
		var dicKey = srvname + obj.sid;
		if (!serviceDic[dicKey]) {
			serviceDic[dicKey] = {name: srvname, from: obj.from, to: obj.to};
		} else {
			if (obj.from < serviceDic[dicKey].from) {
				serviceDic[dicKey].from = obj.from;
			}
			if (obj.to > serviceDic[dicKey].to) {
				serviceDic[dicKey].to = obj.to;
			}
		}

	}
});
db.subscribers.find({type: "subscriber", services: {$ne: [], $exists: 1}}).forEach(function (obj) {
	var services = [];
	for (var i in obj.services) {
		var srvname = obj.services[i].name ? obj.services[i].name : obj.services[i];
		var dicKey = srvname + obj.sid;
		services.push({name: srvname, from: serviceDic[dicKey].from, to: serviceDic[dicKey].to});
	}
	obj.services = services;
	db.subscribers.save(obj);
});


db.balances.dropIndex('sid_1_from_1_to_1_priority_1');
db.balances.ensureIndex({aid: 1, sid: 1, from: 1, to: 1, priority: 1}, {unique: true, background: true});

// BRCD-646
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
if (!lastConfig.taxation || !lastConfig.taxation.tax_type) {
	delete lastConfig['_id'];
	var pricingVat = lastConfig.pricing.vat;
	delete lastConfig['pricing']['vat'];
	lastConfig.taxation = {};
	lastConfig.taxation.vat = pricingVat;
	lastConfig.taxation.tax_type = "vat";
	db.config.insert(lastConfig);
}

// BRCD-749
db.rebalance_queue.dropIndex("sid_1");
db.rebalance_queue.ensureIndex({"sid": 1, "billrun_key": 1}, {unique: true, "background": true})

// BRCD-731
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
if (!lastConfig.registration_date) {
	var firstConfig = db.config.find().sort({_id: 1}).limit(1).pretty()[0];
	var registrationDate = firstConfig._id.getTimestamp();
	delete lastConfig['_id'];
	lastConfig.registration_date = registrationDate;
	db.config.insert(lastConfig);
}

//BRCD-776
db.lines.find({final_charge:{$exists:0},aprice:{$exists:1}}).forEach(function(line){
	if (typeof line.tax_data !== 'undefined') {
		line['final_charge']=line['aprice']+line['tax_data']['total_amount'];
	} else {
		line['final_charge']=line['aprice'];	
	}
	db.lines.save(line);
});

// Set "Account ID" as the title for "aid" field
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
if ((typeof lastConfig) !== "undefined") {
	delete lastConfig['_id'];
	var found_aid = false;
	lastConfig.subscribers.account.fields.forEach(function (field) {
		if (field.field_name == "aid") {
			found_aid = true;
			field.title = "Account ID";
		}
	})
	if (!found_aid) {
		lastConfig.subscribers.account.fields.push({
					"field_name" : "aid",
					"generated" : true,
					"unique" : true,
					"editable" : false
				});
	}
	db.config.insert(lastConfig);
}

// subscribers / discounts indexes fixes
db.subscribers.dropIndex('sid_1_from_1_to_1');
db.subscribers.ensureIndex({'sid': 1 , 'from' : 1, 'aid' : 1}, { unique: true, sparse: true, background: true });
db.discounts.ensureIndex({'key':1, 'from': 1}, { unique: true, background: true });
db.discounts.ensureIndex({'from': 1, 'to': 1 }, { unique: false , sparse: true, background: true });
db.discounts.ensureIndex({'to': 1 }, { unique: false , sparse: true, background: true });

// Update shared secret structure
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
if (typeof lastConfig.shared_secret.key != 'undefined') {
	lastConfig.shared_secret.name = 'key1';
	lastConfig.shared_secret.from = lastConfig.registration_date;
	lastConfig.shared_secret.to = new Date('2117/09/02');
	var ele = [];
	ele.push(lastConfig.shared_secret);
	lastConfig.shared_secret = ele;
	db.config.insert(lastConfig);
}

// Update realtime response fields
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
var fileTypes = lastConfig['file_types'];
for (var i in fileTypes) {
	if (fileTypes[i].response && fileTypes[i].response.fields) {
		fileTypes[i].response.fields = [
			{
				"response_field_name": "requestType",
				"row_field_name": "request_type"
			},
			{
				"response_field_name": "sessionId",
				"row_field_name": "session_id"
			},
			{
				"response_field_name": "returnCode",
				"row_field_name": "granted_return_code"
			},
			{
				"response_field_name": "stamp",
				"row_field_name": "stamp"
			},
			{
				"response_field_name": "sid",
				"row_field_name": "sid"
			},
			{
				"response_field_name": "grantedVolume",
				"row_field_name": "usagev"
			},
			{
				"response_field_name": "pretend",
				"row_field_name": "billrun_pretend"
			}
		];
	}
}


lastConfig.file_types = fileTypes;
db.config.insert(lastConfig);

// BRCD-832 rename entity name from lines to usage
db.reports.find({"entity": "lines"}).forEach(function (obj) {
	obj.entity = "usage";
	db.reports.save(obj);
});

// BRCD-368 Importer - chenge account and subscriber fieds settings
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
if ((typeof lastConfig) !== "undefined") {
	delete lastConfig['_id'];
	// set subscriber plan_activation display false
	var plan_activation_index = lastConfig.subscribers.subscriber.fields.findIndex(function (field) {
		return field.field_name == "plan_activation" && typeof field.editable === "undefined"
	});
	if(plan_activation_index !== -1) {
		lastConfig.subscribers.subscriber.fields[plan_activation_index].editable = false;
	}

	db.config.insert(lastConfig);
}

// BRCD-865 update balances collection with compatibility to extended balance period
db.balances.update({},{"$set":{"period":"default","start_period":"default"}}, {multi:1})
