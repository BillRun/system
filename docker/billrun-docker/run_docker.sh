#!/bin/bash

DEBUG_LOG_DIR=../../logs/container
mkdir ${DEBUG_LOG_DIR} -p
touch ${DEBUG_LOG_DIR}/debug.log && chmod 666 ${DEBUG_LOG_DIR}/debug.log

docker-compose -f docker-compose-php74.yml build
docker-compose -f docker-compose-php74.yml up -d
