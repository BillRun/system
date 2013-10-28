#!/bin/bash

###  script arguments as follow:
###  1) stamp id of the billrun
###  2) size of the page
###  3) amount  of  concurrent billruns  to  run (in one   host)
###  4) page to start from, the first is 0 (zero)

iam="`whoami`";
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

if [ $iam != "billrun" ]; then
        echo "must run under billrun user not : " $iam;
        exit;
fi

for i in `seq 1 $instances`; do
        page=`expr $start_instance \+ $i`;
        php -t /var/www/billrun/ /var/www/billrun/public/index.php  -a --type customer --stamp $month --page $page --size $size &
        echo sleep 5;
done

exit;
