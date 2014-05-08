#!/bin/bash

from_date=`date -d '1 day ago' +'%F 00:00:00'`
to_date=`date -d '1 day ago' +'%F 23:59:59'`
date=`date -d '1 day ago' +'%F'`

#Active subscribers query
active_query="SELECT '$date' AS dom, COUNT(*) AS total, plan_id FROM (SELECT should_be_billed(mss.status, subscribers.activate_ind, subscribers.creation_date, msisdn.status_np) AS open_or_closed, subscribers.plan_id FROM msisdn_subscriber_status mss LEFT JOIN msisdn_subscriber_status mss2 ON mss2.NDC_SN = mss.NDC_SN AND (mss2.eindex IS NULL OR mss2.eindex = 'group') AND mss.creation_date <  mss2.creation_date LEFT JOIN subscribers ON subscribers.id = mss.subscriber_id LEFT JOIN msisdn ON msisdn.NDC_SN = mss.NDC_SN WHERE mss2.creation_date IS NULL AND (mss.eindex IS NULL OR mss.eindex = 'group')) AS xxx WHERE open_or_closed='open' GROUP BY dom, plan_id;"
mysql -h172.29.200.25 -uworker -pfire1234 gtcrm -N -e "$active_query" >> /mnt/blof/wholesale/active.csv

# sim
sim_query="SELECT '$date' AS dom, IF((oi.type='SIM' or oi.type='SIM_SECONDARY'),'SIM','UPS') AS item_type, SUM(IF (o.status != 'cancelled',1,0)) as new, SUM(IF (o.status != 'cancelled',49,0)) as new_amount, SUM(IF (o.status = 'cancelled',1,0)) as cancelled, SUM(IF (o.status = 'cancelled',50,0)) as  cancelled_amount FROM orders_items oi left join orders o on oi.order_id = o.id where oi.creation_date BETWEEN '$from_date' AND '$to_date' group by dom, item_type;"
mysql -h172.29.200.25 -uworker -pfire1234 gtcrm -N -e "$sim_query" >> /mnt/blof/wholesale/sim.csv

#unsubscribe
unsubscribe_query="SELECT '$date' AS dom, count(*) FROM deactivation where status='pending' and creation_date BETWEEN '$from_date' AND '$to_date' order by NDC_SN;"
mysql -h172.29.200.25 -uworker -pfire1234 gtcrm -N -e "$unsubscribe_query" >> /mnt/blof/wholesale/unsubscribe.csv
