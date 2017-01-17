#!/bin/bash
### Script to create billrun tenant db

# Create first user
mongo admin -u<USER> -p<PASSWORD> --eval "db = db.getSiblingDB('$1'); db.createUser({user: '$2',pwd: '$3', roles: [{role: '$4', db: '$1'}]})"

# Create basic collections
mongo $1 /var/www/billrun/mongo/create.ini -u$2 -p$3

# Shard important big collections
mongo admin --ssl --sslAllowInvalidHostnames -u<USER> -p<PASSWORD> --eval "sh.enableSharding('$1');"
mongo admin --ssl --sslAllowInvalidHostnames -u<USER> -p<PASSWORD> --eval "sh.shardCollection('$1.lines',  { 'stamp' : 1 } );"
mongo admin --ssl --sslAllowInvalidHostnames -u<USER> -p<PASSWORD> --eval "sh.shardCollection('$1.queue',  { 'stamp' : 1 } );"