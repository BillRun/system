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

tz_from=`date -d "$day"T00:00:00 +%:z`
tz_to=`date -d "$day"T23:59:59 +%:z`
js_code='db.getMongo().setReadPref("secondaryPreferred");var from_date = ISODate("'$day'T00:00:00'$tz_from'");var to_date = ISODate("'$day'T23:59:59'$tz_to'");';
nsn_end_code='.forEach(function(obj) { print("call\t" + dir + "\t" + network + "\t'$day'\t" + ( obj._id.c ) + "\t" +( obj._id.r ? db.rates.findOne(obj._id.r.$id).key : (obj._id.k ? obj._id.k : "")) + "\t" + obj.count + "\t" + obj.usagev + "\t'$report_name'" );})';
data_end_code='.forEach(      function(obj) {         print("data\t" + dir + "\t" + network + "\t'$day'\t" +  (obj._id.match(/^37\.26/) ? "GT" : (obj._id.match(/^62\.90/) ? "MCEL" : "OTHER") )  +"\tINTERNET_BY_VOLUME" + "\t" + obj.count + "\t" + obj.usagev + "\t'$report_name'");})';
sms_end_code='.forEach(      function(obj) {         print("sms\t" + dir + "\t" + network + "\t'$day'\t" +  obj._id.c  + "\t" + (obj._id.r ? db.rates.findOne(obj._id.r.$id).key : "") + "\t" + obj.count + "\t" + obj.usagev+ "\t'$report_name'");})';
sipregex='^(?=NSML|NBZI|MAZI|MCLA|ISML|IBZI|ITLZ|IXFN|IMRS|IHLT|HBZI|IKRT|IKRTROM|SWAT|GSML|GNTV|GHOT|GBZQ|GBZI|GCEL|LMRS|NNTV|NXFN|VVOM|PCLB|PCTI|NAZI|PTES|IBCS|MXFN|MPELSIP|MCELSIP|MFRE|NFRE|NHEL|NCLA|RCELSIP|INTV|MANT|NBNT|NCEL|PROM|RYEM|AAST|DDWW|DKRT|POPC|RCLI|SFRE)';
sipregex_negative='^(?!NSML|NBZI|MAZI|MCLA|ISML|IBZI|ITLZ|IXFN|IMRS|IHLT|HBZI|IKRT|IKRTROM|SWAT|GSML|GNTV|GHOT|GBZQ|GBZI|GCEL|LMRS|NNTV|NXFN|VVOM|PCLB|PCTI|NAZI|PTES|IBCS|MXFN|MPELSIP|MCELSIP|MFRE|NFRE|NHEL|NCLA|RCELSIP|INTV|MANT|NBNT|NCEL|PROM|RYEM|AAST|DDWW|DKRT|POPC|RCLI|SFRE)';
nsn_grouping_out='{$group:{_id:{c:"$out_circuit_group_name",r:{$ifNull:["$arate",false]}, count:{$sum:{$ifNull : ["$lcount",1]}},usagev:{$sum:"$usagev"}}},{$project:{"_id.c":{$substr:["$_id.c",0,4]},"_id.r":1, count:1,usagev:1}},{$group:{_id:"$_id",count:{$sum:"$count"},usagev:{$sum:"$usagev"}}}';
nsn_grouping_in='{$group:{_id:{c:"$in_circuit_group_name",r:"$pzone",k:"$wholesale_rate_key"}, count:{$sum:{$ifNull : ["$lcount",1]}},usagev:{$sum:"$usagev"}}},{$project:{"_id.c":{$substr:["$_id.c",0,4]},"_id.r":1,"_id.k":1, count:1,usagev:1}},{$group:{_id:"$_id",count:{$sum:"$count"},usagev:{$sum:"$usagev"}}}';
out_str='FG'
in_str='TG'

case $report_name in

	"gt_out_sms" )
	js_code=$js_code'var dir="'$out_str'";var network = "all";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"smsc", "calling_msc" : /^0*97258/, arate:{$exists:1, $ne:false}}},{$group:{_id:{c:"$called_msc",r:"$arate"}, count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code $sms_end_code" ;;

	"nr_out_sms" )
	js_code=$js_code'var dir="'$out_str'";var network = "nr";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"smsc", "calling_msc" : /^0*97252/, arate:{$exists:1, $ne:false}}},{$group:{_id:{c:"$called_msc",r:"$arate"}, count:{$sum:1},usagev:{$sum:"$usagev"}}})';
	js_code="$js_code $sms_end_code" ;;

	"data" )
	js_code=$js_code'var dir="";var network = "all";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"ggsn"}},{$group:{_id:{$substr:["$sgsn_address",0,5]}, count:{$sum:{$ifNull : ["$lcount",1]}},usagev:{$sum:"$usagev"}}})'; 
	js_code="$js_code$data_end_code" ;;

	"all_in_call" )
	js_code=$js_code'var dir="'$in_str'";var network = "all";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", $or:[{record_type:{$in:["12","31"]}, $and: [{in_circuit_group_name:/^(?!FCEL|BICC|BMSS)/},{in_circuit_group_name:/'$sipregex_negative'/}],out_circuit_group_name:/^(?=RCEL)/},{record_type:"11",$and : [ {in_circuit_group_name:/^(?!FCEL|RCEL|BICC|BMSS|TONES|PCLB|PCTI|$)/} , {in_circuit_group_name:/'$sipregex_negative'/}],out_circuit_group_name:/^(?!FCEL|RCEL)/}],usagev:{$exists:1,$gt:0}}},'$nsn_grouping_in')';
	js_code="$js_code$nsn_end_code" ;;

	"all_out_call" )
 	js_code=$js_code'var dir="'$out_str'";var network = "all";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", $or:[{record_type:{$in:["01"]},out_circuit_group_name:/'$sipregex_negative'/},{record_type:{$in:["11","30"]}, in_circuit_group_name:/^(?!BICC)/,out_circuit_group_name:/'$sipregex_negative'/},{record_type:"12",in_circuit_group_name:/^(BICC|BMSS)/}], out_circuit_group_name:/^(?!FCEL|VVOM|BICC|BMSS)/,usagev:{$exists:1,$gt:0} }},'$nsn_grouping_out')';
	js_code="$js_code$nsn_end_code" ;;

	"all_nr_out_call" )
	js_code=$js_code'var dir="'$out_str'";var network = "nr";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", $or:[{record_type:{$in:["11","30"]},in_circuit_group_name:/^(RCEL|4CEL)/ },{record_type:"01", calling_subs_last_ex_id : /^97252/}],usagev:{$exists:1,$gt:0}}},'$nsn_grouping_out')';
	js_code="$js_code$nsn_end_code" ;;

	"all_nr_in_call" )
	js_code=$js_code'var dir="'$in_str'";var network = "nr";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", $or:[{record_type:{$in:["31","12"]},out_circuit_group_name:/^RCEL/ },{record_type:"02", called_subs_last_ex_id : /^97252/}],usagev:{$exists:1,$gt:0}}},'$nsn_grouping_in')';
	js_code="$js_code$nsn_end_code" ;;

	"ho_out_call" )
	js_code=$js_code'var dir="'$out_str'";var network = "ho";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"01", calling_subs_last_ex_id : /^97252/,usagev:{$exists:1,$gt:0}}},'$nsn_grouping_out')';
	js_code="$js_code$nsn_end_code" ;;

	"ho_in_call" )
	js_code=$js_code'var dir="'$in_str'";var network = "ho";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type:"02", called_subs_last_ex_id : /^97252/,usagev:{$exists:1,$gt:0}}},'$nsn_grouping_in')';
	js_code="$js_code$nsn_end_code" ;;

	"sip_out_call" )
 	js_code=$js_code'var dir="'$out_str'";var network = "sip";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type : "31",out_circuit_group_name:/^(?!RCEL|4CEL)/}},'$nsn_grouping_out')';
	js_code="$js_code$nsn_end_code" ;;

	"sip_in_call" )
 	js_code=$js_code'var dir="'$in_str'";var network = "sip";db.lines.aggregate({$match:{urt:{$gte:from_date, $lte:to_date}, type:"nsn", record_type : "30",in_circuit_group_name:/^(?!RCEL|4CEL)/}},'$nsn_grouping_in')';
	js_code="$js_code$nsn_end_code" ;;


	"pal_in_call" )
	js_code=$js_code'var dir="'$in_str'";var network = "all";db.lines.aggregate(
		{$match : {
			type : "nsn" ,
			$or:[{record_type:"12",in_circuit_group_name:/^SPAL/,out_circuit_group_name:/^(?=RCEL)/},
				 {record_type:"11",in_circuit_group_name:/^SPAL/,out_circuit_group_name:/^(?!FCEL|RCEL)/}] , 
			in_circuit_group_name : /^SPAL/,
			urt : {$gte : from_date, $lte : to_date } }},
		{$project : { usagev : 1 , icg: {$substr:["$in_circuit_group_name",0,4]},
			carrier : {$cond : [ 
								{$or : [
									{$eq : [{$substr : ["$calling_number" , 0,5]},"97222"]},
									{$eq : [{$substr : ["$calling_number" , 0,5]},"97242"]},
									{$eq : [{$substr : ["$calling_number" , 0,5]},"97282"]},
									{$eq : [{$substr : ["$calling_number" , 0,5]},"97292"]},									
									{$eq : [{$substr : ["$calling_number" , 0,5]},"97279"]},
								]},	"IL_FIX_PALTEL" ,
								{$cond : [ {$or : [
									{$eq : [{$substr : ["$calling_number" , 0,4]},"9725"]},
										]} ,  "IL_MOBILE_JAWAL"  , "IL_MOBILE_OTHER" ] } 
								] } 
					} },
		{$group : {_id : {c: "$icg" ,r: "$carrier"} , usagev : {$sum : "$usagev"} , count : {$sum : 1} }}).forEach(function(obj) {
		print("call\t" + dir + "\t" + network + "\t'$day'\t" + ( obj._id.c ) + "\t" +( obj._id.r )  + "\t" + obj.count + "\t" + obj.usagev);})';;
	*)
	echo "Unrecognized report name";
	exit;
	;;
esac


if [[ -n "$js_code" ]]; then	
 	mongo billing -ureading -pguprgri --quiet --eval "$js_code" >> "$output_dir/$report_name.csv" ;
fi

exit;
