#!/bin/bash
# Self-contained Dropbear SSH installer for MiladMk Panel.
# Installs dropbear from distro packages and configures it on the panel's
# configured port. Independent — no third-party repo.

if [ "$EUID" -ne 0 ]; then echo "Please run as root"; exit 1; fi

# Read the dropbear port the panel expects (falls back to 2083)
PORT_DROPBEAR="$(grep -E '^PORT_DROPBEAR=' /var/www/html/app/.env 2>/dev/null | cut -d'=' -f2)"
[ -z "$PORT_DROPBEAR" ] && PORT_DROPBEAR=2083

if command -v apt-get >/dev/null; then
  apt-get update -y
  apt-get install -y dropbear
elif command -v yum >/dev/null; then
  yum install -y dropbear
fi

# Configure dropbear
if [ -f /etc/default/dropbear ]; then
  sed -i 's/^NO_START=.*/NO_START=0/' /etc/default/dropbear
  if grep -q '^DROPBEAR_PORT=' /etc/default/dropbear; then
    sed -i "s/^DROPBEAR_PORT=.*/DROPBEAR_PORT=$PORT_DROPBEAR/" /etc/default/dropbear
  else
    echo "DROPBEAR_PORT=$PORT_DROPBEAR" >> /etc/default/dropbear
  fi
fi

# Allow /bin/false and nologin shells for tunnel-only users
grep -qx '/bin/false' /etc/shells || echo '/bin/false' >> /etc/shells
grep -qx '/usr/sbin/nologin' /etc/shells || echo '/usr/sbin/nologin' >> /etc/shells

systemctl enable dropbear 2>/dev/null || true
systemctl restart dropbear 2>/dev/null || service dropbear restart 2>/dev/null || true

echo "Dropbear installed and listening on port $PORT_DROPBEAR."
