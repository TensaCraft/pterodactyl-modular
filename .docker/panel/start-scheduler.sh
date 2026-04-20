#!/bin/ash
set -e

. /app/.docker/panel/lib.sh

ensure_runtime_env
wait_for_database
wait_for_redis
wait_for_panel_ready

while true; do
  php artisan schedule:run --no-interaction --verbose
  sleep 60
done
