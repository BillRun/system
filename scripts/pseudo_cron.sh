#!/bin/bash

###  script arguments as follow:
###  1) Path to the billrun installation directory
###  2) Environment (dev / prod etc.)

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

CLIENTS=$1/conf/tenants/*.ini
LOCKS=$1/scripts/locks/

# Go through the files
for f in $CLIENTS
do
	if [ "$CLIENTS" != "$f" ]; then # non-empty folder check
		TEMP=$(basename $f)
		TENANT=${TEMP%.*}

		flock -n $LOCKS"/pseudo_cron_"$TENANT"_receive_all.lock" -c "php $INDEX --env $ENV --tenant $TENANT --receive --type all" &
		echo "Invoked receiving for $TENANT file"

		flock -n $LOCKS"/pseudo_cron_"$TENANT"_process_all.lock" -c "php $INDEX --env $ENV --tenant $TENANT --process --type all" &
		echo "Invoked processing for $TENANT file"

		flock -n $LOCKS"/pseudo_cron_"$TENANT"_calculate_customer.lock" -c "php $INDEX --env $ENV --tenant $TENANT --calculate --type customer" &
		echo "Invoked customer calculator for $TENANT file"

		flock -n $LOCKS"/pseudo_cron_"$TENANT"_calculate_rate.lock" -c "php $INDEX --env $ENV --tenant $TENANT --calculate --type Rate_Usage" &
		echo "Invoked rating calculator for $TENANT file"

		flock -n $LOCKS"/pseudo_cron_"$TENANT"_calculate_customer_pricing.lock" -c "php $INDEX --env $ENV --tenant $TENANT --calculate --type customerPricing" &
		echo "Invoked customer pricing calculator for $TENANT file"
	else
		echo "No tenants found"
	fi	
done