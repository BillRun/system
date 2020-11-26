// Use this script to switch to external subscribers / customers mode

// script params
var GSD_URL = "http://crm/api/gsd"; // Modify with the desired GSD ("Get Subscribers Details") API endpoint
var GAD_URL = "http://crm/api/gad";  // Modify with the desired GAD ("Get Accounts Details") API endpoint
var GBA_URL = "http://crm/api/gba";  // Modify with the desired GBA ("Get Billable Accounts") API endpoint

// main
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
lastConfig["subscribers"]["subscriber"]["type"] = "external";
lastConfig["subscribers"]["subscriber"]["external_url"] = GSD_URL;
lastConfig["subscribers"]["account"]["type"] = "external";
lastConfig["subscribers"]["account"]["external_url"] = GAD_URL;
lastConfig["subscribers"]["billable"] = {"url": GBA_URL};
db.config.insert(lastConfig)