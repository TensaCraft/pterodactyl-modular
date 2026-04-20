#!/bin/ash
set -e

. /app/.docker/panel/lib.sh

ensure_runtime_env
wait_for_database
wait_for_redis
wait_for_panel_ready

exec php artisan queue:work --queue=high,standard,low --sleep=3 --tries=3
