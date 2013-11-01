#!/bin/bash

###  script arguments as follow:
###  1) stamp id of the billrun (mandatory)
###  2) size of the page. default: 10000
###  3) amount  of  concurrent billruns  to  run (in one host). default: 15
###  4) page to start from, the first is 0 (zero). default: 0
###  5) the sleep time between each concurrent process. default: 5

iam="`whoami`";
if [ $iam != "billrun" ]; then
        echo "must run under billrun user not : " $iam;
        exit;
fi

if [ $1 ]; then
        month=$1;
else
	echo "please supply stamp of the billrun (format: YYYYmm)"
	exit 2
fi

size="10000";
if [ $2 ]; then
        size=$2;
fi

instances=15;
if [ $3 ]; then
        instances=$3;
fi

start_instance=0;
if [ $4 ]; then
        start_instance=$4;
fi

sleeptime=5;
if [ $5 ]; then
        sleeptime=$5;
fi

for i in `seq 1 $instances`; do
        page=`expr $start_instance \+ $i`;
        php -t /var/www/billrun/ /var/www/billrun/public/index.php  -a --type customer --stamp $month --page $page --size $size &
        sleep $sleeptime;
done

exit;
