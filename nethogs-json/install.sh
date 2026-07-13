#!/bin/bash
# Self-contained nethogs-JSON installer for MiladMk Panel.
# Installs stock upstream nethogs (GPLv2) and a small wrapper that writes the
# per-process traffic as JSON to /var/www/html/app/storage/out.json, which the
# panel's traffic engine reads. Fully independent — no third-party repo.

set -e

# 1) Dependencies + nethogs (from distro packages; stock upstream nethogs)
if command -v apt-get >/dev/null; then
  apt-get update -y
  apt-get install -y nethogs jq
elif command -v yum >/dev/null; then
  yum install -y nethogs jq
fi

# Grant capabilities so nethogs can run and read process names
NETHOGS_BIN="$(command -v nethogs || echo /usr/sbin/nethogs)"
setcap "cap_net_admin,cap_net_raw,cap_dac_read_search,cap_sys_ptrace+pe" "$NETHOGS_BIN" 2>/dev/null || true

# 2) Install the JSON emitter script
install -d /usr/local/bin
cat > /usr/local/bin/nethogs-json.sh << 'EMITEOF'
#!/bin/bash
# Emit nethogs per-process traffic as a single JSON line into out.json.
# Uses stock nethogs tracemode (-t). Values are cumulative KB per process during
# the sample window; the panel converts KB -> MB.
OUT="/var/www/html/app/storage/out.json"
IFACE_ARG=""   # empty = all devices
SAMPLE=10      # seconds per sample

NETHOGS_BIN="$(command -v nethogs || echo /usr/sbin/nethogs)"

while true; do
  # -t tracemode, -d SAMPLE refresh, -c 1 => one snapshot then exit
  # tracemode lines look like:  program/PID/UID\tSENT_KB\tRECEIVED_KB
  snapshot="$("$NETHOGS_BIN" -t -d "$SAMPLE" -c 2 $IFACE_ARG 2>/dev/null)"

  # Build JSON array from the last block of lines
  json="["
  first=1
  while IFS= read -r line; do
    # skip empty / header / "Refreshing" lines
    [[ -z "$line" ]] && continue
    [[ "$line" == Refreshing* ]] && continue
    [[ "$line" == unknown* ]] && continue

    # split by whitespace/tabs: field1 = name/PID/UID, then SENT, then RECEIVED
    name_field="$(echo "$line" | awk '{print $1}')"
    sent="$(echo "$line" | awk '{print $2}')"
    recv="$(echo "$line" | awk '{print $3}')"

    # name_field = /path/or/name/PID/UID  -> extract PID (2nd-from-last) and name (rest)
    pid="$(echo "$name_field" | awk -F'/' '{print $(NF-1)}')"
    pname="$(echo "$name_field" | sed -E 's@/[0-9]+/[0-9]+$@@')"

    # validate numbers
    [[ "$sent" =~ ^[0-9.]+$ ]] || sent=0
    [[ "$recv" =~ ^[0-9.]+$ ]] || recv=0
    [[ "$pid"  =~ ^[0-9]+$   ]] || pid=0

    # escape name for JSON
    pname_esc="$(printf '%s' "$pname" | sed 's/\\/\\\\/g; s/"/\\"/g')"

    entry="{\"name\":\"$pname_esc\",\"PID\":$pid,\"TX\":$sent,\"RX\":$recv}"
    if [ $first -eq 1 ]; then json="$json$entry"; first=0; else json="$json,$entry"; fi
  done <<< "$snapshot"
  json="$json]"

  # Append as a new line (panel reads the last valid line)
  echo "$json" >> "$OUT"

  # Keep out.json from growing unbounded: keep only the last 50 lines
  tail -n 50 "$OUT" > "$OUT.tmp" 2>/dev/null && mv "$OUT.tmp" "$OUT"
done
EMITEOF
chmod +x /usr/local/bin/nethogs-json.sh

# 3) systemd service to keep the emitter running
cat > /etc/systemd/system/nethogs-json.service << 'SVCEOF'
[Unit]
Description=Nethogs JSON emitter for MiladMk Panel
After=network.target

[Service]
Type=simple
ExecStart=/usr/local/bin/nethogs-json.sh
Restart=always
RestartSec=5
User=root

[Install]
WantedBy=multi-user.target
SVCEOF

# ensure out.json exists and is writable by the panel
install -d /var/www/html/app/storage
touch /var/www/html/app/storage/out.json
chown www-data:www-data /var/www/html/app/storage/out.json 2>/dev/null || true

systemctl daemon-reload
systemctl enable nethogs-json.service
systemctl restart nethogs-json.service
echo "nethogs-json installed and running."
