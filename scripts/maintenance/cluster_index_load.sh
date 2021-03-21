#/bin/bash
### this script is workaround for issue with 2.6.5 series of indexes loading

shard=$1

for i in {1..9}
do
	ipad=`printf %02d $i`
	full_shard=$shard$ipad.gt
	echo "Touch indexes of ${full_shard}. This will take awhile..."
	command='db.runCommand({ touch: "lines", data: false, index: true });'
	ssh $full_shard 'mongo --port 27018 admin -uadmin -pqsef1#2$ --eval "${command}"'
done
