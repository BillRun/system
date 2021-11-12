mongo billing_cloud /billrun/mongo/create.ini

mongoimport -d billing_cloud -c config /billrun/mongo/base/config.export --batchSize 1

FILE=/billrun/mongo/first_users.ini
if test -f "$FILE"; then
    mongoimport -d billing_cloud -c users $FILE
fi

FILE=/billrun/mongo/first_users.json
if test -f "$FILE"; then
    mongoimport -d billing_cloud -c users $FILE
fi 

mongoimport -d billing_cloud -c taxes /billrun/mongo/base/taxes.export --batchSize 1

mongo billing_cloud /billrun/mongo/migration/script.js
