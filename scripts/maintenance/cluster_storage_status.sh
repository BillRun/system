#/bin/bash

for i in {1..3}
do
        echo "vrl-billdbm$i " `ssh vrl-billdbm$i 'df -h | grep mongodata | grep G'`
done

for i in {1..3}
do
        echo "vkl-billdbs$i " `ssh vkl-billdbs$i 'df -h | grep mongodata | grep G'`
done
