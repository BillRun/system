#!/bin/bash

###  script arguments as follow:
###  1) report name
###  2) report day (YYYY-mm-dd format)
###  3) output directory

if [ $1 ]; then
        report_name=$1;
else
	echo "please supply a report name"
	exit 2
fi

if [ $2 ]; then
        day=$2;
else
	echo "please supply day (format: YYYY-mm-dd)"
	exit 2
fi

if [ $3 ]; then
	output_dir=$3;
else
	script_dir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
	output_dir="${script_dir}/../../../files/wholesale";
fi

js_code='db.getMongo().setReadPref("secondaryPreferred");var from_date = ISODate("'$day'T00:00:00+02:00");var to_date = ISODate("'$day'T23:59:59+02:00");';
nsn_end_code='.result.forEach(function(obj) { print("call\t" + dir + "\t" + network + "\t'$day'\t" + ( obj._id.c ) + "\t" +( obj._id.r ? db.rates.findOne(obj._id.r.$id).key : "") + "\t" + obj.count + "\t" + obj.usagev);})';
data_end_code='.result.forEach(      function(obj) {         print("data\t" + dir + "\t" + network + "\t'$day'\t" +  (obj._id.match(/^37\.26/) ? "GT" : (obj._id.match(/^62\.90/) ? "MCEL" : "OTHER") )  +"\tINTERNET_BY_VOLUME" + "\t" + obj.count + "\t" + obj.usagev);})';
sms_end_code='.result.forEach(      function(obj) {         print("sms\t" + dir + "\t" + network + "\t'$day'\t" +  obj._id.c  + "\t" + (obj._id.r ? db.rates.findOne(obj._id.r.$id).key : "") + "\t" + obj.count + "\t" + obj.usagev);})';
sipregex='^(?=NSML|NBZI|MAZI|MCLA|ISML|IBZI|ITLZ|IXFN|IMRS|IHLT|HBZI|IKRT|IKRTROM|SWAT|GSML|GNTV|GHOT|GBZQ|GBZI|GCEL|LMRS)';
nsn_grouping_out='{$group:{_id:{c:"$out_circuit_group_name",r:"$arate"}, count:{$sum:1},usagev:{$sum:"$usagev"}}},{$project:{"_id.c":{$substr:["$_id.c",0,4]},"_id.r":1, count:1,usagev:1}},{$group:{_id:"$_id",count:{$sum:"$count"},usagev:{$sum:"$usagev"}}}';
nsn_grouping_in='{$group:{_id:{c:"$in_circuit_group_name",r:"$pzone"}, count:{$sum:1},usagev:{$sum:"$usagev"}}},{$project:{"_id.c":{$substr:["$_id.c",0,4]},"_id.r":1, count:1,usagev:1}},{$group:{_id:"$_id",count:{$sum:"$count"},usagev:{$sum:"$usagev"}}}';
out_str='FG'
in_str='TG'

#compatibility with 2.6 - aggregate is cursor
mongo_main_version=`mongo --version | awk '//{split($4,a,"."); print a[1]"."a[2]}'`
if [ $mongo_main_version == "2.6" ] ; then
	nsn_end_code=${nsn_end_code/\.result/}
	data_end_code=${data_end_code/\.result/}
	sms_end_code=${sms_end_code/\.result/}
fi

case $report_name in

	"gt_out_sms" )
	js_code=$js_code'var dir="'$out_str'";var network = "all";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"smsc", "calling_msc" : /^0*97258/, arate:{$exists:1, $ne:false}}},{$group:{_id:{c:"$called_msc",r:"$arate"}, count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code $sms_end_code" ;;

	"nr_out_sms" )
	js_code=$js_code'var dir="'$out_str'";var network = "nr";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"smsc", "calling_msc" : /^0*97252/, arate:{$exists:1, $ne:false}}},{$group:{_id:{c:"$called_msc",r:"$arate"}, count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code $sms_end_code" ;;

	"data" )
	js_code=$js_code'var dir="";var network = "all";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"ggsn"}},{$group:{_id:{$substr:["$sgsn_address",0,5]}, count:{$sum:1},usagev:{$sum:"$usagev"}}})'; 
	js_code="$js_code$data_end_code" ;;

	"all_in_call" )
	js_code=$js_code'var dir="'$in_str'";var network = "all";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", $or:[{record_type:"02",in_circuit_group_name:/'$sipregex'/},{record_type:"12",in_circuit_group_name:/'$sipregex'/,out_circuit_group_name:/^(?=BICC)/},{record_type:"12",in_circuit_group_name:/^(?!FCEL|BICC)/,out_circuit_group_name:/^(?=RCEL)/},{record_type:"11",in_circuit_group_name:/^(?!FCEL|RCEL|BICC|TONES|PCLB|PCTI|$)/,out_circuit_group_name:/^(?!FCEL|RCEL)/}]}},'$nsn_grouping_in')';
	js_code="$js_code$nsn_end_code" ;;

	"all_out_call" )
	js_code=$js_code'var dir="'$out_str'";var network = "all";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", $or:[{record_type:"01"},{record_type:"11", in_circuit_group_name:/^RCEL/},{record_type:"12",in_circuit_group_name:/^BICC/}], out_circuit_group_name:/^(?!RCEL|FCEL|VVOM|BICC)/ }},'$nsn_grouping_out')';
	js_code="$js_code$nsn_end_code" ;;

	"all_nr_out_call" )
	js_code=$js_code'var dir="'$out_str'";var network = "nr";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"11",in_circuit_group_name:/^RCEL/ }},'$nsn_grouping_out')';
	js_code="$js_code$nsn_end_code" ;;

	"all_nr_in_call" )
	js_code=$js_code'var dir="'$in_str'";var network = "nr";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"12",out_circuit_group_name:/^RCEL/ }},'$nsn_grouping_in')';
	js_code="$js_code$nsn_end_code" ;;

	*)
	echo "Unrecognized report name";
	exit;
	;;
esac


if [[ -n "$js_code" ]]; then	
	mongo billing -ureading -pguprgri --quiet --eval "$js_code" >> "$output_dir/$report_name.csv" ;
fi


exit;
