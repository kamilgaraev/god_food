#!/usr/bin/env bash
set -euo pipefail
cd /opt/god-food-prod
umask 077
config_tmp=$(mktemp)
trap 'rm -f "$config_tmp"' EXIT
docker exec -w /var/www/html theobroma-prod-wordpress-1 php -r '
  require "wp-load.php";
  $s = (array) get_option("theobroma_commerce_settings", []);
  echo wp_json_encode([
    "url" => home_url("/"),
    "container" => "theobroma-prod-wordpress-1",
    "nginx_access_log" => "/var/log/nginx/access.log",
    "host" => $s["smtp_host"] ?? "",
    "port" => (int) ($s["smtp_port"] ?? 0),
    "username" => $s["smtp_username"] ?? "",
    "password" => $s["smtp_password"] ?? "",
    "from" => $s["smtp_from_address"] ?? "",
    "to" => get_option("admin_email"),
  ]);
' > "$config_tmp"
python3 - "$config_tmp" <<'PY'
import json, sys
data = json.load(open(sys.argv[1]))
required = ('url', 'host', 'port', 'username', 'password', 'from', 'to')
if any(not data.get(field) for field in required):
    raise SystemExit('SMTP monitor configuration is incomplete')
if not data['url'].startswith('https://theobroma.one/'):
    raise SystemExit('Unexpected production URL')
PY
install -m 0600 "$config_tmp" /etc/theobroma-monitor.json
install -m 0644 deploy/theobroma-monitor.service /etc/systemd/system/theobroma-monitor.service
install -m 0644 deploy/theobroma-monitor.timer /etc/systemd/system/theobroma-monitor.timer
install -d -m 0700 /var/lib/theobroma-monitor
systemctl daemon-reload
systemctl enable --now theobroma-monitor.timer
python3 scripts/monitor_site.py --config /etc/theobroma-monitor.json --state /var/lib/theobroma-monitor/state.json --self-test
systemctl start theobroma-monitor.service
systemctl is-active theobroma-monitor.timer
