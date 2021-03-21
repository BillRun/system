#!/bin/bash

usage() {
        usage="$(basename "$0") -d PATH -e ENVIRONMENT -t AGGREGATOR TYPE -- this scripts runs the cycle action for all tenants

        where:
            -d DIRECTORY path to billrun directory.
            -e ENV current billrun environment
            -t TYPE aggregator type
            -h print help"

        echo "$usage"
}

TYPE=""
ENV=""
INDEX=""

parseInput() {
	OPTIND=1
	optionMask=0
        while getopts "hd:e:t:" option; do
          case $option in
            h) 
	       usage
               exit
               ;;
            d) ((optionMask^=1))
	       cd $OPTARG || { usage; exit 1; }
               export APPLICATION_MULTITENANT=1;
               INDEX=public/index.php
               ;;
            e) ((optionMask^=2))
	       ENV="$OPTARG"
               ;;
            t) ((optionMask^=4))
	       TYPE="$OPTARG"
               ;;
            :) printf "missing argument for -%s\n" "$OPTARG" >&2
               usage
               exit 1
               ;;
           \?) printf "illegal option: -%s\n" "$OPTARG" >&2
               usage
               exit 1
               ;;
          esac
        done
	
	if [ "$optionMask" != 7 ]; then
		usage
		exit 1;
	fi
 	
       shift $((OPTIND - 1))
}

parseInput "${@}"

CLIENTS=./conf/tenants/*.ini
LOCKS=./scripts/locks/

# Go through the files
for f in $CLIENTS
do
	if [ "$CLIENTS" != "$f" ]; then # non-empty folder check
		TEMP=$(basename $f)
		TENANT=${TEMP%.*}

		flock -n $LOCKS"/pseudo_cron_"$TENANT"_cycle.lock" -c "php $INDEX --env $ENV --tenant $TENANT --type $TYPE --cycle" &
		echo "Invoked cycling for $TENANT"

	else
		echo "No tenants found"
	fi	
done

