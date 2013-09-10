sudo screen php public/index.php --receive
sudo screen php public/index.php --calc --type=ilds
sudo screen php public/index.php --aggregate --type=ilds --stamp=`date +%Y%m%d`
sudo screen php public/index.php --generate --type=ilds --stamp=`date +%Y%m%d`