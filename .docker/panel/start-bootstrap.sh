#!/bin/ash
set -e
umask 000

. /app/.docker/panel/lib.sh

wait_for_panel_ready

echo "Ensuring local modules are installed and enabled."
php /app/.docker/local/ensure_local_modules.php

WINGS_HOST="${MODULAR_WAIT_FOR_WINGS_HOST:-wings}"
WINGS_PORT="${MODULAR_WAIT_FOR_WINGS_PORT:-8080}"

echo "Waiting for Wings at ${WINGS_HOST}:${WINGS_PORT}."
until nc -z -w30 "${WINGS_HOST}" "${WINGS_PORT}" >/dev/null 2>&1; do
  sleep 1
done

echo "Seeding local demo admin and servers."
php /app/.docker/local/seed_local_demo_servers.php

echo "Synchronizing modular frontend assets."
php /app/artisan modular:sync-frontend --build

ensure_runtime_write_permissions
touch /app/var/local-bootstrap-ready
