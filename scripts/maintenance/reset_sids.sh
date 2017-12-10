#!/bin/bash
#uncomment this
host="172.28.202.111"
echo "what billrun do you want?[201404]";
read billrun; 

if [ -z  "$billrun" ]; then
  billrun="201404"
fi

echo "Send reset on $# subscribers lists with billrun : $billrun , to : $host ? [y/N]";
read cont;

if [  "$cont" = "y" ] || [ "$cont" = "yes" ]; then
  apiBase="http://$host/api/resetlines";
  billMonthArg="billrun=$billrun"
  url=$apiBase;

  for i in $*;
  do  
    postData="sid=$i&$billMonthArg"  
    echo "Sending '$postData' to URL : $url";
    wget -O - -q $url --read-timeout=0 --post-data $postData;
  done
  
  echo "";
else
  echo "Aborting...";
fi

exit;