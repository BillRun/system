#/bin/bash


for i in {1..9}
do
        ipad=`printf %02d $i`
        echo "pri$ipad " `ssh pri$ipad.gt 'df -h | grep ssd | grep G'`

done

for i in {1..9}
do
        ipad=`printf %02d $i`
        echo "slv$ipad " `ssh slv$ipad.gt 'df -h | grep ssd | grep G'`
done

