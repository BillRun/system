/* 
 * Version 5.7 Idempotent DB migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */

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