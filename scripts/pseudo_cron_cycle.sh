#!/bin/bash

###  script arguments as follow:
###  1) Tenant's name
###  2) Environment (dev / prod etc.)
###  3) Path to the billrun index script

if [ $1 ]; then
	TENANT=$1
else
	echo "please supply tenant name"
	exit 1
fi

if [ $2 ]; then
	ENV=$2
else
	echo "please supply an environment"
	exit 2
fi

if [ $3 ]; then
	INDEX=$3
else
	echo "please supply a path to index file"
	exit 3
fi

php $INDEX --env $ENV --tenant $TENANT --receive --type all
echo "Invoked receiving for "$TENANT" file"

php $INDEX --env $ENV --tenant $TENANT --process --type all
echo "Invoked processing for "$TENANT" file"

php $INDEX --env $ENV --tenant $TENANT --calculate --type customer
echo "Invoked customer calculator for "$TENANT" file"

php $INDEX --env $ENV --tenant $TENANT --calculate --type Rate_Usage
echo "Invoked rating calculator for "$TENANT" file"

php $INDEX --env $ENV --tenant $TENANT --calculate --type customerPricing
echo "Invoked customer pricing calculator for "$TENANT" file"

php $INDEX --env $ENV --tenant $TENANT --calculate --type tax
echo "Invoked tax calculator for "$TENANT" file"

php $INDEX --env $ENV --tenant $TENANT --calculate --type unify
echo "Invoked unify calculator for "$TENANT" file"

php $INDEX --env $ENV --tenant $TENANT --calculate --type rebalance
echo "Invoked rebalance calculator for "$TENANT" file"