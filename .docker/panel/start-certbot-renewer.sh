#!/bin/ash
set -e

while true; do
  if [ -n "${LE_EMAIL:-}" ]; then
    echo "Running certificate renewal check."
    if ! certbot renew --nginx --quiet; then
      echo "Certificate renewal failed, retrying in one hour."
      sleep 3600
      continue
    fi
  else
    echo "LE_EMAIL not configured, skipping certificate renewal check."
  fi

  sleep 43200
done
