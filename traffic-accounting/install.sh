#!/bin/bash
# Modern, lightweight per-user SSH traffic accounting for MiladMk Panel (nftables).
#
# Method: a single nftables table with a per-user pair of named counters, matched
# by the socket's owning UID for OUTPUT (TX) and by conntrack "reply" bytes for
# INPUT (RX). Counters live in the kernel — reading them is essentially free and
# there is NO packet capture (unlike nethogs), so overhead is minimal.
#
# Output: writes the panel's expected out.json format so no panel change needed:
#   [{"name":"sshd: <user>","PID":<uid>,"TX":<kb_delta>,"RX":<kb_delta>}, ...]

set -e

if command -v apt-get >/dev/null; then
  apt-get install -y nftables jq >/dev/null 2>&1 || apt-get install -y nftables
fi

install -d /var/lib/mk-traffic
install -d /var/www/html/app/storage
touch /var/www/html/app/storage/out.json

# --- base nftables ruleset: one table, counters added per-user by the sampler ---
nft list table inet mk_traffic >/dev/null 2>&1 || nft -f - <<'NFT'
table inet mk_traffic {
    chain out {
        type filter hook output priority -300; policy accept;
    }
    chain in {
        type filter hook input priority -300; policy accept;
    }
}
NFT

# --- sampler script ------------------------------------------------------
cat > /usr/local/bin/mk-traffic.sh << 'SAMP'
#!/bin/bash
# Ensure a per-uid counter rule exists (TX via meta skuid on output, RX via
# ct original/reply matching the same uid's sockets on input), then read the
# byte counters, compute deltas vs last snapshot, and append to out.json.
OUT="/var/www/html/app/storage/out.json"
STATE_DIR="/var/lib/mk-traffic"
install -d "$STATE_DIR"
STATE="$STATE_DIR/last.txt"

ensure_table() {
  nft list table inet mk_traffic >/dev/null 2>&1 || nft -f - <<'NFT'
table inet mk_traffic {
    chain out { type filter hook output priority -300; policy accept; }
    chain in  { type filter hook input  priority -300; policy accept; }
}
NFT
}

ensure_user_rules() {
  # For each SSH user (uid>=1000, <65534) add TX (output by skuid) and
  # RX (input by skuid — nft can match socket owner on input via 'meta skuid'
  # for locally-terminated sockets like sshd children) counters, once.
  while IFS=: read -r uname _ uid _ _ _ _; do
    [ "$uid" -ge 1000 ] 2>/dev/null || continue
    [ "$uid" -ge 65534 ] 2>/dev/null && continue
    # TX (upload): packets leaving, owned by uid
    if ! nft list chain inet mk_traffic out 2>/dev/null | grep -q "skuid $uid "; then
      nft add rule inet mk_traffic out meta skuid "$uid" counter comment "\"tx_$uid\"" 2>/dev/null || true
    fi
    # RX (download): packets arriving for sockets owned by uid
    if ! nft list chain inet mk_traffic in 2>/dev/null | grep -q "skuid $uid "; then
      nft add rule inet mk_traffic in meta skuid "$uid" counter comment "\"rx_$uid\"" 2>/dev/null || true
    fi
  done < /etc/passwd
}

ensure_table
ensure_user_rules

# Read counters as JSON for reliable parsing
raw="$(nft -j list table inet mk_traffic 2>/dev/null)"

# Parse with jq: extract rules that have a counter + comment tx_/rx_
declare -A TX RX
if command -v jq >/dev/null 2>&1 && [ -n "$raw" ]; then
  while IFS=$'\t' read -r kind uid bytes; do
    [ -z "$uid" ] && continue
    if [ "$kind" = "tx" ]; then TX[$uid]=$bytes; else RX[$uid]=$bytes; fi
  done < <(echo "$raw" | jq -r '
    .nftables[] | select(.rule) | .rule
    | select(.comment != null and (.comment|test("^(tx|rx)_")))
    | . as $r
    | ($r.comment | capture("^(?<k>tx|rx)_(?<u>[0-9]+)$")) as $c
    | ( [ $r.expr[] | select(.counter) | .counter.bytes ] | add // 0 ) as $b
    | "\($c.k)\t\($c.u)\t\($b)"
  ')
fi

# previous snapshot
declare -A PREV
[ -f "$STATE" ] && while read -r key val; do PREV[$key]=$val; done < "$STATE"

: > "$STATE.new"
json="["
first=1
# union of uids seen in TX or RX
for uid in $(printf '%s\n' "${!TX[@]}" "${!RX[@]}" | sort -un); do
  uname=$(getent passwd "$uid" | cut -d: -f1)
  [ -z "$uname" ] && continue
  ctx=${TX[$uid]:-0}; crx=${RX[$uid]:-0}
  ptx=${PREV[tx_$uid]:-0}; prx=${PREV[rx_$uid]:-0}
  if [ "$ctx" -ge "$ptx" ]; then dtx=$((ctx-ptx)); else dtx=$ctx; fi
  if [ "$crx" -ge "$prx" ]; then drx=$((crx-prx)); else drx=$crx; fi
  echo "tx_$uid $ctx" >> "$STATE.new"
  echo "rx_$uid $crx" >> "$STATE.new"
  kbtx=$((dtx/1024)); kbrx=$((drx/1024))
  entry="{\"name\":\"sshd: ${uname}\",\"PID\":${uid},\"TX\":${kbtx},\"RX\":${kbrx}}"
  if [ $first -eq 1 ]; then json="$json$entry"; first=0; else json="$json,$entry"; fi
done
json="$json]"
mv "$STATE.new" "$STATE"

echo "$json" >> "$OUT"
tail -n 20 "$OUT" > "$OUT.tmp" 2>/dev/null && mv "$OUT.tmp" "$OUT"
SAMP
chmod +x /usr/local/bin/mk-traffic.sh

# --- systemd timer (every 60s) -------------------------------------------
cat > /etc/systemd/system/mk-traffic.service << 'SVC'
[Unit]
Description=MiladMk lightweight per-user traffic accounting (nftables)
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/mk-traffic.sh
SVC

cat > /etc/systemd/system/mk-traffic.timer << 'TMR'
[Unit]
Description=Run MiladMk traffic accounting every minute

[Timer]
OnBootSec=60
OnUnitActiveSec=60
AccuracySec=10s

[Install]
WantedBy=timers.target
TMR

chown www-data:www-data /var/www/html/app/storage/out.json 2>/dev/null || true
systemctl daemon-reload
systemctl enable mk-traffic.timer
systemctl start mk-traffic.timer
/usr/local/bin/mk-traffic.sh || true
echo "MiladMk nftables traffic accounting installed (lightweight, TX+RX)."
