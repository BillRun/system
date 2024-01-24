#!/bin/bash

MONGOEXEC="mongosh";  			 	# The ongo client command to use inorder to run the removal scripts
DAYS_TO_REMOVE_PER_ITER=4;		 	# The number of  days  to  remove on each iteration of the  removal script run.
MONTHS_TO_KEEP=24;				 	# the Minimum amount of usage data to keep in the DB in months.
CUSTOMER_MONTHS_TO_KEEP=24;		 	# the Minimum amount of customer data to keep in the DB in months.
DB_CONFIG_PATH="../../conf/dev.ini" # The path to the DB configuration
CONFIG_PATH="../../conf/removal_config.sh" # THe path to the environemt and removal limits configuration

if [ -e $CONFIG_PATH ]; then
	echo "Loading configuration $CONFIG_PATH";
	. $CONFIG_PATH;
else
 declare -A USAGE_COLL;
 USAGE_COLL["lines"]=24;
 USAGE_COLL["queue"]=24;
 USAGE_COLL["archive"]=24;
 declare -A CUSTOMER_COLL;
 CUSTOMER_COLL["billrun"]=24;
 CUSTOMER_COLL["bills"]=24;
fi

if [ -n "$1" ]; then
	DB_CONFIG_PATH=$1;
fi

if [ -n "$2" ]; then
	DAYS_TO_REMOVE_PER_ITER=$2;
fi

if [ -n "$3" ]; then
	MONTHS_TO_KEEP=$3;
fi

if [ -n "$4" ]; then
	CUSTOMER_MONTHS_TO_KEEP=$4;
fi

print_usage() {
	echo "Usage as follow:";
	echo $0 "<DB Configuration Path> <Days to remove on each run> <Minimum usage months to keep in the DB>  <Minimum customer data months to keep in the DB>";
}

echo -e "Configuration\n Config Path : $DB_CONFIG_PATH,\n Global Minimum months to keep : $MONTHS_TO_KEEP\n Maximum days to remove on this iteration: $DAYS_TO_REMOVE_PER_ITER\n"

DB_USER="`grep -e '^db.user=' $DB_CONFIG_PATH | sed 's/db\.user\=//' | sed 's/[\\"]//g' `"
DB_PASSWORD="`grep -e '^db.password=' $DB_CONFIG_PATH | sed 's/db\.password\=//' | sed 's/[\\"]//g'`"
DB_HOST="`grep -e '^db.host=' $DB_CONFIG_PATH | sed 's/db\.host\=//' | sed 's/[\\"]//g'`"
DB_NAME="`grep -e '^db.name=' $DB_CONFIG_PATH | sed 's/db\.name\=//' | sed 's/[\\"]//g'`"

if [ -z $DB_HOST ] && [ -z $DB_NAME ]; then
	echo "Missing DB or hostname";
	print_usage;
	exit -1;
fi

echo "Starting to remove old  lines/archived cdrs"
for coll in "${!USAGE_COLL[@]}"; do
	COLL_MONTHS_TO_KEEP=$(( ${USAGE_COLL[$coll]} > $MONTHS_TO_KEEP ? ${USAGE_COLL[$coll]} : $MONTHS_TO_KEEP ));
	echo "var trgtColl='$coll'; var daysToRmove = $DAYS_TO_REMOVE_PER_ITER; var monthsToKeep = $COLL_MONTHS_TO_KEEP;" > /tmp/data_removal_config.js;

	if [ -z "$DB_USER" ] && [ -z $DB_PASSWORD ]; then
		$MONGOEXEC --host $DB_HOST $DB_NAME  /tmp/data_removal_config.js ./remove_old_cdr_data.js
	else
		$MONGOEXEC --host $DB_HOST --authenticationDatabase=admin -u$DB_USER -p$DB_PASSWORD $DB_NAME  /tmp/data_removal_config.js ./remove_old_cdr_data.js
	fi
done

echo "Starting to remove old  invoices/events"
for coll in "${!CUSTOMER_COLL[@]}"; do
	COLL_MONTHS_TO_KEEP=$(( ${CUSTOMER_COLL[$coll]} >  $CUSTOMER_MONTHS_TO_KEEP ? ${CUSTOMER_COLL[$coll]} : $CUSTOMER_MONTHS_TO_KEEP ));
	echo "var trgtColl='$coll'; var daysToRmove = $DAYS_TO_REMOVE_PER_ITER; var monthsToKeep = $COLL_MONTHS_TO_KEEP;" > /tmp/data_removal_config.js;

	if [ -z "$DB_USER" ] && [ -z $DB_PASSWORD ]; then
		$MONGOEXEC --host $DB_HOST $DB_NAME  /tmp/data_removal_config.js ./creation_time_based_data_removal.js
	else
		$MONGOEXEC --host $DB_HOST --authenticationDatabase=admin -u$DB_USER -p$DB_PASSWORD $DB_NAME  /tmp/data_removal_config.js ./creation_time_based_data_removal.js
	fi
done
