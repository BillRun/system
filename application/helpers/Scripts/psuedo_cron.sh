#!/bin/bash

###  script arguments as follow:
###  1) Path to the public index php file
###  2) Path to the config file folder

if [ $1 ]; then
	INDEX=$1
else
	echo "please supply the location of the public index php file"
	exit 1
fi

if [ $2 ]; then
    CLIENTS=$2/*.ini;
else
	echo "please supply the location of the config files folder"
	exit 2
fi

# Go through the files
for f in $CLIENTS
do
	TEMP=$(basename $f)
	ENV=${TEMP%.*}

	php $INDEX --env $ENV --receive --type all&
	echo "Invoked receiving for $ENV file"

	php $INDEX --env $ENV --process --type all&
	echo "Invoked processing for $ENV file"
done
