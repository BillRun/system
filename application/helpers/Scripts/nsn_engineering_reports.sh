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

case $report_name in

	"top_50_incoming_calls" )
	js_code=$js_code'db.lines.aggregate({$match : {type : "nsn", usaget: "incoming_call", urt : {$gte : from_date , $lte : to_date }, sid : {$exists : 1}}},
                    {$group : {_id : "$sid",total_calls : {$sum : 1}, calls_answered : {$sum : {$cond : [{$gt : ["$usagev",0]},1,0]}}, total_volume : {$sum : "$usagev"} } },
                    {$sort : {total_volume : -1}}, {$limit : 50}).forEach(function(obj) {
                        print("'$day'" + "\t" + obj._id +"\t"+ obj.total_calls +"\t"+ obj.calls_answered +"\t"+ obj.total_volume);
                    });' ;;

	"top_50_calls" )
	js_code=$js_code'db.lines.aggregate({$match : {type : "nsn", usaget: "call", urt : {$gte : from_date , $lte : to_date }, sid : {$exists : 1}}},
                    {$group : {_id : "$sid",total_calls : {$sum : 1}, calls_answered : {$sum : {$cond : [{$gt : ["$usagev",0]},1,0]}}, total_volume : {$sum : "$usagev"} } },
                    {$sort : {total_volume : -1}}, {$limit : 50}).forEach(function(obj) {
                        print("'$day'" + "\t" + obj._id +"\t"+ obj.total_calls +"\t"+ obj.calls_answered +"\t"+ obj.total_volume);
                    });' ;;

	"top_50_sms" )
	js_code=$js_code'db.lines.aggregate({$match : {type : {$in : ["smsc","smpp","mmsc"]}, urt : {$gte : from_date , $lte : to_date }, sid : {$exists : 1}}},
                    {$group : {_id : "$sid",total_volume : {$sum : 1} }},
                    {$sort : {total_volume : -1}}, {$limit : 50}).forEach(function(obj) {
                        print("'$day'" + "\t" + obj._id +"\t"+ obj.total_volume);
                    });' ;;


	"international_calls" )
	js_code=$js_code'db.lines.aggregate({$match: {type : "nsn", usaget: "call",  urt : {$gte : from_date , $lte : to_date } , arate : {$exists : 1}}},
                    {$group: {_id : "$arate" , total_volume : {$sum : "$usagev"} ,total_calls : {$sum : 1} , total_successful: {$sum : {$cond : [{$gt : ["$usagev" , 0]} , 1, 0]}}}},
                    {$project : {_id : 1, total_volume : 1  , total_successful : 1, successful_ratio : {$multiply : [{$divide : ["$total_successful", "$total_calls"]},100]}, 
                                  average_duration : {$cond : [{$gt : ["$total_successful", 0]}, {$divide : ["$total_volume", "$total_successful"]}, 0]} }}
                    ).forEach(function(obj) {
                        var rate = db.rates.findOne(obj._id.$id).key;
                        print("'$day'" + "\t" + rate +"\t"+ obj.total_successful +"\t"+ obj.successful_ratio +"\t"+ obj.average_duration +"\t"+ obj.total_volume);
                    });';;

    "circuit_groups" )
	js_code=$js_code'db.lines.aggregate({$match: {type : "nsn", usaget: {$exists : 1, $in : ["call", "incoming_call"]} ,  urt : {$gte : from_date , $lte : to_date } ,  
                         sid : {$exists : 1}}},
                        {$group : {_id : {circuit_group : "$out_circuit_group" , type : "$usaget"} , circuit_group_name : {$first :"$out_circuit_group_name"} , 
                                sid_list : {$addToSet : "$sid"}, call_attempts : {$sum : 1}, total_successful: {$sum : {$cond : [{$gt : ["$usagev" , 0]} , 1, 0]}},
                                total_volume : {$sum : "$usagev"} }},
                        {$project : {ciruit_group : "$_id.circuit_group" , usaget : "$_id.type", circuit_group_name : 1, sid_count : {$size : "$sid_list"} , call_attempts : 1,
			 total_successful : 1, total_volume : 1}}
		).forEach(function(obj) {
                        print("'$day'" + "\t" + obj._id.circuit_group +"\t"+ obj.circuit_group_name  +"\t"+ obj.sid_count  +"\t"+ obj.call_attempts  +"\t"+ obj.total_successful  +"\t"+ 
                          obj.total_volume  +"\t"+ obj.total_volume/obj.sid_count  +"\t"+ obj.total_successful/obj.call_attempts*100 +"\t"+ obj._id.type);
                    });' ;;

	*)
	echo "Unrecognized report name";
	exit;
	;;
esac


if [[ -n "$js_code" ]]; then	
	mongo billing -ureading -pguprgri --quiet --eval "$js_code" > "$output_dir/$report_name.csv" ;
fi


exit;
