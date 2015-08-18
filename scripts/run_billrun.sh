#!/bin/bash

###  script arguments as follow:
###  1) environment to run (mandatory)
###  2) stamp id of the billrun (mandatory)
###  3) size of the page. default: 8000
###  4) amount  of  concurrent billruns  to  run (in one host). default: 15
###  5) page to start from, the first is 0 (zero). default: 0
###  6) sleep time (seconds) between each concurrent process. default: 120
###  7) billrun directory. default: /var/www/billrun

if [ $1 ]; then
	billrun_env=$1;
else
	echo "please supply environment to run (first argument)"
	exit 2
fi

if [ $2 ]; then
	month=$2;
else
	echo "please supply stamp of the billrun (first argument; format: YYYYmm)"
	exit 2
fi

iam="`whoami`";
if [ $iam != "billrun" ] && ( [ "$billrun_env" == "prod" ] || [ "$billrun_env" == "production" ] ); then
	echo "must run under billrun user not : " $iam;
	exit;
fi

size="8000";
if [ $3 ]; then
	size=$3;
else
	echo "Using default size: $size"
fi

instances=15;
if [ $4 ]; then
	instances=$4;
else
	echo "Using default instances (pages): $instances"
fi
instances=`expr $instances \- 1`;

start_instance=0;
if [ $5 ]; then
	start_instance=$5;
else
	echo "Using default page offset: $start_instance"
fi

sleeptime=120;
if [ $6 ]; then
	sleeptime=$6;
else
	echo "Using default sleeptime: $sleeptime"
fi

billrun_dir="/var/www/billrun";
if [ $7 ]; then
	billrun_dir=$7;
else
	echo "Using default billrun directory: $billrun_dir"
fi

for i in `seq 0 $instances`; do
	page=`expr $start_instance \+ $i`;
	screen -d -m flock -n $billrun_dir"/workspace/locks/aggregate_lock_"$page"_"$size".tmp" php -t $billrun_dir $billrun_dir/public/index.php  --env $billrun_env --aggregate --type customer --stamp $month --page $page --size $size
	sleep $sleeptime;
done

exit;
