/* 
 * General (idempotent) DB migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */

//var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
//delete lastConfig['_id'];
//db.config.insert(lastConfig);

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

db.config.insert(lastConfig);

//-------------------------------------------------------------------
// BRCD-1278 - backward support for new template
var lastConfig = db.config.find().sort({_id: -1}).limit(1).next();
delete lastConfig['_id'];
if(lastConfig.invoice_export) {
	lastConfig.invoice_export.header = "/application/views/invoices/header/header_tpl.html";
	lastConfig.invoice_export.footer = "/application/views/invoices/footer/footer_tpl.html";
}

db.config.insert(lastConfig);

//BRCD-1229 - Input processor re-enabled when not requested
for (var i in lastConfig['file_types']) {
	if (lastConfig['file_types'][i]['enabled'] === undefined) {
		lastConfig['file_types'][i]['enabled'] = true;
	}
}
db.config.insert(lastConfig);
