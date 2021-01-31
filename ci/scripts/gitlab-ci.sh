#!/bin/bash

function RESET_MONGO_DB() {
    echo "Init mongo db"
    local billing_db=$1
    local billing_db_port=$2
    mongo $billing_db --port $billing_db_port --eval "db.dropDatabase()"
    mongo $billing_db --port $billing_db_port mongo/create.ini
    mongo $billing_db --port $billing_db_port mongo/sharding.ini
    mongoimport -d $billing_db --port $billing_db_port -c config mongo/base/config.export --batchSize 1
    mongoimport -d $billing_db --port $billing_db_port -c users mongo/first_users.ini
    mongoimport -d $billing_db --port $billing_db_port -c users mongo/first_users.json
    mongoimport -d $billing_db --port $billing_db_port -c taxes mongo/base/taxes.export
}

function GET_ACCESS_TOKEN() {
    echo "getting the access token for the testing environment $1"
    local APP_DOMAIN=$1   
    local AUTH_RESPONSE = curl -X POST -d "grant_type=client_credentials&client_id=$BILL_RUN_CLIENT_ID&client_secret=$BILL_RUN_CLIENT_SECRET" "$APP_DOMAIN/oauth2/token"
    echo "$AUTH_RESPONSE"
    BILL_RUN_ACCESS_TOKEN=`jq '.access_token' $AUTH_RESPONSE`
    
}
