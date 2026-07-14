#!/bin/bash
# Standalone helper: allow php-fpm to write /etc/passwd, /etc/shadow, etc.
# Run this once on an already-installed server if user create/delete fails with
# a permission error. Safe to run multiple times.
if [ "$EUID" -ne 0 ]; then echo "Please run as root"; exit 1; fi

# Detect the active php-fpm version
PHPVERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)
if [ -z "$PHPVERSION" ]; then
  # fallback: find any installed php*-fpm service
  PHPVERSION=$(systemctl list-unit-files 2>/dev/null | grep -oE 'php[0-9]+\.[0-9]+-fpm' | head -1 | sed 's/php//;s/-fpm//')
fi
if [ -z "$PHPVERSION" ]; then echo "Could not detect PHP version"; exit 1; fi

FPM_SVC="php${PHPVERSION}-fpm"
OVERRIDE_DIR="/etc/systemd/system/${FPM_SVC}.service.d"
mkdir -p "$OVERRIDE_DIR"
tee "${OVERRIDE_DIR}/override.conf" >/dev/null <<'FPMEOF'
[Service]
ProtectSystem=false
ProtectHome=false
ReadWritePaths=/etc /etc/passwd /etc/group /etc/shadow /etc/gshadow
FPMEOF

systemctl daemon-reload
systemctl restart "${FPM_SVC}"
systemctl restart nginx 2>/dev/null || true
echo "Applied php-fpm override for ${FPM_SVC}. Done."
