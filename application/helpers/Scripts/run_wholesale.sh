#!/bin/bash

###  script arguments as follow:
###  1) days back
###  2) date range in days
###  3) output directory

if [ $1 ]; then
        from_days_back=$1;
else
	echo "please supply number of days back from today (integer)"
	exit 2
fi

if [ $2 ]; then
        to_days_back=$((from_days_back-$2+1));
else
	echo "please supply the range for the report in days (integer)"
	exit 2
fi

if [ $3 ]; then
	output_dir=$3;
else
	script_dir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
	output_dir="${script_dir}/../../../files/wholesale";
fi

wholesale_reports=( "gt_out_sms" "nr_out_sms" "data" "all_in_call" "all_out_call" "all_nr_out_call" "all_nr_in_call" "ho_out_call" "ho_in_call" "pal_in_call" "sip_out_call" "sip_in_call" )
WD="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )" # hack to get the directory of the .sh file


for (( day=$from_days_back; day >= $to_days_back; day-- ))
do
	report_day=`date -d "$day days ago" +'%F'`
	for report in "${wholesale_reports[@]}"
	do
	   :
		echo $report_day $report;
		$WD/wholesale_reports.sh $report $report_day $output_dir
	done
	 $WD/wholesale_retail.sh $report_day $output_dir
done

nsn_reports_for_engineering=( "top_50_calls" "top_50_incoming_calls" "top_50_sms" "circuit_groups" "international_calls" )  
for (( day=$from_days_back; day >= $to_days_back; day-- ))
do
	report_day=`date -d "$day days ago" +'%F'`
	for report in "${nsn_reports_for_engineering[@]}"
	do
		:
		echo $report_day $report;
		$WD/nsn_engineering_reports.sh $report $report_day $output_dir
	done
done