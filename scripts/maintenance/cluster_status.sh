#/bin/bash


stat='mongo --port 27018 admin -uadmin -padmin123 --eval "tojson(rs.status());" | grep stateStr; echo "|" ; mongo --port 27018 admin -uadmin -padmin123 --eval "tojson(rs.status());" | grep "optimeDate"';
stat2='mongo --port 27018 admin -uadmin -padmin123 --quiet --eval "db.serverStatus().version;"'
for i in {1..3}
do
        ipad=`printf %02d $i`
        echo "vrl-billdbm$i " `ssh vrl-billdbm$i $stat`
        echo "vrl-billdbm$i " `ssh vrl-billdbm$i $stat2`
done


for i in {1..3}
do
        ipad=`printf %02d $i`
        echo "vrl-billdbm$i " `ssh vkl-billdbs$i $stat`
        echo "vrl-billdbm$i " `ssh vkl-billdbs$i $stat2`
done
