#!/usr/bin/env bash
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Run as root: sudo bash install-vps.sh"
  exit 1
fi

apt-get update
apt-get install -y ca-certificates ufw

ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 3478/tcp
ufw allow 3478/udp
ufw allow 49160:49260/udp
ufw allow 50000:50100/udp

echo "Firewall prepared."
echo "Copy classroom-server and config.json to /opt/classroom-remote, then run the binary."
