#!/bin/bash

function RESET_MONGO_DB() {
    echo "Init mongo db"
    local billing_db=$1
    local billing_db_port=$2
    echo "BILL_RUN_CLIENT_ID: $BILL_RUN_CLIENT_ID"
    mongo $billing_db --port $billing_db_port --eval "db.dropDatabase()"
    echo "Import 1"
    mongo $billing_db --port $billing_db_port mongo/create.ini
    echo "Import 2"
    mongo $billing_db --port $billing_db_port mongo/sharding.ini
    echo "Import 3"
    mongoimport -d $billing_db --port $billing_db_port -c config mongo/base/config.export --batchSize 1
    local INSERT_STATMENT = `echo 'db.users.insert({ "username" : $BILLING_DB_USER_NAME, "password" :  $BILLING_DB_PASSWORD, "roles" : [ "read", "write", "admin" ], "from" : ISODate("2012-09-01T00:00:00Z"), "to" : ISODate("2168-03-25T09:43:10Z"), "creation_time" : ISODate("2012-09-01T00:00:00Z") })'`
    echo "Import 5 $INSERT_STATMENT"
    mongo $billing_db --port $billing_db_port --eval "$INSERT_STATMENT"
    echo "Import 6"
    mongo $billing_db --port $billing_db_port --eval 'var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];delete lastConfig["_id"];lastConfig.shared_secret =  [{"key" : @BILL_RUN_CLIENT_SECRET,"crc" : "834fde09","name" : @BILL_RUN_CLIENT_ID,"from" : ISODate("2020-12-28T00:00:00Z"),"to" : ISODate("2222-12-28T00:00:00Z")}];db.config.insert(lastConfig);'
    echo "Import 7"
    mongoimport -d $billing_db --port $billing_db_port -c taxes mongo/base/taxes.export
    echo "Done import"
     
}

function GET_ACCESS_TOKEN() {
    echo "getting the access token for the testing environment $1"
    echo "BILL_RUN_CLIENT_ID: $BILL_RUN_CLIENT_ID"
    local APP_DOMAIN=$1   
    local AUTH_RESPONSE=`curl -X POST -d "grant_type=client_credentials&client_id=$BILL_RUN_CLIENT_ID&client_secret=$BILL_RUN_CLIENT_SECRET" "$APP_DOMAIN/oauth2/token"`
    echo "$AUTH_RESPONSE"
    BILL_RUN_ACCESS_TOKEN=`jq '.access_token' <<<"$AUTH_RESPONSE"`
    
}
