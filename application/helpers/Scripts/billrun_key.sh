#!/bin/bash

###  script arguments as follow:
###  1) date to check on (YYYY-mm-dd)
###  2) charging day

if [ $1 ]; then
        by_date=$1;
else
	echo "please supply the date in YYYY-mm-dd format"
	exit 2
fi

if [ $2 ]; then
        charging_day=$2;
else
	charging_day=25
fi


day=${by_date:8:2}

if [ "$day" -lt "$charging_day" ]; then
	date_from=${by_date}
else
	date_from=`date -d "${by_date} -$(date -d "$by_date" +%d) days +1 month +1 day" +'%F'`
fi

echo ${date_from:0:4}${date_from:5:2}