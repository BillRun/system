#!/bin/bash

php_ver=$1
light=$2
export PLUGIN_PATH=$3
include_tests=$4

export USER_ID=$(id -u) ;
export GROUP_ID=$(id -g) ;

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

if [ -n "$PLUGIN_PATH" ]; then
    export PLUGIN_BASENAME=$(basename "$PLUGIN_PATH")
else
    export PLUGIN_BASENAME=default
fi

if [ -n "$include_tests" ]; then
    tests="-tests"
else
    tests=""
fi

cd `dirname "$0"`


DEBUG_LOG_DIR=../../logs/container
mkdir ${DEBUG_LOG_DIR} -p
touch ${DEBUG_LOG_DIR}/debug.log && chmod 666 ${DEBUG_LOG_DIR}/debug.log

docker-compose -f docker-compose-php$php_ver$light$tests.yml $( [ -f ${PLUGIN_PATH}/docker/billrun-docker/plugin.yml ] && echo "-f ${PLUGIN_PATH}/docker/billrun-docker/plugin.yml" ) up --build