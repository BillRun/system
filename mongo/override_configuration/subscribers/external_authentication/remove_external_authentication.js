// Use this script is to remove external authentication

// example command (with authentication):
//     mongo/override_configuration/subscribers/remove_external_authentication.js

// main
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

delete lastConfig['subscribers']['external_authentication'];

db.config.insert(lastConfig)