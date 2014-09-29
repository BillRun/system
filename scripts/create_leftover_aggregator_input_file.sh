#!/bin/bash

###  script arguments as follow:
###  1) billrun_key
###  2) billrun path
###  3) unix user
###  4) db username (optional)
###  5) db password

if [ $1 ]; then
        billrun_key=$1;
else
	echo "please supply a billrun key"
	exit 2
fi

if [ $2 ]; then
        billrun_dir=$2;
else
	echo "please supply the billrun project path"
	exit 2
fi

if [ $3 ]; then
        unix_user=$3;
else
	echo "please supply the unix user"
	exit 2
fi

if [ $4 ]; then
        username=$4;
	if [ $5 ]; then
        	password=$5;
	else
		echo "please supply a password"
		exit 3
	fi
else
	username=""
	password=""
fi


js_code='
db.getMongo().setReadPref("secondaryPreferred");
var billrun_key = "'$billrun_key'";
var username = "'$username'";
var password = "'$password'";
var from_billrun = {};
var from_balances = new Array();
var diff = new Array();
var output = new Object();
db.billrun.find({"billrun_key": billrun_key}, {_id: 0, subs: 1}).forEach(function(obj) {
	obj.subs.forEach(function(obj2) {
		from_billrun[obj2.sid+""] = null;
	})
});
balances_db = db.getSiblingDB("balances");
if (username != "") {
	balances_db.auth(username, password);
}
balances_coll = balances_db.getCollection("balances");
balances_coll.find({"billrun_month": billrun_key}, {_id: 0, sid: 1}).forEach(function(obj) {
	from_balances.push(obj.sid+"");
});
from_balances.forEach(function(obj){
	if (!from_billrun.hasOwnProperty(obj)) {
		diff.push(parseInt(obj));
	}
});
balances_coll.aggregate({$match: {"billrun_month": billrun_key, sid: {$in: diff}}}, {$group: {_id: {aid: "$aid"}, subs: {$addToSet: "$sid"}}}).forEach(function(obj) {
	var subs = new Array();
	obj.subs.forEach(function(sub) {
		var new_sub = new Object();
		new_sub.subscriber_id = sub.toString();
		new_sub.curr_plan = new_sub.next_plan = "NULL";
		subs.push(new_sub);
		print(obj._id.aid + "," + new_sub.subscriber_id + "," + balances_coll.findOne({'sid':sub,'billrun_month':billrun_key}).balance.cost); // comment to get json format + uncomment "printjson"
	});
	output[obj._id.aid] = new Object();
	output[obj._id.aid]["subscribers"] = subs;
});
//printjson(output);
';

if [ "$username" != "" ]; then
	auth_str="-u $username -p$password"
fi

mongo --quiet --eval "$js_code" billrun $auth_str | sudo -u $unix_user tee $billrun_dir"/files/"$billrun_key"_leftover_aggregator_input" > /dev/null