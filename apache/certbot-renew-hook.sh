#!/usr/bin/env bash
# Certbot deploy hook to reload Apache after certificate renewal
# Place at: /etc/letsencrypt/renewal-hooks/deploy/apache-reload.sh
# Make executable: chmod +x /etc/letsencrypt/renewal-hooks/deploy/apache-reload.sh

set -euo pipefail

log() { echo "[certbot-hook][$(date +'%F %T')] $*"; }

APACHECTL=$(command -v apache2ctl || true)
HTTPDCTL=$(command -v apachectl || true)

if [[ -n "${APACHECTL}" ]]; then
  log "Reloading apache2 ..."
  sudo "$APACHECTL" -k graceful || sudo systemctl reload apache2 || true
  log "Done."
elif [[ -n "${HTTPDCTL}" ]]; then
  log "Reloading httpd ..."
  sudo "$HTTPDCTL" -k graceful || sudo systemctl reload httpd || true
  log "Done."
else
  log "apachectl/apache2ctl not found. Skipping reload."
fi
