#!/bin/bash

###  script arguments as follow:
###  1) environment to run (mandatory)
###  2) stamp id of the billrun (mandatory)
###  3) size of the page. default: 8000
###  4) amount  of  concurrent billruns  to  run (in one host). default: 15
###  5) page to start from, the first is 0 (zero). default: 0
###  6) sleep time (seconds) between each concurrent process. default: 120

if [ $1 ]; then
	billrun_env=$1;
else
	echo "please supply environment to run (first argument)"
	exit 2
fi

iam="`whoami`";
if [ $iam != "billrun" ] && ( [ "$billrun_env" == "prod" ] || [ "$billrun_env" == "production" ] ); then
	echo "must run under billrun user not : " $iam;
	exit;
fi

if [ $2 ]; then
        month=$2;
else
	echo "please supply stamp of the billrun (format: YYYYmm)"
	exit 2
fi

size="8000";
if [ $3 ]; then
        size=$3;
fi

instances=15;
if [ $4 ]; then
        instances=$4;
fi
instances=`expr $instances \- 1`;

start_instance=0;
if [ $5 ]; then
        start_instance=$5;
fi

sleeptime=120;
if [ $6 ]; then
        sleeptime=$6;
fi

billrun_dir="/var/www/billrun";

for i in `seq 0 $instances`; do
        page=`expr $start_instance \+ $i`;
        screen -d -m php -t $billrun_dir $billrun_dir/public/index.php --env $billrun_env --generate --type xml --stamp $month --page $page --size $size
        sleep $sleeptime;
done

exit;
