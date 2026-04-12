#!/usr/bin/env bash
# Install systemd unit and timer for rembg (needs root)
UNIT_SRC_DIR="$(cd "$(dirname "$0")" && pwd)"
UNIT_DST_DIR=/etc/systemd/system

if [ $(id -u) -ne 0 ]; then
  echo "Please run as root: sudo $0"
  exit 1
fi

cp "$UNIT_SRC_DIR/rembg.service" "$UNIT_DST_DIR/" || true
cp "$UNIT_SRC_DIR/rembg-stop.service" "$UNIT_DST_DIR/" || true
cp "$UNIT_SRC_DIR/rembg-stop.timer" "$UNIT_DST_DIR/" || true

systemctl daemon-reload
systemctl enable --now rembg-stop.timer || true
echo "Installed and enabled rembg-stop.timer. rembg service can be started via: systemctl start rembg.service" 
