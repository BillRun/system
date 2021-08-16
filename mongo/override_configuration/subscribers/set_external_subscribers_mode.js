// Use this script to switch to external subscribers / customers mode

// script params (can be sent from the command line using --eval)
// var GSD_URL // Get Subscribers Details API endpoint
// var GAD_URL // Get Accounts Details API endpoint
// var GBA_URL // Get Billable Accounts API endpoint
// var ACCESS_TOKEN_URL // CRM authentication endpoint
// var ACCESS_TOKEN_CLIENT_ID // CRM client id for authentication
// var ACCESS_TOKEN_CLIENT_SECRET // CRM client secret for authentication

// main
var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];
delete lastConfig['_id'];
lastConfig['subscribers']['subscriber']['type'] = 'external';
lastConfig['subscribers']['subscriber']['external_url'] = GSD_URL;
lastConfig['subscribers']['account']['type'] = 'external';
lastConfig['subscribers']['account']['external_url'] = GAD_URL;
lastConfig['subscribers']['billable'] = {'url': GBA_URL};
lastConfig['subscribers']['external_authentication'] = {
    'type': 'oauth2',
    'access_token_url': ACCESS_TOKEN_URL,
    'data': {
        'grant_type': 'client_credentials',
        'client_id': ACCESS_TOKEN_CLIENT_ID,
        'client_secret': ACCESS_TOKEN_CLIENT_SECRET,
        'scope': '',
    },
    'cache': true
};

db.config.insert(lastConfig)