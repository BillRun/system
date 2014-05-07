#!/bin/bash

###  script arguments as follow:
###  1) stamp id of the billrun (mandatory)
###  2) size of the page. default: 8000
###  3) amount  of  concurrent billruns  to  run (in one host). default: 15
###  4) page to start from, the first is 0 (zero). default: 0
###  5) sleep time (seconds) between each concurrent process. default: 120

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

size="8000";
if [ $2 ]; then
        size=$2;
fi

instances=15;
if [ $3 ]; then
        instances=$3;
fi
instances=`expr $instances \- 1`;

start_instance=0;
if [ $4 ]; then
        start_instance=$4;
fi

sleeptime=120;
if [ $5 ]; then
        sleeptime=$5;
fi

billrun_dir="/var/www/billrun";

for i in `seq 0 $instances`; do
        page=`expr $start_instance \+ $i`;
        screen -d -m php -t $billrun_dir $billrun_dir/public/index.php  --env prod --aggregate --type customer --stamp $month --page $page --size $size
        sleep $sleeptime;
done

exit;
