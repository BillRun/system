/* 
 * Version 5.8 Idempotent DB migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */


db.createCollection('prepaidgroups');
db.prepaidgroups.ensureIndex({ 'name':1, 'from': 1, 'to': 1 }, { unique: false, background: true });
db.prepaidgroups.ensureIndex({ 'name':1, 'to': 1 }, { unique: false, sparse: true, background: true });
db.prepaidgroups.ensureIndex({ 'description': 1}, { unique: false, background: true });


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
          width:  lastConfig['file_types'][i]['parser']['structure']['name']
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