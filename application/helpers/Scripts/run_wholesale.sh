#!/bin/bash

###  script arguments as follow:
###  1) year
###  2) month
###  3) output directory

if [ $1 ]; then
        year=$1;
else
	echo "please supply year (format: YYYY)"
	exit 2
fi

if [ $2 ]; then
        month=$2;
else
	echo "please supply month (format: MM)"
	exit 2
fi

output_dir="";
if [ $3 ]; then
	output_dir=$3;
fi

reports=( "gt_out_sms" "nr_out_sms" "data" "all_in_call" "all_out_call" "all_nr_out_call" "all_nr_in_call" )

wd=`pwd`;

for i in "${reports[@]}"
do
   :
	echo $i;
	$wd/wholesale_reports.sh $i $year $month $output_dir
done