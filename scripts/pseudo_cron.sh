#!/bin/bash

###  script arguments as follow:
###  1) Path to the billrun installation directory
###  2) Environment (dev / prod etc.)
###  3) Number of servers
###  4) Server index (out of number of servers)

if [ $1 ]; then
	cd $1;
	export APPLICATION_MULTITENANT=1;
	INDEX=public/index.php

else
	echo "please supply the path to the billrun installation directory"
	exit 1
fi

if [ -z "$APPLICATION_MULTITENANT" ] || [ "$APPLICATION_MULTITENANT" != 1 ]; then
    echo "Not running in multi-tenant mode. Exiting."
    exit 1
fi  

if [ $2 ]; then
	ENV=$2
else
	echo "please supply an environment"
	exit 2
fi

if [ $3 ]; then
	SERVERS=$3
else
	echo "please supply number of servers"
	exit 3
fi

if [ $4 ]; then
	if [ "$4" -lt "$SERVERS" ]; then
		SERVER_INDEX=$4
	else
		echo "server index must be less than number of servers"
		exit 4
	fi
else
	echo "please supply server index"
	exit 4
fi

function SET_SERVER_NUM {
	MD5=`echo "$TENANT" | md5sum | cut -f1 -d" "`
	I=`expr index "0123456789abcdef" ${MD5:0:1}`
	SERVER_NUM=`expr $I % $SERVERS`
}

CLIENTS=$1/conf/tenants/*.ini
LOCKS=$1/scripts/locks/
CYCLE_SCRIPT=$1/scripts/pseudo_cron_cycle.sh

# Go through the files
for f in $CLIENTS
do
	if [ "$CLIENTS" != "$f" ]; then # non-empty folder check
		TEMP=$(basename $f)
		TENANT=${TEMP%.*}
		if [ "$TENANT" == "base" ]; then
			continue
		fi

		SET_SERVER_NUM
		if [ "$SERVER_NUM" == "$SERVER_INDEX" ]; then
			echo "Running calculators for "$TENANT" file"
			flock -n $LOCKS"/pseudo_cron_"$TENANT".lock" -c "sh $CYCLE_SCRIPT $TENANT $ENV $INDEX"
		fi
	else
		echo "No tenants found"
	fi	
done