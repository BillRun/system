#backup_fs.sh
#This is a backup sricpt that shold be placed in the slave of each replica to backup the DB.
#The result is a tar.gz file containing the  content of the  mongo database  driectory.
#!/bin/bash

numberOfHosts=8;
hostname="`hostname`";
backupBase="/mnt/mongo_backup/backups";
syncFile=$backupBase"/sync.txt";
lockFile=$backupBase"/lock.txt";
logFile="/tmp/backup.log";
mongoCmd="mongo --port 27018 admin -uadmin -pqsef1#2$"
mongoSrv="mongod"

backupFile=$backupBase"/`date +%Y%m%d`_`hostname`.tar.gz";
mongoDir="/ssd/mongo"

#load configuration first from /usr/local/etc/ and then overide it with the local directory configuration.
source /usr/local/etc/backup_fs.conf
source ./backup_fs.conf


function runningOnConf {
  if [ -n "`hostname | grep -e 'con0[0-9]'`" ]; then
    echo 1;
  fi
}

function getActiveHostsCount {
 echo `cat $syncFile | grep -v done | wc -l`;
}

function allhostActive {
  local hostCount=$1;

  if [ $(getActiveHostsCount) -ge $hostCount ]; then
    echo 1;
  fi
}

function hasSyncAt {
  local syncedState=$1;
  
  if [ -n "`grep $syncedState $syncFile`" ]; then
    echo 1;
  fi
}

function updateSync {
  local to=$1;
  
  if [ -n "`grep \"$hostname:\" $syncFile`"  ]; then
    flock $lockFile sed -i.bak s/$hostname:.*$/$hostname:$to/g $syncFile;
  else 
    if [ -z "`grep $hostname $syncFile`" ]; then
      flock $lockFile echo "$hostname:$to" >> $syncFile
    fi
  fi
  sleep $((RANDOM%5+1)); # allow other host to notice the change.
}

function waitForServers {
  while [  -z "$(allhostActive $numberOfHosts)" ]; do
    echo "not all host have started waiting... current active hosts : $(getActiveHostsCount)" >> $logFile
    sleep 1;
  done

  while [ -n "$(hasSyncAt stopping)" ]; do
    echo "another host is still syncing waiting for all the host to go down" >> $logFile
    sleep 1;
  done
}

function isMongoSlave {
  local state="`$mongoCmd --eval 'tojson(rs.isMaster());'`";
  
  if [ -n "`echo $state | grep '\"ismaster\" : false'`" ]; then 
    echo 1;
  fi
}

function shutdownMongo {
	#echo "Shutting down mongo" > STDOUT;
	local result="`service $mongoSrv status`";	
	if [ -n "`echo $result | grep 'stop'`" ]; then 
		echo 1;
	else
		local result="`$mongoCmd admin --eval 'db.shutdownServer();'`";
		echo $result >> $logFile;
		if [ -n "`echo $result | grep 'server should be down'`" ]; then 
			echo 1;
		fi
	fi
}

function startMongo {
	#echo "Starting mongo" > STDOUT;
	service $mongoSrv start >> $logFile
}

##### MAIN ####


if [ -z "$(runningOnConf)" -a -z "$(isMongoSlave)" ]; then 
  echo "Mongo server is not a slave!!!" >> $logFile
  exit;
fi

$(updateSync stopping);
if [ -n $(shutdownMongo) ]; then 
	$(updateSync stopped);

	$(waitForServers)

	$(updateSync backingup);
	tar -vczf $backupFile $mongoDir >> $logFile
	$(updateSync backedup);

	$(updateSync starting);
	$(startMongo);
	$(updateSync  done);
else 
	echo "Failed to stop the mongo server!" >> $logFile;
fi

exit;
