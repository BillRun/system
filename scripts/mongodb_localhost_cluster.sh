#!/bin/bash

## Script to create mongo sharding with 2 nodes on specific path on ports range 276XX
## Installation is localhost only

INSTALLPATH=$1

echo "Script is going to install mongo sharding on $INSTALLPATH"
echo "Installing log directory"
mkdir -p $INSTALLPATH/log/
echo "Installing config node"
mkdir -p $INSTALLPATH/configdb
mongod --quiet --configsvr --dbpath $INSTALLPATH/configdb --replSet rsconfig --fork --port 27621 --pidfilepath $INSTALLPATH/configdb/mongod-con.lock --logpath=$INSTALLPATH/log/con.log
mongo --quiet --port 27621 --eval 'rs.initiate({"_id": "rsconfig", configsvr: true, members: [{ "_id": 0, "host": "localhost:27621" }]});'

echo "Installing data node 1"
mkdir -p $INSTALLPATH/data1
mongod --quiet --shardsvr --dbpath $INSTALLPATH/data1 --port 27618 --logpath=$INSTALLPATH/log/mongod1.log --fork

echo "Installing data node 2"
mkdir -p $INSTALLPATH/data2
mongod --quiet --shardsvr --dbpath $INSTALLPATH/data2 --port 27619 --logpath=$INSTALLPATH/log/mongod2.log --fork

echo "Installing mongos node"
mongos --quiet --configdb rsconfig/localhost:27621 --port 27617 --logpath=$INSTALLPATH/log/mongos.log --fork

echo "Configured data nodes as shards"
mongo --quiet --port 27617 admin --eval 'sh.addShard( "localhost:27618" );'
mongo --quiet --port 27617 admin --eval 'sh.addShard( "localhost:27619" );'
