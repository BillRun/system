#!/bin/bash

for i in `ls $1`; do
    flock -w 3600 $i echo "$i isn't locked";
    if [ $? -eq 1 ]; then 
      flock -u $i echo "$i Unlocked";
      rm $i;
    fi 
done 

