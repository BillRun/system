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
	for (var usaget in lastConfig['file_types'][i]['rate_calculators']) {
		if (typeof lastConfig['file_types'][i]['rate_calculators'][usaget][0][0] === 'undefined') {
			lastConfig['file_types'][i]['rate_calculators'][usaget] = [lastConfig['file_types'][i]['rate_calculators'][usaget]];
		}
	}
}
db.config.insert(lastConfig);
