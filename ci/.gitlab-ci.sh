#!/bin/bash

function RESET_MONGO_DB() {
    local billing_db=billing
    local billing_db_port=27617
    mongo $billing_db --port $billing_db_port --eval "db.dropDatabase()"
    mongo $billing_db --port $billing_db_port mongo/create.ini
    mongo $billing_db --port $billing_db_port mongo/sharding.ini
    mongoimport -d $billing_db --port $billing_db_port -c config mongo/base/config.export --batchSize 1
    mongoimport -d $billing_db --port $billing_db_port -c users mongo/first_users.ini
    mongoimport -d $billing_db --port $billing_db_port -c users mongo/first_users.json
    mongoimport -d $billing_db --port $billing_db_port -c taxes mongo/base/taxes.export
}

function GET_ACCESS_TOKEN() {
    local APP_DOMAIN=$1
    local BILL_RUN_CLIENT_ID=gitlab_ci
    local BILL_RUN_CLIENT_SECRET=87711abf64e2344ed4fbcd26b312035b
    local AUTH_RESPONSE = curl -X POST -d "grant_type=client_credentials&client_id=$BILL_RUN_CLIENT_ID&client_secret=$BILL_RUN_CLIENT_SECRET" "$APP_DOMAIN/oauth2/token"
    echo "$AUTH_RESPONSE"
    BILL_RUN_ACCESS_TOKEN=`jq '.access_token' $AUTH_RESPONSE`
    
}
