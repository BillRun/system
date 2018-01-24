/* 
 * Version 5.7 Idempotent DB migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */


// BRCD-1002- Add weight property type to units of measure
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
if (lastConfig.property_types && lastConfig.property_types.filter(element => element.type === 'weight').length == 0) {
	delete lastConfig['_id'];
	lastConfig.property_types.push(
			{
				"system": true,
				"type": "weight",
				"uom": [{"unit": 1, "name": "mg", "label": "mg"}, {"unit": 1000, "name": "g", "label": "g"}, {"unit": 1000000, "name": "kg", "label": "kg"}, {"unit": 1000000000, "name": "ton", "label": "ton"}],
				"invoice_uom": "kg"
			});
	db.config.insert(lastConfig);
}

// BRCD-988 - rating priorities
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
for (var i in lastConfig['file_types']) {
	for (var rateCat in lastConfig['file_types'][i]['rate_calculators']) {
		for (var usaget in lastConfig['file_types'][i]['rate_calculators'][rateCat]) {
			if (typeof lastConfig['file_types'][i]['rate_calculators'][rateCat][usaget][0][0] === 'undefined') {
				lastConfig['file_types'][i]['rate_calculators'][rateCat][usaget] = [lastConfig['file_types'][i]['rate_calculators'][rateCat][usaget]];
			}
		}
	}
}
db.config.insert(lastConfig);

// BRCD-865 - extend postpaid balances period
db.balances.update({"period":{$exists:0}},{"$set":{"period":"default","start_period":"default"}}, {multi:1});


// BRCD-811 - Save process_time field as a date instead of a string
db.lines.find({"process_time":{$exists:1}}).forEach( function(line) { 
	if (typeof line.process_time == 'string'){
		db.lines.update({_id:line._id},{$set:{process_time:new ISODate(line.process_time)}})
	}
});
db.log.find({"process_time":{$exists:1}}).forEach( function(line) { 
	if (typeof line.process_time == 'string'){
		db.log.update({_id:line._id},{$set:{process_time:new ISODate(line.process_time)}})
	}
});

// BRCD-986 - fix empty "computed" field
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
for (var i in lastConfig['file_types']) {
	for (var usaget in lastConfig['file_types'][i]['rate_calculators']) {
		for (var priority in lastConfig['file_types'][i]['rate_calculators'][usaget]) {
			for (var j in lastConfig['file_types'][i]['rate_calculators'][usaget][priority]) {
				if (lastConfig['file_types'][i]['rate_calculators'][usaget][priority][j].computed && lastConfig['file_types'][i]['rate_calculators'][usaget][priority][j].computed.length === 0) {
					delete lastConfig['file_types'][i]['rate_calculators'][usaget][priority][j].computed;
				}
			}
		}
	}
}
db.config.insert(lastConfig);

// BRCD-865 - overlapping extend balances services
db.balances.update({"priority":{$exists:0}},{"$set":{"priority":0}}, {multi:1});
var existingCollections = db.getCollectionNames();
if (existingCollections.indexOf('prepaidgroups') === -1) {
	db.createCollection('prepaidgroups');
	db.prepaidgroups.ensureIndex({ 'name':1, 'from': 1, 'to': 1 }, { unique: false, background: true });
	db.prepaidgroups.ensureIndex({ 'name':1, 'to': 1 }, { unique: false, sparse: true, background: true });
	db.prepaidgroups.ensureIndex({ 'description': 1}, { unique: false, background: true });
	}


// BRCD-1143 - Input Processors fields new strucrure
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
for (var i in lastConfig['file_types']) {
	if(["fixed"].includes(lastConfig['file_types'][i]['parser']['type'])){
		if (!Array.isArray(lastConfig['file_types'][i]['parser']['structure'])) {
			var newStructure = [];
			for (var name in lastConfig['file_types'][i]['parser']['structure']) {
				newStructure.push({
					name: name,
          width:  lastConfig['file_types'][i]['parser']['structure'][name]
				});
			}
			lastConfig['file_types'][i]['parser']['structure'] = newStructure;
		}
	} else if(typeof lastConfig['file_types'][i]['parser']['structure'][0] === 'string'){
			var newStructure = [];
			for (var j in lastConfig['file_types'][i]['parser']['structure']) {
				newStructure.push({
					name: lastConfig['file_types'][i]['parser']['structure'][j],
				});
			}
			lastConfig['file_types'][i]['parser']['structure'] = newStructure;
	}
}
db.config.insert(lastConfig);

// BRCD-1114: change customer mapping structure
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
for (var i in lastConfig['file_types']) {
	var mappings = {};
	if (typeof lastConfig['file_types'][i]['customer_identification_fields'] === 'undefined') continue;
	var firstKey = Object.keys(lastConfig['file_types'][i]['customer_identification_fields'])[0];
	if (firstKey != 0) {
		continue;
	}
	for (var priority in lastConfig['file_types'][i]['customer_identification_fields']) {
		if (typeof lastConfig['file_types'][i]['customer_identification_fields'][priority]['conditions'] === 'undefined') continue;
		var regex = lastConfig['file_types'][i]['customer_identification_fields'][priority]['conditions'][0]['regex'];
		var data = lastConfig['file_types'][i]['customer_identification_fields'][priority];
		delete data['conditions'];
		var usaget = regex.substring(2, regex.length - 2);;
		if (!mappings[usaget]) {
			mappings[usaget] = [];
		}
		mappings[usaget].push(data);
	}
	lastConfig['file_types'][i]['customer_identification_fields'] = mappings;
}
db.config.insert(lastConfig);

// BRCD-865 - overlapping extend balances services
db.balances.update({"priority":{$exists:0}},{"$set":{"priority":0}}, {multi:1});

// BRCD-908 - Rebalance field changes
db.lines.find({"rebalance":{$exists:1}}).forEach( function(line) { 
	if (!Array.isArray(line.rebalance)){
		db.lines.update({_id:line._id},{$set:{rebalance:[line.rebalance]}})
	}
});

// BRCD-1044: separate volume field to different usage types
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
for (var i in lastConfig['file_types']) {
	if (typeof lastConfig['file_types'][i]['processor'] === 'undefined') continue;
	var volumeFields = lastConfig['file_types'][i]['processor']['volume_field'];
	if (typeof volumeFields  === 'undefined') {
		continue;
	}
	if (typeof lastConfig['file_types'][i]['processor']['usaget_mapping'] !== 'undefined') {
		for (var j in lastConfig['file_types'][i]['processor']['usaget_mapping']) {
			lastConfig['file_types'][i]['processor']['usaget_mapping'][j]['volume_type'] = 'field';
			lastConfig['file_types'][i]['processor']['usaget_mapping'][j]['volume_src'] = volumeFields;
		}
	} else {
		lastConfig['file_types'][i]['processor']['default_volume_type'] = 'field';
		lastConfig['file_types'][i]['processor']['default_volume_src'] = volumeFields;
	}
	delete lastConfig['file_types'][i]['processor']['volume_field'];
}
db.config.insert(lastConfig);

// BRCD-1164 - Don't set balance_period field when it's irrelevant
db.services.update({balance_period:"default"},{$unset:{balance_period:1}},{multi:1})

// BRCD-1140 - update plan includes new structure 
var usageTypes = [];
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
var usageTypesStruct = lastConfig["usage_types"];
for (var usageType in usageTypesStruct) {
if (usageTypesStruct[usageType] == null || usageTypesStruct[usageType]["usage_type"] == "") continue;
	usageTypes.push(usageTypesStruct[usageType]["usage_type"]);
}
db.plans.find({include:{$exists:1}, 'include.groups':{$ne:[]}}).forEach(
	function (obj) {
			var groups = obj.include.groups;
			for(var group in groups) {
				var oldStrcuture = groups[group];
				if (typeof oldStrcuture.usage_types !== 'undefined') continue;
				for (var field in oldStrcuture) {
					if (usageTypes.indexOf(field) == -1) continue;
					oldStrcuture["value"] = parseFloat(oldStrcuture[field]);
					var key = field;
					var newStructure = {};
					newStructure[key] = {"unit": oldStrcuture["unit"]};
					oldStrcuture["usage_types"] = newStructure;
					delete oldStrcuture["unit"];
					delete oldStrcuture[key];
				}
			}
	
		db.plans.save(obj);
	}
);

// BRCD-1140 - update service includes new structure 
var usageTypes = [];
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
var usageTypesStruct = lastConfig["usage_types"];
for (var usageType in usageTypesStruct) {
if (usageTypesStruct[usageType] == null || usageTypesStruct[usageType]["usage_type"] == "") continue;
	usageTypes.push(usageTypesStruct[usageType]["usage_type"]);
}
db.services.find({include:{$exists:1}, 'include.groups':{$ne:[]}}).forEach(
	function (obj) {
			var groups = obj.include.groups;
			for(var group in groups) {
				var oldStrcuture = groups[group];
				if (typeof oldStrcuture.usage_types !== 'undefined') continue;
				for (var field in oldStrcuture) {
					if (usageTypes.indexOf(field) == -1) continue;
					oldStrcuture["value"] = parseFloat(oldStrcuture[field]);
					var key = field;
					var newStructure = {};
					newStructure[key] = {"unit": oldStrcuture["unit"]};
					oldStrcuture["usage_types"] = newStructure;
					delete oldStrcuture["unit"];
					delete oldStrcuture[key];
				}
			}
		
		db.services.save(obj);
	}
);

// BRCD-1168: remove invalid "used_usagev_field" value of [undefined]
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
for (var i in lastConfig['file_types']) {
	if (typeof lastConfig['file_types'][i]['realtime'] === 'undefined' ||
			typeof lastConfig['file_types'][i]['realtime']['used_usagev_field'] === 'undefined') {
		continue;
	}
	if (Array.isArray(lastConfig['file_types'][i]['realtime']['used_usagev_field']) &&
		typeof lastConfig['file_types'][i]['realtime']['used_usagev_field'][0] === 'undefined') {
		lastConfig['file_types'][i]['realtime']['used_usagev_field'] = [];
	}
}
db.config.insert(lastConfig);

// BRCD-1168: fix field source which is not an array
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
for (var i in lastConfig['file_types']) {
	if (typeof lastConfig['file_types'][i]['processor'] === 'undefined') continue;
	if (typeof lastConfig['file_types'][i]['processor']['usaget_mapping'] !== 'undefined') {
		for (var j in lastConfig['file_types'][i]['processor']['usaget_mapping']) {
			if (lastConfig['file_types'][i]['processor']['usaget_mapping'][j]['volume_type'] === 'field' &&
					!Array.isArray(lastConfig['file_types'][i]['processor']['usaget_mapping'][j]['volume_src'])) {
				var val = (typeof lastConfig['file_types'][i]['processor']['usaget_mapping'][j]['volume_src'] === 'undefined'
									? []
									: [lastConfig['file_types'][i]['processor']['usaget_mapping'][j]['volume_src']]);
				lastConfig['file_types'][i]['processor']['usaget_mapping'][j]['volume_src'] = val;
			}
		}
	} else {
		if (lastConfig['file_types'][i]['processor']['default_volume_type'] === 'field' &&
				!Array.isArray(lastConfig['file_types'][i]['processor']['default_volume_src'])) {
			var val = (typeof lastConfig['file_types'][i]['processor']['default_volume_src'] === 'undefined'
									? []
									: [lastConfig['file_types'][i]['processor']['default_volume_src']]);
			lastConfig['file_types'][i]['processor']['default_volume_src'] = val;
		}
	}
}
db.config.insert(lastConfig);

// BRCD-1152: Add service activation date to each cdr generated on the billing cycle
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
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
db.config.insert(lastConfig);

db.services.ensureIndex({'name':1, 'from': 1, 'to': 1}, { unique: true, background: true });


// BRCD-1189: Prepriced field per usage type
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
for (var i in lastConfig['file_types']) {
	if (typeof lastConfig['file_types'][i]['pricing'] !== 'undefined' || typeof lastConfig['file_types'][i]['processor'] === 'undefined') {
		continue;
	}
	lastConfig['file_types'][i]['pricing'] = {};
	if (typeof lastConfig['file_types'][i]['processor']['default_usaget'] !== 'undefined') {
		var defaultUsage = lastConfig['file_types'][i]['processor']['default_usaget'];
		if (typeof lastConfig['file_types'][i]['processor']['aprice_field'] === 'undefined') {
			lastConfig['file_types'][i]['pricing'][defaultUsage] = [];
			continue;
		}
		lastConfig['file_types'][i]['pricing'][defaultUsage] = {'aprice_field': lastConfig['file_types'][i]['processor']['aprice_field']};
		if (typeof lastConfig['file_types'][i]['processor']['aprice_mult'] !== 'undefined') {
			lastConfig['file_types'][i]['pricing'][defaultUsage] = {'aprice_field': lastConfig['file_types'][i]['processor']['aprice_field'], 'aprice_mult': lastConfig['file_types'][i]['processor']['aprice_mult']};
		}
	} else {
		for (var j in lastConfig['file_types'][i]['processor']['usaget_mapping']) {
			var usageType = lastConfig['file_types'][i]['processor']['usaget_mapping'][j]['usaget'];
			if (typeof lastConfig['file_types'][i]['processor']['aprice_field'] === 'undefined') {
				lastConfig['file_types'][i]['pricing'][usageType] = [];
				continue;
			}
			lastConfig['file_types'][i]['pricing'][usageType] = {'aprice_field': lastConfig['file_types'][i]['processor']['aprice_field']};
			if (typeof lastConfig['file_types'][i]['processor']['aprice_mult'] !== 'undefined') {
				lastConfig['file_types'][i]['pricing'][usageType] = {'aprice_field': lastConfig['file_types'][i]['processor']['aprice_field'], 'aprice_mult': lastConfig['file_types'][i]['processor']['aprice_mult']};
			}
		}
	}
	delete(lastConfig['file_types'][i]['processor']['aprice_field']);
	delete(lastConfig['file_types'][i]['processor']['aprice_mult']);
}

db.config.insert(lastConfig);