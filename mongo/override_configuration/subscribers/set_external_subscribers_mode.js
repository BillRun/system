// Use this script to switch to external subscribers / customers mode

// script params (can be sent from the command line using --eval)
// var GSD_URL // Get Subscribers Details API endpoint
// var GAD_URL // Get Accounts Details API endpoint
// var GBA_URL // Get Billable Accounts API endpoint
// var EXTERNAL_AUTHENTICATION // CRM authentication configuration (json)

// example comnand (without authentication):
//     mongo DB_NAME -uUSER -pPASSWORD --eval 
//         'var GSD_URL="http://CRM/Api/V8/custom/external/gsd";
//          var GAD_URL="http://CRM/Api/V8/custom/external/gad";
//          var GBA_URL="http://CRM/Api/V8/custom/external/gba";'
//     mongo/override_configuration/subscribers/set_external_subscribers_mode.js

// example comnand (with authentication):
//     mongo DB_NAME -uUSER -pPASSWORD --eval 
//         'var GSD_URL="http://CRM/Api/V8/custom/external/gsd";
//          var GAD_URL="http://CRM/Api/V8/custom/external/gad";
//          var GBA_URL="http://CRM/Api/V8/custom/external/gba";
//          var EXTERNAL_AUTHENTICATION = {
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
//     mongo/override_configuration/subscribers/set_external_subscribers_mode.js

// main
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
lastConfig['subscribers']['subscriber']['type'] = 'external';
lastConfig['subscribers']['subscriber']['external_url'] = GSD_URL;
lastConfig['subscribers']['account']['type'] = 'external';
lastConfig['subscribers']['account']['external_url'] = GAD_URL;
lastConfig['subscribers']['billable'] = {'url': GBA_URL};

if (typeof EXTERNAL_AUTHENTICATION !== 'undefined') {
    lastConfig['subscribers']['external_authentication'] = EXTERNAL_AUTHENTICATION;
} else {
    delete lastConfig['subscribers']['external_authentication'];
}

db.config.insert(lastConfig)