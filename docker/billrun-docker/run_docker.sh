#!/bin/bash

php_ver=$1
light=$2

if [ -n "$php_ver" ]; then
    echo "PHP version selected is" $php_ver
else
    php_ver=74
    echo "No input for PHP version; Selected default" $php_ver
fi

if [ -n "$light" ]; then
    light="-light"
else
    light=""
fi

cd `dirname "$0"`

DEBUG_LOG_DIR=../../logs/container
mkdir ${DEBUG_LOG_DIR} -p
touch ${DEBUG_LOG_DIR}/debug.log && chmod 666 ${DEBUG_LOG_DIR}/debug.log

docker-compose -f docker-compose-php$php_ver$light.yml build
docker-compose -f docker-compose-php$php_ver$light.yml up -d
