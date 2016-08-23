#!/bin/bash

###  script arguments as follow:
###  1) Path to the public index php file
###  2) Environment (dev / prod etc.)
###  3) Path to the config file folder

if [ $1 ]; then
	INDEX=$1
else
	echo "please supply the location of the public index php file"
	exit 1
fi

if [ $2 ]; then
	ENV=$2
else
	echo "please supply an environment"
	exit 2
fi

if [ $3 ]; then
	CLIENTS=$3/*.ini;
else
	echo "please supply the location of the tenants files folder"
	exit 3
fi

# Go through the files
for f in $CLIENTS
do
	if [ "$CLIENTS" != "$f" ]; then # non-empty folder check
		TEMP=$(basename $f)
		TENANT=${TEMP%.*}

		php $INDEX --env $ENV --tenant $TENANT --receive --type all&
		echo "Invoked receiving for $TENANT file"

		php $INDEX --env $ENV --tenant $TENANT --process --type all&
		echo "Invoked processing for $TENANT file"
	fi	
done