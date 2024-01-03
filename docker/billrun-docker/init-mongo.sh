mongo billing_container /billrun/mongo/create.ini
mongoimport -d billing_container -c config /billrun/mongo/base/config.export --batchSize 1
mongoimport -d billing_container -c taxes /billrun/mongo/base/taxes.export --batchSize 1
FILE=/billrun/mongo/first_users.ini
if test -f "$FILE"; then
    mongoimport -d billing_container -c users $FILE
fi
FILE=/billrun/mongo/first_users.json
if test -f "$FILE"; then
    mongoimport -d billing_container -c users $FILE
fi 
mongo billing_container /billrun/mongo/migration/script.js
