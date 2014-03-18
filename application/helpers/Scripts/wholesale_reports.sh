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

month_end=3;
#`date -d "$(date -d "$year-$month-01" +%Y-%m-01) +1 month -1 day" +%d`;
	
js_code='db.getMongo().setReadPref("secondaryPreferred");var start_day = 1; var end_day = '$month_end'; for(var i = start_day; i <= end_day; i++) {var day = (i.toString().length==1 ? "0" + i : i);var from_date = ISODate("'$year'-'$month'-" + day + "T00:00:00+02:00");var to_date = ISODate("'$year'-'$month'-" + day + "T23:59:59+02:00");';
nsn_end_code='.result.forEach(      function(obj) {         print("'$year'-'$month'-" + day + "," + (!isNaN(parseInt(obj._id,10)) ? parseInt(obj._id,10) : obj._id ) + "," + obj.count + "," + obj.usagev);});}';
data_end_code='.result.forEach(      function(obj) {         print("'$year'-'$month'-" + day + "," +  obj._id  + "," + obj.count + "," + obj.usagev);});}';
sms_end_code='.result.forEach(      function(obj) {         print("'$year'-'$month'-" + day + "," +  obj._id  + "," + obj.count + "," + obj.usagev);});}';
sipregex='^(?=NSML|NBZI|MAZI|MCLA|ISML|IBZI|ITLZ|IXFN|IMRS|IHLT|HBZI|IKRT|IKRTROM|SWAT|GSML|GNTV|GHOT|GBZQ|GBZI|GCEL|LMRS)';

case $report_name in

	"gt_out_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"01"}},{$group:{_id:"$out_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code$nsn_end_code" ;;

	"nr_out_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"11", in_circuit_group_name:/^RCEL/ , out_circuit_group_name:/^(?!FCEL|VVOM)/ }},{$group:{_id:"$out_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code$nsn_end_code" ;;

	"gt_in_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn",  record_type:"11" , in_circuit_group_name:/^(?!FCEL|VVOM)/,out_circuit_group_name:/^(?=PCT|BICC|PCL|$)/}},{$group:{_id:"$in_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code$nsn_end_code" ;;

	"nr_in_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"11" , in_circuit_group_name:/^(?!FCEL|VVOM)/ ,out_circuit_group_name:/^RCEL/}},{$group:{_id:"$in_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code$nsn_end_code" ;;

	"gt_out_sms" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"smsc", "calling_msc" : /^0*97258/, arate:{$exists:1, $ne:false}}},{$group:{_id:"$called_msc", count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code $sms_end_code" ;;

	"nr_out_sms" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"smsc", "calling_msc" : /^0*97252/, arate:{$exists:1, $ne:false}}},{$group:{_id:"$called_msc", count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code $sms_end_code" ;;

	"data" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"ggsn", sid:{$gt:0}}},{$group:{_id:"$sgsn_address", count:{$sum:1},usagev:{$sum:"$usagev"}}})'; 
	js_code="$js_code$data_end_code" ;;

	"all_in_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", $or:[{record_type:"02",in_circuit_group_name:/'$sipregex'/},{record_type:"12",in_circuit_group_name:/'$sipregex'/,out_circuit_group_name:/^(?=BICC)/},{record_type:"12",in_circuit_group_name:/^(?!FCEL|BICC)/,out_circuit_group_name:/^(?=RCEL)/},{record_type:"11",in_circuit_group_name:/^(?!FCEL|RCEL|BICC|TONES|PCLB|PCTI|$)/,out_circuit_group_name:/^(?!FCEL|RCEL)/}]}},{$group:{_id:"$in_circuit_group", count:{$sum:1}, usagev:{$sum:"$usagev"}}})';
	js_code="$js_code$nsn_end_code" ;;

	"all_out_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", $or:[{record_type:"01"},{record_type:"11", in_circuit_group_name:/^RCEL/}], out_circuit_group_name:/^(?!RCEL|FCEL|VVOM)/ }},{$group:{_id:"$out_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code$nsn_end_code" ;;

	"all_nr_out_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"11",in_circuit_group_name:/^RCEL/ }},{$group:{_id:"$out_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code$nsn_end_code" ;;

	"all_nr_in_call" )
	js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"12",out_circuit_group_name:/^RCEL/ }},{$group:{_id:"$in_circuit_group", count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code$nsn_end_code" ;;

	*)
	echo "Unrecognized report name";
esac


if [[ -n "$js_code" ]]; then	
	mongo 172.29.202.111/billing -ureading -pguprgri --quiet --eval "$js_code" > "$output_dir/$report_name""_""$year$month.csv" ;
fi

exit;