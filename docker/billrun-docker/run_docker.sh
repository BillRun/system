#!/bin/bash

ver=$1

if [ -n "$ver" ]; then
    echo "PHP version selected is" $ver
else
    ver=74
    echo "No input for PHP version; Selected default" $ver
fi

cd `dirname "$0"`

DEBUG_LOG_DIR=../../logs/container
mkdir ${DEBUG_LOG_DIR} -p
touch ${DEBUG_LOG_DIR}/debug.log && chmod 666 ${DEBUG_LOG_DIR}/debug.log

docker-compose -f docker-compose-php$ver.yml build
docker-compose -f docker-compose-php$ver.yml up -d
