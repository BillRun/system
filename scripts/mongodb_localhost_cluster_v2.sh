#!/bin/bash

## Script to create mongodb sharding with 2 nodes on specific path on ports range {PORT_PREFIX}XX
## Installation is localhost only
echo "MongoDB Localhost Cluster Installation Script"
while getopts d:p:fh option
do
 case "${option}"
 in
 d) INSTALLPATH=$OPTARG;;
 f) FULLINSTALL="1";;
 h) HELP="1";;
 p) PORT_PREFIX="$OPTARG";;
 esac
done

if [ -n "$HELP" ]
then
	echo "Command to install mongodb cluster on localhost"
	echo
	echo "-d installation  directory (mandatory)"
	echo "-f full installation including redundancy for each component (optional)"
	echo "-p port prefix (default: 276)"
	echo "-h print help"
	echo
	echo "BillRun Technologies Ltd. Product"
	exit;
fi

if [ -z $INSTALLPATH ]
then
	echo "No installation path defined (d argument)"
	exit;
fi

if [ -z $PORT_PREFIX ]
then
	echo "No port prefix specified; setting 276 as port prefix"
	PORT_PREFIX=276
fi

echo "Installation path:" $INSTALLPATH

if [ -z "$FULLINSTALL" ]
then
	echo "Minimal installation required"
	echo "Installing log directory"
	mkdir -p $INSTALLPATH/log/
	echo "Installing config node"
	mkdir -p $INSTALLPATH/configdb
	mongod --quiet --configsvr --dbpath $INSTALLPATH/configdb --replSet rsconfig --fork --port ${PORT_PREFIX}21 --pidfilepath $INSTALLPATH/configdb/mongod-con.lock --logpath=$INSTALLPATH/log/con.log
	mongosh --quiet --port ${PORT_PREFIX}21 --eval 'rs.initiate({"_id": "rsconfig", configsvr: true, members: [{ "_id": 0, "host": "localhost:'${PORT_PREFIX}'21" }]});'

	echo "Installing data node 1"
	mkdir -p $INSTALLPATH/data1
	mongod --quiet --shardsvr --dbpath $INSTALLPATH/data1 --replSet rs1 --port ${PORT_PREFIX}18 --logpath=$INSTALLPATH/log/mongod1.log --fork

	echo "Configure data node 1"
	mongosh --quiet --port ${PORT_PREFIX}18 --eval 'rs.initiate({});'

	echo "Installing data node 2"
	mkdir -p $INSTALLPATH/data2
	mongod --quiet --shardsvr --dbpath $INSTALLPATH/data2 --replSet rs2 --port ${PORT_PREFIX}19 --logpath=$INSTALLPATH/log/mongod2.log --fork

	echo "Configure data node 2"
	mongosh --quiet --port ${PORT_PREFIX}19 --eval 'rs.initiate({});'


	echo "Installing mongos node"
	mongos --quiet --configdb rsconfig/localhost:${PORT_PREFIX}21 --port ${PORT_PREFIX}17 --logpath=$INSTALLPATH/log/mongos.log --fork

	echo "Configured data nodes as shards"
	mongosh --quiet --port ${PORT_PREFIX}17 admin --eval 'sh.addShard( "rs1/localhost:'${PORT_PREFIX}'18" );'
	mongosh --quiet --port ${PORT_PREFIX}17 admin --eval 'sh.addShard( "rs2/localhost:'${PORT_PREFIX}'19" );'
	echo
	echo "Installation finished. You can now login with 'mongosh --port ${PORT_PREFIX}17'"

else
	echo "Full installation required"
	echo "Installing log directory"
	mkdir -p $INSTALLPATH/log
	echo "Installing config node"
	mkdir -p $INSTALLPATH/configdb1
	mkdir -p $INSTALLPATH/configdb2
	mkdir -p $INSTALLPATH/configdb3
	echo "Deploy 3 config servers"
	echo "Deploy config server 1"
	mongod --quiet --configsvr --dbpath $INSTALLPATH/configdb1 --replSet rsconfig --fork --port ${PORT_PREFIX}21 --pidfilepath $INSTALLPATH/configdb1/mongod1-con.lock --logpath=$INSTALLPATH/log/con1.log
	echo "Deploy config server 2"
	mongod --quiet --configsvr --dbpath $INSTALLPATH/configdb2 --replSet rsconfig --fork --port ${PORT_PREFIX}22 --pidfilepath $INSTALLPATH/configdb2/mongod2-con.lock --logpath=$INSTALLPATH/log/con2.log
	echo "Deploy config server 3"
	mongod --quiet --configsvr --dbpath $INSTALLPATH/configdb3 --replSet rsconfig --fork --port ${PORT_PREFIX}23 --pidfilepath $INSTALLPATH/configdb3/mongod3-con.lock --logpath=$INSTALLPATH/log/con3.log
	echo "Configure config servers"
	mongosh --quiet --port ${PORT_PREFIX}21 --eval 'rs.initiate({"_id": "rsconfig", configsvr: true, members: [{ "_id": 0, "host": "localhost:'${PORT_PREFIX}'21" },{ "_id": 1, "host": "localhost:'${PORT_PREFIX}'22" }, { "_id": 2, "host": "localhost:'${PORT_PREFIX}'23" }]});'

	echo 
	echo "Installing replica-set data node 1"
	mkdir -p $INSTALLPATH/data1a
	mkdir -p $INSTALLPATH/data1b
	mkdir -p $INSTALLPATH/data1c
	echo 
	echo "Deploy replica-set 1"
	echo "Deploy replica-set 1 server a"
	mongod --quiet --shardsvr --replSet "rs1" --dbpath $INSTALLPATH/data1a --port ${PORT_PREFIX}31 --logpath=$INSTALLPATH/log/mongod1a.log --fork
	echo "Deploy replica-set 1 server b"
	mongod --quiet --shardsvr --replSet "rs1" --dbpath $INSTALLPATH/data1b --port ${PORT_PREFIX}32 --logpath=$INSTALLPATH/log/mongod1b.log --fork
	echo "Deploy replica-set 1 server c"
	mongod --quiet --shardsvr --replSet "rs1" --dbpath $INSTALLPATH/data1c --port ${PORT_PREFIX}33 --logpath=$INSTALLPATH/log/mongod1c.log --fork
	echo "Configure replica-set 1"
	echo "Configure server a of replica-set 1"
	mongosh --quiet --port ${PORT_PREFIX}31 --eval 'rs.initiate({"_id": "rs1", members: [{ "_id": 0, "host": "localhost:'${PORT_PREFIX}'31" }]});'
	echo "Configure server b (secondary) of replica-set 1"
	mongosh --quiet --port ${PORT_PREFIX}31 --eval 'rs.add("localhost:'${PORT_PREFIX}'32");'
	echo "Configure server c (secondary) of replica-set 1"
	mongosh --quiet --port ${PORT_PREFIX}31 --eval 'rs.add("localhost:'${PORT_PREFIX}'33");'

	echo 
	echo "Installing replica-set data node 2"
	mkdir -p $INSTALLPATH/data2a
	mkdir -p $INSTALLPATH/data2b
	mkdir -p $INSTALLPATH/data2c
	echo 
	echo "Deploy replica-set 2"
	echo "Deploy replica-set 2 server a"
	mongod --quiet --shardsvr --replSet "rs2" --dbpath $INSTALLPATH/data2a --port ${PORT_PREFIX}41 --logpath=$INSTALLPATH/log/mongod2a.log --fork
	echo "Deploy replica-set 2 server b"
	mongod --quiet --shardsvr --replSet "rs2" --dbpath $INSTALLPATH/data2b --port ${PORT_PREFIX}42 --logpath=$INSTALLPATH/log/mongod2b.log --fork
	echo "Deploy replica-set 2 server c"
	mongod --quiet --shardsvr --replSet "rs2" --dbpath $INSTALLPATH/data2c --port ${PORT_PREFIX}43 --logpath=$INSTALLPATH/log/mongod2c.log --fork
	echo 
	echo "Configure replica-set 2"
	echo "Configure server a of replica-set 2"
	mongosh --quiet --port ${PORT_PREFIX}41 --eval 'rs.initiate({"_id": "rs2", members: [{ "_id": 0, "host": "localhost:'${PORT_PREFIX}'41" }]});'
	echo "Configure server b (secondary) of replica-set 2"
	mongosh --quiet --port ${PORT_PREFIX}41 --eval 'rs.add("localhost:'${PORT_PREFIX}'42");'
	echo "Configure server c (secondary) of replica-set 2"
	mongosh --quiet --port ${PORT_PREFIX}41 --eval 'rs.add("localhost:'${PORT_PREFIX}'43");'
	echo 
	echo "Installing mongos node"
	mongos --quiet --configdb rsconfig/localhost:${PORT_PREFIX}21,localhost:${PORT_PREFIX}22,localhost:${PORT_PREFIX}23 --port ${PORT_PREFIX}17 --logpath=$INSTALLPATH/log/mongos.log --fork
	echo 
	echo "Configure data nodes as shards"
	echo "add shard 1 to cluster"
	mongosh --quiet --port ${PORT_PREFIX}17 admin --eval 'sh.addShard("rs1/localhost:'${PORT_PREFIX}'31,localhost:'${PORT_PREFIX}'32,localhost:'${PORT_PREFIX}'33");'
	echo "add shard 2 to cluster"
	mongosh --quiet --port ${PORT_PREFIX}17 admin --eval 'sh.addShard("rs2/localhost:'${PORT_PREFIX}'41,localhost:'${PORT_PREFIX}'42,localhost:'${PORT_PREFIX}'43");'

	echo
	echo "Installation finished. You can now login with 'mongosh --port ${PORT_PREFIX}17'"
fi