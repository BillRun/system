#!/bin/sh
set -e

PERSIST_DIR=${BILLRUN_DATA_DIR:-/data}

# Persist BillRun runtime directories to a single host-mounted volume.
# For each $dir, /billrun/$dir becomes a symlink to $PERSIST_DIR/$dir.
# If the image ships skeleton content in /billrun/$dir on first boot, we
# copy it into the persistent location before replacing with the symlink.
link_persistent() {
    dir=$1
    src=/billrun/$dir
    dst=$PERSIST_DIR/$dir

    if [ ! -d "$dst" ]; then
        mkdir -p "$dst"
        if [ -d "$src" ] && [ ! -L "$src" ]; then
            cp -an "$src/." "$dst/" 2>/dev/null || true
        fi
    fi

    if [ ! -L "$src" ]; then
        rm -rf "$src"
        ln -sfn "$dst" "$src"
    fi
}

mkdir -p "$PERSIST_DIR"

for dir in shared files export workspace; do
    link_persistent "$dir"
done

chown -R www-data:www-data "$PERSIST_DIR" 2>/dev/null || true

# Render the nginx site config from the template so APPLICATION_MULTITENANT
# (and any future runtime-configurable param) can be supplied via env vars.
# Defaults preserve the previous baked-in behaviour (single-tenant).
: ${APPLICATION_MULTITENANT:=0}
NGINX_TEMPLATE=/etc/nginx/sites-available/default.template
NGINX_SITE=/etc/nginx/sites-available/default
if [ -f "$NGINX_TEMPLATE" ]; then
    APPLICATION_MULTITENANT="$APPLICATION_MULTITENANT" \
        envsubst '${APPLICATION_MULTITENANT}' \
        < "$NGINX_TEMPLATE" \
        > "$NGINX_SITE"
fi

if [ "${DB_INIT:-0}" = "1" ]; then
    # --dbinit runs on every container start when DB_INIT=1. This is intentional
    # and safe: every step inside DbinitModel::execute() is idempotent
    # (collections / seed records / migrations are skipped when already
    # present), so re-runs are near no-ops. Leaving DB_INIT=1 permanently set
    # means new migrations baked into a newer image version will auto-apply
    # on the next container restart.
    echo "Running --dbinit (DB_INIT=1)..."
    php /billrun/public/index.php --env container --dbinit
fi

# Select which supervisor programs run, based on BILLRUN_ROLE. The values feed
# the autostart=%(ENV_...)s expansions in billrun.conf
# Role selection is env-driven: no /etc/supervisor edits are needed in child images.
#   worker          -> only the billrun worker process
#   anything else   -> php-fpm + nginx (default/unset behaviour)
case "${BILLRUN_ROLE:-web}" in
    worker)
        export BILLRUN_WEB_ENABLED=false
        export BILLRUN_WORKER_ENABLED=true
        ;;
    *)
        export BILLRUN_WEB_ENABLED=true
        export BILLRUN_WORKER_ENABLED=false
        ;;
esac

# If we are starting supervisor, run it directly to avoid nested entrypoint conflicts
if [ "$1" = "/usr/bin/supervisord" ]; then
    exec "$@"
fi

# Fallback hand off for standard php commands
exec docker-php-entrypoint "$@"
