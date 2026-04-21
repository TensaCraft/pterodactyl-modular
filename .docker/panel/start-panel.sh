#!/bin/ash
set -e
umask 000

. /app/.docker/panel/lib.sh

configure_panel_nginx() {
  echo "Checking if https is required."
  if [ -f /etc/nginx/http.d/panel.conf ]; then
    echo "Using nginx config already in place."
    if [ -n "${LE_EMAIL:-}" ]; then
      echo "Checking for cert update"
      certbot certonly -d "$(echo "$APP_URL" | sed 's~http[s]*://~~g')" --standalone -m "$LE_EMAIL" --agree-tos -n
    else
      echo "No letsencrypt email is set"
    fi

    return 0
  fi

  echo "Checking if letsencrypt email is set."
  if [ -z "${LE_EMAIL:-}" ]; then
    echo "No letsencrypt email is set using http config."
    cp /app/.docker/panel/default.conf /etc/nginx/http.d/panel.conf
  else
    echo "writing ssl config"
    cp /app/.docker/panel/default_ssl.conf /etc/nginx/http.d/panel.conf
    echo "updating ssl config for domain"
    sed -i "s|<domain>|$(echo "$APP_URL" | sed 's~http[s]*://~~g')|g" /etc/nginx/http.d/panel.conf
    echo "generating certs"
    certbot certonly -d "$(echo "$APP_URL" | sed 's~http[s]*://~~g')" --standalone -m "$LE_EMAIL" --agree-tos -n
  fi

  echo "Removing the default nginx config"
  rm -f /etc/nginx/http.d/default.conf
}

ensure_runtime_env
configure_panel_nginx
wait_for_database
wait_for_redis

echo "Production-safe startup: skipping automatic migrations, seeding, and local Wings setup."
ensure_runtime_write_permissions
rm -f /app/var/panel-starting
touch /app/var/panel-ready

exec supervisord -n -c /etc/supervisord.conf
