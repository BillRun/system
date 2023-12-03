// script to enable sharding on the billing collections

var _dbName = db.getName();
print('running sharding on db: ' + _dbName);
sh.enableSharding(_dbName);
sh.shardCollection(_dbName + ".lines", {"stamp": 1});
sh.shardCollection(_dbName + ".archive", {"stamp": 1});
sh.shardCollection(_dbName + ".rates", {"key": 1});
sh.shardCollection(_dbName + ".billrun", {"aid": "hashed", "billrun_key": "hashed"});
sh.shardCollection(_dbName + ".balances", {"aid": "hashed", "sid": "hashed"});
if (Number(db.version().charAt(0)) >= 6) {
    sh.shardCollection(_dbName + ".bills", {"aid": "hashed"});
}
sh.shardCollection(_dbName + ".audit", {"stamp": 1});
sh.shardCollection(_dbName + ".queue", {"stamp": 1});
//sh.shardCollection(_dbName + ".events", { "stamp" : 1 } );
sh.shardCollection(_dbName + ".subscribers", {"aid": "hashed", "sid": "hashed"});
//sh.shardCollection(_dbName + ".cards", { "batch_number":1, "serial_number":1 } );
//sh.shardCollection(_dbName + ".plans", { "name" : 1 } );
