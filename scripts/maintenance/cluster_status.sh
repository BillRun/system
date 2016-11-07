#/bin/bash


stat='mongo --port 27018 admin -uadmin -pqsef1#2$ --eval "tojson(rs.status());" | grep stateStr; echo "|" ; mongo --port 27018 admin -uadmin -pqsef1#2$ --eval "tojson(rs.status());" | grep "optimeDate"';
stat2='mongo --port 27018 admin -uadmin -pqsef1#2$ --quiet --eval "db.serverStatus().version;"'
echo "Let's report from pri nodes..."
echo ""
for i in {1..9}
do
        ipad=`printf %02d $i`
        echo "$ipad" `ssh pri$ipad.gt $stat`
        echo "pri$ipad mongo version" `ssh pri$ipad.gt $stat2`
done

echo ""
echo "Let's report from slv nodes..."
echo ""
for i in {1..9}
do
        ipad=`printf %02d $i`
        echo "$ipad" `ssh slv$ipad.gt $stat`
        echo "slv$ipad mongo version" `ssh slv$ipad.gt $stat2`
done

