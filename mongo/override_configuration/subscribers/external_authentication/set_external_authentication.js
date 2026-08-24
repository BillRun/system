// Use this script is to apply external authentication

// script params (can be sent from the command line using --eval)
// var EXTERNAL_AUTHENTICATION // CRM authentication configuration (json)

// example command (with authentication):
//     mongo DB_NAME -uUSER -pPASSWORD --eval 
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
//     mongo/override_configuration/subscribers/set_external_authentication.js

// main
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];

lastConfig['subscribers']['external_authentication'] = EXTERNAL_AUTHENTICATION;

db.config.insert(lastConfig)