#!/bin/bash

MONGOEXEC="mongosh";
DAYS_TO_REMOVE_PER_ITER=4;
MONTHS_TO_KEEP=6;
CONFIG_PATH="../../conf/dev.ini"

if [ -n "$1" ]; then
	CONFIG_PATH=$1;
fi

if [ -n "$2" ]; then
	MONTHS_TO_KEEP=$2;
fi

if [ -n "$3" ]; then
	DAYS_TO_REMOVE_PER_ITER=$3;
fi

echo -e "Configuration\n Config Path : $CONFIG_PATH,\n Minimum months to keep : $MONTHS_TO_KEEP\n Maximum days to remove on this iteration: $DAYS_TO_REMOVE_PER_ITER\n"

DB_USER="`grep -e '^db.user=' $CONFIG_PATH | sed 's/db\.user\=//' | sed 's/[\\"]//g' `"
DB_PASSWORD="`grep -e '^db.password=' $CONFIG_PATH | sed 's/db\.password\=//' | sed 's/[\\"]//g'`"
DB_HOST="`grep -e '^db.host=' $CONFIG_PATH | sed 's/db\.host\=//' | sed 's/[\\"]//g'`"
DB_NAME="`grep -e '^db.name=' $CONFIG_PATH | sed 's/db\.name\=//' | sed 's/[\\"]//g'`"

echo " Starting to remove old  lines/archived cdrs"
for coll in lines archive ; do
	echo "var trgtColl='$coll'; var daysToRmove = $DAYS_TO_REMOVE_PER_ITER; var monthsToKeep = $MONTHS_TO_KEEP;" > /tmp/data_removal_config.js
 	echo $MONGOEXEC --host $DB_HOST --authenticationDatabase=admin -u$DB_USER -p$DB_PASSWORD $DB_NAME  /tmp/data_removal_config.js ./remove_old_cdr_data.js
done

echo " Starting to remove old  invoices/events"
for coll in billrun bills events ; do
	echo "var trgtColl='$coll'; var daysToRmove = $DAYS_TO_REMOVE_PER_ITER; var monthsToKeep = $MONTHS_TO_KEEP;" > /tmp/data_removal_config.js
 	echo $MONGOEXEC --host $DB_HOST --authenticationDatabase=admin -u$DB_USER -p$DB_PASSWORD $DB_NAME  /tmp/data_removal_config.js ./creation_time_based_data_removal.js
done
