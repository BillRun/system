#!/bin/sh
set -e

# Run install/migration scripts synchronously against a throwaway,
# localhost-only mongod, then hand off to the real mongod. This avoids
# racing our own scripts against docker-entrypoint.sh's background
# initialization of the real listener.

if command -v mongosh >/dev/null 2>&1; then
    MONGOC=mongosh
else
    MONGOC=mongo
fi

FIRST_RUN=0
[ -z "$(ls -A /data/db 2>/dev/null)" ] && FIRST_RUN=1

mongod --fork --dbpath /data/db --logpath /tmp/mongod-init.log --bind_ip localhost

if [ "$FIRST_RUN" = "1" ]; then
    sh /docker-entrypoint-initdb.d/init-mongo.sh
fi

$MONGOC billing_container /billrun/mongo/migration/script.js

for f in /plugin/mongo/migration/*.js
do
    [ -f "$f" ] || break
    $MONGOC billing_container "$f"
done

mongod --dbpath /data/db --shutdown

# Overriding the image's entrypoint drops its default CMD, so "mongod" must
# be supplied here explicitly rather than relying on "$@". By now /data/db is
# populated, so docker-entrypoint.sh's own initdb phase is a no-op and it
# execs straight into the real mongod, inheriting our PID for clean signal
# handling.
exec docker-entrypoint.sh mongod "$@"
