#!/bin/sh

if test -d "/plugin/application/plugins/"; then
     cd /plugin/application/plugins/
     for f in *.php
     do
          rm -f "/billrun/application/plugins/"$f
          ln -s /plugin/application/plugins/$f "/billrun/application/plugins/"$f
     done
     for f in /plugin/application/plugins/*.json
     do
          [ -f "$f" ] || break
          echo "configuration.include[] = $f" >> /billrun/conf/container.ini
     done
     for f in /plugin/conf/*.json
     do
          [ -f "$f" ] || break
          echo "configuration.include[] = $f" >> /billrun/conf/container.ini
     done
fi
if test -d "/plugin/application/views/"; then
     cd /plugin/application/views/
     for d in *
     do
          rm -rf "/billrun/application/views/"$d
          ln -s /plugin/application/views/$d "/billrun/application/views/"$d
     done
fi
if test -d "/plugin/conf/translations/overrides/"; then
    rm -rf /billrun/conf/translations/overrides/
    mkdir -p /billrun/conf/translations/overrides/
    cd /plugin/conf/translations/overrides/
    for f in *
     do
          ln -s /plugin/conf/translations/overrides/$f /billrun/conf/translations/overrides/$f
     done
fi 
ln -s /usr/local/bin/wkhtmltopdf /bin/wkhtmltopdf

exec "$@"