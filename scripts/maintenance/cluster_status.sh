#/bin/bash


stat='mongo --port 27018 admin -uadmin -pqsef1#2$ --eval "tojson(rs.status());" | grep stateStr; echo "|" ; mongo --port 27018 admin -uadmin -pqsef1#2$ --eval "tojson(rs.status());" | grep "optimeDate"';
stat2='mongo --port 27018 admin -uadmin -pqsef1#2$ --quiet --eval "db.serverStatus().version;"'
for i in {1..9}
do
        ipad=`printf %02d $i`
        echo "pri$ipad " `ssh pri$ipad.gt $stat`
        echo "pri$ipad " `ssh pri$ipad.gt $stat2`
done


for i in {1..9}
do
        ipad=`printf %02d $i`
        echo "slv$ipad " `ssh slv$ipad.gt $stat`
        echo "slv$ipad " `ssh slv$ipad.gt $stat2`
done

