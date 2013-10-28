#!/bin/bash

iam="`whoami`";
month="201309";

size="10000";
if [ $1 ]; then
        size=$1;
fi

instences=15;
if [ $2 ]; then
        instences=$2;
fi

start_instance=0;
if [ $3 ]; then
        start_instance=$3;
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
