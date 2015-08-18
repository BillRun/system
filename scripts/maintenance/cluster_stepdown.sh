#!/bin/bash

shard=$1
#echo $shard

for i in {1..9}
do
	ipad=`printf %02d $i`
	full_shard=$shard$ipad.gt
	echo $full_shard
	cmd='if [ -n "` mongo --port 27018 admin -uadmin -pqsef1#2$ --eval \"tojson(rs.status());\" | grep ARBITER `" ]; then mongo --port 27018 admin -uadmin -pqsef1#2$ --eval "rs.stepDown();"; fi'
		ssh $full_shard $cmd
done