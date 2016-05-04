#!/bin/bash

###  script arguments as follow:
###  1) day (YYYY-MM-dd format)
###  2) output directory

if [ $1 ]; then
        day=$1;
else
	echo "please supply day (format: YYYY-MM-dd)"
	exit 2
fi

if [ $2 ]; then
	output_dir=$2;
else
	script_dir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
	output_dir="${script_dir}/../../../files/wholesale";
fi

js_code='db.getMongo().setReadPref("secondaryPreferred");'

js_code=$js_code'db.lines.aggregate({$match:{urt:{$gte:ISODate("'$day'T00:00:00+03:00"),$lt:ISODate("'$day'T23:59:59+03:00")},$or:[{over_plan:{$exists:1}},{out_plan:{$exists:1}}]}},{$project:{_id:0,over_aprice:{$cond: [{$gt: ["$over_plan", 0]}, "$aprice", 0]},out_aprice:{$cond: [{$gt: ["$out_plan", 0]}, "$aprice", 0]}}},{$group:{_id:0,over:{$sum:"$over_aprice"},out:{$sum:"$out_aprice"}}}).forEach(function (obj){print("'$day'" + "\t" + obj.over + "\t" + obj.out)});';

mongo billing -ureading -pguprgri --quiet --eval "$js_code" >> "$output_dir/extra.csv" ;

exit;
