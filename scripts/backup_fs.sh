#backup_fs.sh
#!/bin/bash
numberOfHosts=7;
hostname="`hostname`";
backupBase="/mnt/mongo_backup/backups";
syncFile=$backupBase"/sync.txt";
logFile="/tmp/backup.log";

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
  local init=$2;
  
  if [ -n "`grep \"$hostname:\" $syncFile`"  ]; then
    sed -i.bak s/$hostname:.*$/$hostname:$to/g $syncFile;
  else 
    if [ $init -eq 1  -a  -z "`grep $hostname $syncFile`" ]; then
      echo "$hostname:$to" >> $syncFile
    fi
  fi
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
  local state="`mongo --port 27018 --eval 'tojson(rs.isMaster());'`";
  
  if [ -n "`echo $state | grep '\"ismaster\" : false'`" ]; then 
    echo 1;
  fi
}

##### MAIN ####

backupDir=$backupBase"/`date +%Y%m%d`_`hostname`";
mongoDir="/ssd/mongo"

if [ -z "$(runningOnConf)" -a -z "$(isMongoSlave)" ]; then 
  echo "Mongo server is not a slave!!!" >> $logFile
  exit;
fi

$(updateSync stopping 1);
service mongod stop >> $logFile
$(updateSync stopped);

$(waitForServers)

$(updateSync backingup);
tar -vczf $backupDir".tar.gz" $mongoDir >> $logFile
$(updateSync backedup);

$(updateSync starting);
service mongod start >> $logFile
$(updateSync  done);

exit;

