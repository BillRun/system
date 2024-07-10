#!/bin/bash

if test -d "/plugin/application/"; then
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
     fi
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
    unlink /billrun/conf/translations/overrides/
    mkdir -p /billrun/conf/translations/
    ln -s /plugin/conf/translations/overrides /billrun/conf/translations/overrides
fi 

cd /plugin/tests/
for f in {acceptance,all,api,functional,unit,bc}/plugin/*
do
    if test -e $f; then
         if ! test -e /billrun/tests/$f; then
            mkdir -p /billrun/tests/$(dirname "$f")
            ln -s /plugin/tests/$f /billrun/tests/$f
         fi
    fi
done

cd /billrun

ln -s /usr/local/bin/wkhtmltopdf /bin/wkhtmltopdf
mkdir -p /opt/wkhtmltox/bin/
ln -s /usr/local/bin/wkhtmltopdf /opt/wkhtmltox/bin/wkhtmltopdf
exec "$@"