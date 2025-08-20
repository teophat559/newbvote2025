#!/usr/bin/env bash
# One-command Apache deployment for newbvote2025s
# Usage (Ubuntu/Debian or RHEL-based):
#   curl -sSLO https://your-repo/raw/apache/deploy-apache.sh && sudo bash deploy-apache.sh
# Or copy this script to the server and run: sudo bash deploy-apache.sh
set -euo pipefail

# -------- CONFIG (edit if needed) --------
DOMAIN=${DOMAIN:-"newbvote2025s.online"}
WWW=${WWW:-"www.${DOMAIN}"}
PROJECT_ROOT=${PROJECT_ROOT:-"/home/newbvote2025s.online/public_html"}
LOG_DIR=${LOG_DIR:-"${PROJECT_ROOT}/logs"}
CERT_DIR=${CERT_DIR:-"/etc/letsencrypt/live/${DOMAIN}"}
APACHE_DIR_DEBIAN=${APACHE_DIR_DEBIAN:-"/etc/apache2"}
APACHE_DIR_RHEL=${APACHE_DIR_RHEL:-"/etc/httpd"}
SERVICE_NAME=${SERVICE_NAME:-"ratchet-newbvote2025s.service"}
# ----------------------------------------

if [[ $EUID -ne 0 ]]; then
  echo "[ERROR] Please run as root (use sudo)." >&2
  exit 1
fi

# Detect distro family (very simple)
IS_DEBIAN=0
IS_RHEL=0
if command -v apt-get >/dev/null 2>&1; then IS_DEBIAN=1; fi
if command -v yum >/dev/null 2>&1 || command -v dnf >/dev/null 2>&1; then IS_RHEL=1; fi

if [[ ${IS_DEBIAN} -eq 0 && ${IS_RHEL} -eq 0 ]]; then
  echo "[ERROR] Unsupported distro (need Debian/Ubuntu or RHEL/CentOS/Alma)." >&2
  exit 1
fi

# Determine repo path (assume this script resides under PROJECT_ROOT/apache)
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
REPO_ROOT=$(cd -- "${SCRIPT_DIR}/.." && pwd)

CONF80_SRC="${SCRIPT_DIR}/newbvote2025s-80.conf"
CONF443_SRC="${SCRIPT_DIR}/newbvote2025s-443.conf"
SERVICE_SRC="${SCRIPT_DIR}/${SERVICE_NAME}"
HOOK_SRC="${SCRIPT_DIR}/certbot-renew-hook.sh"

# Sanity check source files
for f in "${CONF80_SRC}" "${CONF443_SRC}" "${SERVICE_SRC}" "${HOOK_SRC}"; do
  [[ -f "$f" ]] || { echo "[ERROR] Missing file: $f" >&2; exit 1; }
done

# Ensure paths in confs match PROJECT_ROOT
sed -i "s#/home/newbvote2025s.online/public_html#${PROJECT_ROOT//\//\\/}#g" "${CONF80_SRC}"
sed -i "s#/home/newbvote2025s.online/public_html#${PROJECT_ROOT//\//\\/}#g" "${CONF443_SRC}"
sed -i "s#newbvote2025s.online#${DOMAIN}#g" "${CONF80_SRC}" "${CONF443_SRC}"

# Make sure log dir exists
mkdir -p "${LOG_DIR}"
chown -R ${SUDO_USER:-root}:${SUDO_USER:-root} "${LOG_DIR}" || true

# Install Certbot deploy hook
install -m 755 "${HOOK_SRC}" \
  /etc/letsencrypt/renewal-hooks/deploy/apache-reload.sh || true

if [[ ${IS_DEBIAN} -eq 1 ]]; then
  echo "[INFO] Detected Debian/Ubuntu. Setting up under ${APACHE_DIR_DEBIAN}"
  a2enmod rewrite headers expires ssl proxy proxy_http proxy_wstunnel
  install -m 644 "${CONF80_SRC}"  "${APACHE_DIR_DEBIAN}/sites-available/newbvote2025s-80.conf"
  install -m 644 "${CONF443_SRC}" "${APACHE_DIR_DEBIAN}/sites-available/newbvote2025s-443.conf"
  a2ensite newbvote2025s-80.conf
  a2ensite newbvote2025s-443.conf
  apache2ctl configtest
  systemctl reload apache2
elif [[ ${IS_RHEL} -eq 1 ]]; then
  echo "[INFO] Detected RHEL/CentOS/Alma. Setting up under ${APACHE_DIR_RHEL}"
  # Enable modules if needed (httpd uses LoadModule in conf.d; ensure packages installed)
  install -m 644 "${CONF80_SRC}"  "${APACHE_DIR_RHEL}/conf.d/newbvote2025s-80.conf"
  install -m 644 "${CONF443_SRC}" "${APACHE_DIR_RHEL}/conf.d/newbvote2025s-443.conf"
  # Replace Debian-specific log var with RHEL path
  sed -i 's#\${APACHE_LOG_DIR}#/var/log/httpd#g' "${APACHE_DIR_RHEL}/conf.d/newbvote2025s-80.conf" || true
  sed -i 's#\${APACHE_LOG_DIR}#/var/log/httpd#g' "${APACHE_DIR_RHEL}/conf.d/newbvote2025s-443.conf" || true
  # Ensure httpd is unmasked and running before reload
  systemctl unmask httpd || true
  systemctl enable --now httpd || true
  httpd -t
  systemctl reload httpd
fi

# Install Ratchet systemd service
install -m 644 "${SERVICE_SRC}" "/etc/systemd/system/${SERVICE_NAME}"
sed -i "s#/home/newbvote2025s.online/public_html#${PROJECT_ROOT//\//\\/}#g" \
  "/etc/systemd/system/${SERVICE_NAME}"
if [[ ${IS_RHEL} -eq 1 ]]; then
  # Switch service user to apache for RHEL-based systems
  sed -i 's/^User=www-data/User=apache/' "/etc/systemd/system/${SERVICE_NAME}" || true
  sed -i 's/^Group=www-data/Group=apache/' "/etc/systemd/system/${SERVICE_NAME}" || true
fi

systemctl daemon-reload
systemctl enable --now "${SERVICE_NAME}"

# Quick output
echo "================= SUMMARY ================="
echo "Domain           : ${DOMAIN}"
echo "Project root     : ${PROJECT_ROOT}"
echo "DocRoot (HTTPS)  : ${PROJECT_ROOT}/public"
echo "Certs            : ${CERT_DIR}"
echo "Apache (Debian)  : sites-available newbvote2025s-(80|443).conf"
echo "Apache (RHEL)    : conf.d/newbvote2025s-(80|443).conf"
echo "Service          : ${SERVICE_NAME} (enabled & started)"
echo "Logs (WebSocket) : ${LOG_DIR}/ws.stdout.log | ws.stderr.log"
echo "============================================"

echo "[OK] Deployment finished. Test: https://${DOMAIN}/"

# Warn if certs are missing on the target host
if [[ ! -s "${CERT_DIR}/fullchain.pem" || ! -s "${CERT_DIR}/privkey.pem" ]]; then
  echo "[WARN] Let's Encrypt certs not found or empty at ${CERT_DIR}."
  echo "       Ensure certificates exist before expecting HTTPS to work."
fi
