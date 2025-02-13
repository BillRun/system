// Use this script to switch to set ecollection authentication

// script params (can be sent from the command line using --eval)
// var EXTERNAL_AUTHENTICATION // CRM authentication configuration (json)

// example comnand:
//     mongo DB_NAME -uUSER -pPASSWORD --eval 
//          'var EXTERNAL_AUTHENTICATION = {
//              "type": "oauth2",
//              "access_token_url": http://CRM/Api/access_token,
//              "data": {
//                  "grant_type": "client_credentials",
//                  "client_id": "abcd-1234-5678-9012",
//                  "client_secret": "abc1234",
//                  "scope": ""
//              },
//              "cache": true
//          };'
//     mongo/override_configuration/collection/set_collection_authentication.js

// main
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

if (typeof EXTERNAL_AUTHENTICATION !== 'undefined') {
    lastConfig['collection']['settings']['authentication'] = EXTERNAL_AUTHENTICATION;
} else {
    delete lastConfig['collection']['settings']['authentication'];
}

db.config.insert(lastConfig)