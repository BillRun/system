#!/bin/sh

cd /plugin/application/plugins/
for f in *.php
do
     rm -f "/billrun/application/plugins/"$f
     ln -s /plugin/application/plugins/$f "/billrun/application/plugins/"$f
done
cd /plugin/application/views/
for d in *
do
     rm -rf "/billrun/application/views/"$d
     ln -s /plugin/application/views/$d "/billrun/application/views/"$d
done
if test -d "/plugin/conf/translations/overrides/"; then
    rm -rf /billrun/conf/translations/overrides/
    mkdir -p /billrun/conf/translations/overrides/
    cd /plugin/conf/translations/overrides/
    for f in *
     do
          ln -s /plugin/conf/translations/overrides/$f /billrun/conf/translations/overrides/$f
     done
fi 

exec "$@"