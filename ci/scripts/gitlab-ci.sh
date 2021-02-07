#!/bin/bash -xv

function RESET_MONGO_DB() {
    echo "Init mongo db"
    local billing_db=$1
    local billing_db_port=$2
    
    mongo $billing_db --port $billing_db_port --eval "db.dropDatabase()"
    mongo $billing_db --port $billing_db_port mongo/create.ini
    mongo $billing_db --port $billing_db_port mongo/sharding.ini
    mongoimport -d $billing_db --port $billing_db_port -c config mongo/base/config.export --batchSize 1
    local DB_STATMENT=`echo "db.users.insert({ \"username\" : \"$BILLING_DB_USER_NAME\", \"password\" :  \"${BILLING_DB_PASSWORD//#/$}\", \"roles\" : [ \"read\", \"write\", \"admin\" ], \"from\" : ISODate(\"2012-09-01T00:00:00Z\"), \"to\" : ISODate(\"2168-03-25T09:43:10Z\"), \"creation_time\" : ISODate(\"2012-09-01T00:00:00Z\") })"`
    mongo $billing_db --port $billing_db_port --eval "$DB_STATMENT"
    DB_STATMENT=`echo "var lastConfig = db.config.find().sort({_id: -1}).limit(1).pretty()[0];delete lastConfig[\"_id\"];lastConfig.shared_secret =  [{\"key\" : \"$BILL_RUN_CLIENT_SECRET\",\"crc\" : \"834fde09\",\"name\" : \"$BILL_RUN_CLIENT_ID\",\"from\" : ISODate(\"2020-12-28T00:00:00Z\"),\"to\" : ISODate(\"2222-12-28T00:00:00Z\")}];db.config.insert(lastConfig);"`
    mongo $billing_db --port $billing_db_port --eval "$DB_STATMENT"    
    mongo $billing_db --port $billing_db_port --eval "db.oauth_clients.insert({ \"client_id\" : \"$BILL_RUN_CLIENT_ID\", \"client_secret\" : \"$BILL_RUN_CLIENT_SECRET\", \"grant_types\" : null, \"scope\" : null, \"user_id\" : null })"
    mongoimport -d $billing_db --port $billing_db_port -c taxes mongo/base/taxes.export
}

function GET_ACCESS_TOKEN() {
    echo "getting the access token for the testing environment $1"
    echo "BILL_RUN_CLIENT_ID: $BILL_RUN_CLIENT_ID"
    local APP_DOMAIN=$1   
    local AUTH_RESPONSE=`curl -X POST -d "grant_type=client_credentials&client_id=$BILL_RUN_CLIENT_ID&client_secret=$BILL_RUN_CLIENT_SECRET" "$APP_DOMAIN/oauth2/token"`
    echo "$AUTH_RESPONSE"
    BILL_RUN_ACCESS_TOKEN=`jq '.access_token' <<<"$AUTH_RESPONSE"`
    BILL_RUN_ACCESS_TOKEN=$(echo "$BILL_RUN_ACCESS_TOKEN" | cut -c2- | rev | cut -c2- | rev)
    
    
}

function RUN_TEST() {
    local APP_DOMAIN=$1
     GET_ACCESS_TOKEN "$APP_DOMAIN"     
     
     curl -H 'Accept:application/json' -H 'Authorization:Bearer '$BILL_RUN_ACCESS_TOKEN "$APP_DOMAIN/test/updaterowt" >> testresult.html
     FOUND_ERROR=`awk '/[0]?[1-9]+[0-9]?<strong> fails/' testresult.html`
     
     [ ! -z "$FOUND_ERROR" ] ; then echo "Found errors: $FOUND_ERROR" && exit 1 ; else echo "tests passed" ;
}
