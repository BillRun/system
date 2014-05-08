#!/bin/bash

from_date=`date -d '1 day ago' +'%F 00:00:00'`
to_date=`date -d '1 day ago' +'%F 23:59:59'`
date=`date -d '1 day ago' +'%F'`

#PORT IN:
portin_query="SELECT '$date' AS dom, COUNT(DISTINCT(Requests.number)) as total FROM Transactions INNER JOIN Requests ON Transactions.request_id = Requests.request_id WHERE Transactions.request_id LIKE 'NPGT%' AND Transactions.message_type='Execute_response' AND Transactions.reject_reason_code IS NULL AND last_transactions_time BETWEEN '$from_date' AND '$to_date' GROUP BY dom;"
echo $portin_query
mysql -h172.28.23.11 -unpg -pearth12 gtnp -N -e "$portin_query" >> portin_query.csv

#PORT OUT:
portout_query="SELECT '$date' AS dom, COUNT(DISTINCT request_id) AS total FROM Transactions WHERE request_id NOT LIKE 'NPGT%' AND message_type='Execute_response' AND reject_reason_code IS NULL AND last_transactions_time BETWEEN '$from_date' AND '$to_date';"
echo $portout_query
mysql -h172.28.23.11 -unpg -pearth12 gtnp -N -e "$portout_query" >> portout_query.csv
