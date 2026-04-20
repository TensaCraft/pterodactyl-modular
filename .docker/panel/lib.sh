#!/bin/ash
set -e

generate_random_string() {
  length="$1"
  charset="$2"

  LC_ALL=C tr -dc "$charset" </dev/urandom | head -c "$length"
}

ensure_required_runtime_keys() {
  env_file="$1"

  if grep -q '^APP_KEY=' "$env_file"; then
    echo "APP_KEY exists in env file, preserving that."
  else
    if [ -n "${APP_KEY:-}" ]; then
      echo "APP_KEY exists in environment, using that."
    else
      echo "Generating key."
      APP_KEY="$(generate_random_string 32 'a-zA-Z0-9')"
      echo "Generated app key: $APP_KEY"
    fi

    printf 'APP_KEY=%s\n' "$APP_KEY" >> "$env_file"
  fi

  if grep -q '^HASHIDS_SALT=' "$env_file"; then
    echo "HASHIDS_SALT exists in env file, preserving that."
  else
    if [ -n "${HASHIDS_SALT:-}" ]; then
      echo "HASHIDS_SALT exists in environment, using that."
    else
      echo "Generating hashids salt."
      HASHIDS_SALT="$(generate_random_string 20 'a-zA-Z0-9!@#$%^&*()_+?><~')"
      echo "Generated hashids salt: $HASHIDS_SALT"
    fi

    printf 'HASHIDS_SALT=%s\n' "$HASHIDS_SALT" >> "$env_file"
  fi
}

with_runtime_env_lock() {
  while ! mkdir /app/var/.env.lock 2>/dev/null; do
    sleep 1
  done

  trap 'rmdir /app/var/.env.lock 2>/dev/null || true' EXIT
}

ensure_runtime_env_file() {
  mkdir -p /app/var

  if [ -f /app/.env ] && [ ! -L /app/.env ]; then
    echo "project .env exists, preserving bind-mounted env file."
    ensure_required_runtime_keys /app/.env
    return 0
  fi

  with_runtime_env_lock

  if [ -f /app/var/.env ]; then
    echo "external vars exist."
    rm -f /app/.env
    ensure_required_runtime_keys /app/var/.env
    ln -s /app/var/.env /app/.env
    rmdir /app/var/.env.lock 2>/dev/null || true
    trap - EXIT
    return 0
  fi

  echo "external vars don't exist."
  rm -f /app/.env
  : > /app/var/.env
  ensure_required_runtime_keys /app/var/.env
  ln -s /app/var/.env /app/.env
  rmdir /app/var/.env.lock 2>/dev/null || true
  trap - EXIT
}

ensure_default_db_port() {
  if [ -z "${DB_PORT:-}" ]; then
    echo "DB_PORT not specified, defaulting to 3306"
    DB_PORT=3306
  fi
}

ensure_default_redis_port() {
  if [ -z "${REDIS_PORT:-}" ]; then
    echo "REDIS_PORT not specified, defaulting to 6379"
    REDIS_PORT=6379
  fi
}

ensure_log_permissions() {
  echo "Checking log folder permissions."
  mkdir -p /var/log/panel /var/log/supervisord /var/log/nginx /var/log/php7 /app/storage/logs
  rm -rf /var/log/panel/logs
  ln -s /app/storage/logs /var/log/panel/logs

  if [ "$(stat -c '%U:%G' /app/storage/logs)" != "nginx:nginx" ]; then
    echo "Fixing log folder permissions."
    chown -R nginx:nginx /app/storage/logs
  fi
}

ensure_runtime_write_permissions() {
  echo "Normalizing runtime write permissions."

  for path in \
    /app/storage \
    /app/bootstrap/cache \
    /app/storage/app/modular
  do
    if [ -e "$path" ]; then
      chmod -R a+rwX "$path" 2>/dev/null || true
    fi
  done
}

clear_bootstrap_cache_files() {
  echo "Clearing cached Laravel bootstrap metadata."
  rm -f /app/bootstrap/cache/*.php
}

ensure_public_storage_link() {
  if [ -L /app/public/storage ]; then
    echo "Public storage symlink already exists."
    return 0
  fi

  if [ -e /app/public/storage ]; then
    echo "Public storage path already exists and is not a symlink, leaving it unchanged."
    return 0
  fi

  echo "Ensuring public storage symlink exists."
  php artisan storage:link --relative >/dev/null 2>&1 || php artisan storage:link >/dev/null 2>&1 || true
}

sync_snapshot_directory() {
  snapshot="$1"
  target="$2"
  label="$3"
  source_stamp_file="$snapshot/.modular-image-stamp"
  target_stamp_file="$target/.modular-image-stamp"
  source_stamp=""
  target_stamp=""

  mkdir -p "$target"

  if [ -f "$source_stamp_file" ]; then
    source_stamp="$(cat "$source_stamp_file")"
  fi

  if [ -f "$target_stamp_file" ]; then
    target_stamp="$(cat "$target_stamp_file")"
  fi

  if [ -n "$source_stamp" ] && [ "$source_stamp" = "$target_stamp" ]; then
    echo "$label directory already matches the current image snapshot."
    return 0
  fi

  if [ -n "$(ls -A "$target" 2>/dev/null)" ]; then
    echo "$label directory is stale or missing a snapshot marker, refreshing from the image."
  else
    echo "$label directory is empty, syncing image snapshot."
  fi

  find "$target" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
  cp -a "$snapshot"/. "$target"/

  if [ -n "$source_stamp" ]; then
    printf '%s\n' "$source_stamp" > "$target_stamp_file"
  fi
}

with_runtime_artifact_lock() {
  mkdir -p /app/var

  while ! mkdir /app/var/.runtime-artifacts.lock 2>/dev/null; do
    sleep 1
  done

  trap 'rmdir /app/var/.runtime-artifacts.lock 2>/dev/null || true' EXIT
}

wait_for_database() {
  if [ -z "${DB_HOST:-}" ]; then
    echo "DB_HOST not specified, skipping database wait."
    return 0
  fi

  echo "Checking database status."
  until nc -z -w30 "$DB_HOST" "$DB_PORT" >/dev/null 2>&1; do
    echo "Waiting for database connection..."
    sleep 1
  done
}

uses_redis() {
  [ "${CACHE_DRIVER:-}" = "redis" ] \
    || [ "${SESSION_DRIVER:-}" = "redis" ] \
    || [ "${QUEUE_CONNECTION:-${QUEUE_DRIVER:-}}" = "redis" ]
}

wait_for_redis() {
  if ! uses_redis; then
    echo "Redis is not configured as an active backend, skipping cache wait."
    return 0
  fi

  if [ -z "${REDIS_HOST:-}" ]; then
    echo "REDIS_HOST not specified, skipping Redis wait."
    return 0
  fi

  echo "Checking Redis status."
  until nc -z -w30 "$REDIS_HOST" "$REDIS_PORT" >/dev/null 2>&1; do
    echo "Waiting for Redis connection..."
    sleep 1
  done
}

wait_for_panel_ready() {
  if [ "${MODULAR_WAIT_FOR_PANEL_READY:-false}" != "true" ]; then
    return 0
  fi

  echo "Waiting for panel readiness marker."
  until [ ! -f /app/var/panel-starting ] && [ -f /app/var/panel-ready ]; do
    sleep 1
  done
}

sync_runtime_artifacts() {
  echo "Syncing vendor, node modules, and frontend artifacts from image snapshots."

  with_runtime_artifact_lock
  sync_snapshot_directory /opt/pterodactyl/vendor /app/vendor "Vendor"
  sync_snapshot_directory /opt/pterodactyl/node-modules /app/node_modules "Node modules"
  sync_snapshot_directory /opt/pterodactyl/public-assets /app/public/assets "Frontend assets"
  rmdir /app/var/.runtime-artifacts.lock 2>/dev/null || true
  trap - EXIT
}

ensure_runtime_env() {
  ensure_runtime_env_file
  ensure_default_db_port
  ensure_default_redis_port
}
