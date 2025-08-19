#!/usr/bin/env bash
# Rollback Apache deployment for newbvote2025s
# Usage: sudo bash rollback-apache.sh
set -euo pipefail

DOMAIN=${DOMAIN:-"newbvote2025s.online"}
APACHE_DIR_DEBIAN=${APACHE_DIR_DEBIAN:-"/etc/apache2"}
APACHE_DIR_RHEL=${APACHE_DIR_RHEL:-"/etc/httpd"}
SERVICE_NAME=${SERVICE_NAME:-"ratchet-newbvote2025s.service"}

if [[ $EUID -ne 0 ]]; then
  echo "[ERROR] Please run as root (use sudo)." >&2
  exit 1
fi

IS_DEBIAN=0
IS_RHEL=0
if command -v apt-get >/dev/null 2>&1; then IS_DEBIAN=1; fi
if command -v yum >/dev/null 2>&1 || command -v dnf >/dev/null 2>&1; then IS_RHEL=1; fi

# Stop and disable service
if systemctl list-unit-files | grep -q "^${SERVICE_NAME}"; then
  systemctl disable --now "${SERVICE_NAME}" || true
  rm -f "/etc/systemd/system/${SERVICE_NAME}"
  systemctl daemon-reload || true
fi

if [[ ${IS_DEBIAN} -eq 1 ]]; then
  # Disable sites
  a2dissite newbvote2025s-80.conf || true
  a2dissite newbvote2025s-443.conf || true
  # Remove vhosts
  rm -f "${APACHE_DIR_DEBIAN}/sites-available/newbvote2025s-80.conf"
  rm -f "${APACHE_DIR_DEBIAN}/sites-available/newbvote2025s-443.conf"
  apache2ctl configtest || true
  systemctl reload apache2 || true
elif [[ ${IS_RHEL} -eq 1 ]]; then
  # Remove vhosts under conf.d
  rm -f "${APACHE_DIR_RHEL}/conf.d/newbvote2025s-80.conf"
  rm -f "${APACHE_DIR_RHEL}/conf.d/newbvote2025s-443.conf"
  httpd -t || true
  systemctl reload httpd || true
fi

echo "[OK] Rollback finished."
