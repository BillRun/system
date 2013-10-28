#!/bin/bash

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

instences=15;
if [ $3 ]; then
        instences=$3;
fi

start_instance=0;
if [ $4 ]; then
        start_instance=$4;
fi

if [ $iam != "billrun" ]; then
        echo "must run under billrun user not : " $iam;
        exit;
fi

for i in `seq 1 $instences`; do
        page=`expr $start_instance \+ $i`;
        echo php -t /var/www/billrun/ /var/www/billrun/public/index.php  -a --type customer --stamp $month --page $page --size $size &
        echo sleep 5;
done

exit;
