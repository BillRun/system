#!/bin/bash

## Script to create mongo sharding with 2 nodes on specific path on ports range 276XX
## Installation is localhost only

INSTALLPATH=$1

echo "Script is going to install mongo sharding on $INSTALLPATH"
echo "Installing log directory"
mkdir -p $INSTALLPATH/log
echo "Installing config node"
mkdir -p $INSTALLPATH/configdb1
mkdir -p $INSTALLPATH/configdb2
mkdir -p $INSTALLPATH/configdb3
echo "Deploy 3 config servers"
echo "Deploy config server 1"
mongod --quiet --configsvr --dbpath $INSTALLPATH/configdb1 --replSet rsconfig --fork --port 27621 --pidfilepath $INSTALLPATH/configdb1/mongod1-con.lock --logpath=$INSTALLPATH/log/con1.log
echo "Deploy config server 2"
mongod --quiet --configsvr --dbpath $INSTALLPATH/configdb2 --replSet rsconfig --fork --port 27622 --pidfilepath $INSTALLPATH/configdb2/mongod2-con.lock --logpath=$INSTALLPATH/log/con2.log
echo "Deploy config server 3"
mongod --quiet --configsvr --dbpath $INSTALLPATH/configdb3 --replSet rsconfig --fork --port 27623 --pidfilepath $INSTALLPATH/configdb3/mongod3-con.lock --logpath=$INSTALLPATH/log/con3.log
echo "Configure config servers"
mongo --quiet --port 27621 --eval 'rs.initiate({"_id": "rsconfig", configsvr: true, members: [{ "_id": 0, "host": "localhost:27621" },{ "_id": 1, "host": "localhost:27622" }, { "_id": 2, "host": "localhost:27623" }]});'

echo "Installing replica-set data node 1"
mkdir -p $INSTALLPATH/data1a
mkdir -p $INSTALLPATH/data1b
mkdir -p $INSTALLPATH/arb1
echo ""
echo "Deploy replica-set 1"
echo "Deploy replica-set 1 server a"
mongod --quiet --shardsvr --replSet "rs1" --dbpath $INSTALLPATH/data1a --port 27631 --logpath=$INSTALLPATH/log/mongod1a.log --fork
echo "Deploy replica-set 1 server b"
mongod --quiet --shardsvr --replSet "rs1" --dbpath $INSTALLPATH/data1b --port 27632 --logpath=$INSTALLPATH/log/mongod1b.log --fork
echo "Deploy replica-set 1 arbiter"
mongod --quiet --port 30000 --dbpath $INSTALLPATH/arb1 --replSet rs0 --fork --logpath=$INSTALLPATH/log/arb1.log --smallfiles --nojournal
echo "Configure replica-set 1"
echo "Configure server a of replica-set 1"
mongo --quiet --port 27631 --eval 'rs.initiate({"_id": "rs1", members: [{ "_id": 0, "host": "localhost:27631" }]});'
echo "Configure server b (secondary) of replica-set 1"
mongo --quiet --port 27631 --eval 'rs.add("localhost:27632");'
echo "Configure arbiter of replica-set 1"
mongo --quiet --port 27631 --eval 'rs.addArb("localhost:30000");'

echo ""
echo "Installing replica-set data node 2"
mkdir -p $INSTALLPATH/data2a
mkdir -p $INSTALLPATH/data2b
mkdir -p $INSTALLPATH/arb2
echo ""
echo "Deploy replica-set 2"
echo "Deploy replica-set 2 server a"
mongod --quiet --shardsvr --replSet "rs2" --dbpath $INSTALLPATH/data2a --port 27641 --logpath=$INSTALLPATH/log/mongod2a.log --fork
echo "Deploy replica-set 2 server b"
mongod --quiet --shardsvr --replSet "rs2" --dbpath $INSTALLPATH/data2b --port 27642 --logpath=$INSTALLPATH/log/mongod2b.log --fork
echo "Deploy replica-set 1 arbiter"
mongod --quiet --port 30001 --dbpath $INSTALLPATH/arb2 --replSet rs1 --fork --logpath=$INSTALLPATH/log/arb2.log --smallfiles --nojournal
echo ""
echo "Configure replica-set 2"
echo "Configure server a of replica-set 2"
mongo --quiet --port 27641 --eval 'rs.initiate({"_id": "rs2", members: [{ "_id": 0, "host": "localhost:27641" }]});'
echo "Configure server b (secondary) of replica-set 2"
mongo --quiet --port 27641 --eval 'rs.add("localhost:27642");'
echo "Configure arbiter of replica-set 2"
mongo --quiet --port 27641 --eval 'rs.addArb("localhost:30001");'

echo ""
echo "Installing mongos node"
mongos --quiet --configdb rsconfig/localhost:27621,localhost:27622,localhost:27623 --port 27617 --logpath=$INSTALLPATH/log/mongos.log --fork

echo ""
echo "Configured data nodes as shards"
echo "add shard 1 to cluster"
mongo --quiet --port 27617 admin --eval 'sh.addShard("rs1/localhost:27631");'
echo "add shard 2 to cluster"
mongo --quiet --port 27617 admin --eval 'sh.addShard("rs2/localhost:27641");'
