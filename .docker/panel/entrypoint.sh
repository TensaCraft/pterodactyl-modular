#!/bin/ash
set -e

cd /app

. /app/.docker/panel/lib.sh

ensure_runtime_env
ensure_log_permissions
clear_bootstrap_cache_files
ensure_public_storage_link
sync_runtime_artifacts

if [ "${2:-}" = ".docker/panel/start-panel.sh" ] || [ "${1:-}" = ".docker/panel/start-panel.sh" ]; then
  mkdir -p /app/var
  rm -f /app/var/panel-ready
  : > /app/var/panel-starting
fi

exec "$@"
