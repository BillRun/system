#!/bin/bash

. ../conf/creds.sh

OLD_UPLOAD_FILE=/var/www/billrun/workspace/last_ftp_upload_time_file
CURRENT_UPLOAD_FILE=/var/www/billrun/workspace/ftp_upload_time_file

DEST_DIRECTORY="/data/tmp/International_calls/from_golan"

if [ -n $2 ]; then
	DEST_DIRECTORY=$2;
fi

touch $CURRENT_UPLOAD_FILE

if [ -f $OLD_UPLOAD_FILE ]; then  
	find $1 -type f -mmin -30 -mmin +0 -cnewer $OLD_UPLOAD_FILE   -exec bash -c 'FULL_PATH="{}"; FILE_NAME="`basename {}`" ; /var/www/billrun/scripts/sftp_upload.expct $FULL_PATH $FILE_NAME '$UPLOAD_FTP_PASS' '$DEST_DIRECTORY'' \;
else 
        find $1 -type f -mmin -30 -mmin +0  -exec bash -c 'FULL_PATH="{}"; FILE_NAME="`basename {}`" ; /var/www/billrun/scripts/sftp_upload.expct $FULL_PATH $FILE_NAME '$UPLOAD_FTP_PASS' '$DEST_DIRECTORY'' \;
fi
 
mv -f $CURRENT_UPLOAD_FILE $OLD_UPLOAD_FILE

