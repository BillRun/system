// Use this script to switch to internal (DB) subscribers / customers mode

// main
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
lastConfig["subscribers"]["subscriber"]["type"]="db";
lastConfig["subscribers"]["account"]["type"]="db";
db.config.insert(lastConfig)