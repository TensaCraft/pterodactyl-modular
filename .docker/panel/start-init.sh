#!/bin/ash
set -e
umask 000

. /app/.docker/panel/lib.sh

ensure_runtime_env
wait_for_database
wait_for_redis
ensure_runtime_write_permissions
clear_bootstrap_cache_files
ensure_public_storage_link

if [ "${INIT_RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "Running database migrations."
  php artisan migrate --force
else
  echo "Skipping database migrations."
fi

if [ "${INIT_REBUILD_MODULE_REGISTRY:-true}" = "true" ]; then
  echo "Rebuilding modular frontend registry."
  php artisan modular:rebuild-registry
else
  echo "Skipping modular registry rebuild."
fi

if [ "${INIT_SETUP_LOCAL_WINGS:-false}" = "true" ] && [ -f /app/.docker/local/setup_local_wings.php ]; then
  echo "Configuring bundled local Wings runtime."
  php /app/.docker/local/setup_local_wings.php
fi

if [ "${INIT_SYNC_FRONTEND:-false}" = "true" ]; then
  echo "Synchronizing modular frontend assets."
  php artisan modular:sync-frontend --build
fi

echo "Initialization completed."
