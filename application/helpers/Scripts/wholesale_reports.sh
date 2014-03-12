#!/bin/bash

###  script arguments as follow:
###  1) report name
###  2) year
###  3) month
###  4) output directory

if [ $1 ]; then
        report_name=$1;
else
	echo "please supply a report name"
	exit 2
fi

if [ $2 ]; then
        year=$2;
else
	echo "please supply year (format: YYYY)"
	exit 2
fi

if [ $3 ]; then
        month=$3;
else
	echo "please supply month (format: MM)"
	exit 2
fi

output_dir="/var/www/billrun/files/csvs";
if [ $4 ]; then
	output_dir=$4;
fi

month_end=`date -d "$(date -d "$year-$month-01" +%Y-%m-01) +1 month -1 day" +%d`;
	
js_code='db.getMongo().setReadPref("secondaryPreferred");var start_day = 1; var end_day = '$month_end'; for(var i = start_day; i <= end_day; i++) {var day = (i.toString().length==1 ? "0" + i : i);var from_date = ISODate("'$year'-'$month'-" + day + "T00:00:00+02:00");var to_date = ISODate("'$year'-'$month'-" + day + "T23:59:59+02:00");';

case $report_name in

	"gt_out_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"01"}},{$group:{_id:"$out_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;;

	"nr_out_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"11", $or:[{in_circuit_group:{$in:["1001","1006"]}},{in_circuit_group:{$gte:"1201",$lte:"1209"}}],out_circuit_group:{$nin:["3060","3061"]}}},{$group:{_id:"$out_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;;

	"gt_in_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"02"}},{$group:{_id:"$in_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;;

	"nr_in_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"12", $or:[{out_circuit_group:{$in:["1001","1006"]}},{out_circuit_group:{$gte:"1201",$lte:"1209"}}]}},{$group:{_id:"$in_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;;

	"gt_out_sms" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"smsc", "calling_msc" : /^0*97258/, arate:{$exists:1, $ne:false}}},{$group:{_id:"$called_msc", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;;

	"nr_out_sms" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"smsc", "calling_msc" : /^0*97252/, arate:{$exists:1, $ne:false}}},{$group:{_id:"$called_msc", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;;

	"data" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"ggsn"}},{$group:{_id:"$sgsn_address", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;; 
##.result.forEach(      function(obj) {  var prvdr = obj._id.sgsn_address;  prvdr = (prvdr.match(/37.26/) ? "GOLAN" : (prvdr.match(/62.90/) ? "NR" : "OTHER"));    print("'$year'-'$month'-" + day + "," + prvdr + "," + obj.count + "," + obj.usagev);});}' ;;

	"all_in_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"11", in_circuit_group:/^(?!FCEL|VVOM|PCT)/ , out_circuit_group_name:/^(?!FCEL|VVOM|PCT)/}},{$group:{_id:"$in_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;;

	"all_out_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"12",out_circuit_group_name:/^(?!FCEL|VVOM|PCT)/ ,in_circuit_group:/^(?!FCEL|VVOM|PCT)/ }},{$group:{_id:"$out_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;;

	"all_nr_out_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"11",in_circuit_group_name:/^RCEL/ ,out_circuit_group:/^(?!FCEL|VVOM)/ }},{$group:{_id:"$out_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;;

	"all_nr_in_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"12",out_circuit_group_name:/^RCEL/ ,in_circuit_group:/^(?!FCEL|VVOM)/ }},{$group:{_id:"$in_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})' ;;

	*)
	echo "Unrecognized report name";
esac


if [[ -n "$js_code" ]]; then
	js_code=$js_code'.result.forEach(      function(obj) {         print("'$year'-'$month'-" + day + "," + obj._id + "," + obj.count + "," + obj.usagev);});}';
	mongo 172.29.202.111/billing -ureading -pguprgri --quiet --eval "$js_code" > $output_dir/$report_name"_"$year$month.csv ;
fi

exit;