#!/bin/bash
# One-shot initializer for the mongodb replica set (docker-compose-php74-cluster.yml).
# Initiates rs0 over the three mongod containers, then seeds billing_container
# the same way init-mongo.sh does for the standalone setup.

if command -v mongosh &>/dev/null; then
  MONGOC=mongosh
else
  MONGOC=mongo
fi

RS_HOSTS="billrun-mongodb:27017,billrun-mongodb2:27017,billrun-mongodb3:27017"

echo "waiting for mongod nodes to answer..."
for h in billrun-mongodb billrun-mongodb2 billrun-mongodb3; do
  until $MONGOC --host $h --eval "db.adminCommand('ping')" >/dev/null 2>&1; do
    sleep 2
  done
  echo "$h is up"
done

INITIATED=$($MONGOC --host billrun-mongodb --quiet --eval "rs.status().ok" 2>/dev/null)
if [ "$INITIATED" != "1" ]; then
  echo "initiating replica set rs0..."
  $MONGOC --host billrun-mongodb --eval '
    rs.initiate({
      _id: "rs0",
      members: [
        { _id: 0, host: "billrun-mongodb:27017",  priority: 2 },
        { _id: 1, host: "billrun-mongodb2:27017", priority: 1 },
        { _id: 2, host: "billrun-mongodb3:27017", priority: 1 }
      ]
    })'
else
  echo "replica set already initiated"
fi

echo "waiting for a primary to be elected..."
until [ "$($MONGOC --host billrun-mongodb --quiet --eval "db.isMaster().ismaster" 2>/dev/null)" = "true" ]; do
  sleep 2
done
echo "primary is billrun-mongodb"

# query the primary directly: a rs0/... connection spews replica-set-monitor
# log lines into stdout, which would corrupt the captured count
SEEDED=$($MONGOC --host billrun-mongodb --quiet --eval "db.getSiblingDB('billing_container').config.count()" 2>/dev/null | tail -1 | tr -d '[:space:]')
if [ -z "$SEEDED" ] || [ "$SEEDED" = "0" ]; then
  echo "seeding billing_container..."
  $MONGOC --host "rs0/$RS_HOSTS" billing_container /billrun/mongo/create.ini

  mongoimport --host "rs0/$RS_HOSTS" -d billing_container -c config /billrun/mongo/base/config.export --batchSize 1
  mongoimport --host "rs0/$RS_HOSTS" -d billing_container -c taxes /billrun/mongo/base/taxes.export --batchSize 1
  FILE=/billrun/mongo/first_users.ini
  if test -f "$FILE"; then
      mongoimport --host "rs0/$RS_HOSTS" -d billing_container -c users $FILE
  fi
  FILE=/billrun/mongo/first_users.json
  if test -f "$FILE"; then
      mongoimport --host "rs0/$RS_HOSTS" -d billing_container -c users $FILE
  fi
  $MONGOC --host "rs0/$RS_HOSTS" billing_container /billrun/mongo/migration/script.js

  for f in /plugin/mongo/installation/*.js
  do
      [ -f "$f" ] || break
      $MONGOC --host "rs0/$RS_HOSTS" billing_container $f
  done

  for f in /plugin/mongo/migration/*.js
  do
      [ -f "$f" ] || break
      $MONGOC --host "rs0/$RS_HOSTS" billing_container $f
  done
else
  echo "billing_container already seeded ($SEEDED config docs), skipping import"
fi

echo "replica set rs0 is ready"
