/* 
 * Version 5.8 Idempotent DB migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */


db.createCollection('prepaidgroups');
db.prepaidgroups.ensureIndex({ 'name':1, 'from': 1, 'to': 1 }, { unique: false, background: true });
db.prepaidgroups.ensureIndex({ 'name':1, 'to': 1 }, { unique: false, sparse: true, background: true });
db.prepaidgroups.ensureIndex({ 'description': 1}, { unique: false, background: true });

// BRCD-1077 Add new custom 'tariff_category' field to Products(Rates).
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
var fields = lastConfig['rates']['fields'];
var found = false;
for (var field_key in fields) {
	if (fields[field_key].field_name === "tariff_category") {
		found = true;
	}
}
if(!found) {
	fields.push({
		"system":true,
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
db.config.insert(lastConfig);
// BRCD-1077 update all products(Rates) tariff_category field.
db.rates.find().forEach(function (rate) {
	if (!rate.hasOwnProperty("tariff_category")) {
		rate.tariff_category = "retail";
	}
	db.rates.save(rate);
});