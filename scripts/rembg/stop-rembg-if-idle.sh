#!/usr/bin/env bash
# Stop rembg container if heartbeat older than IDLE seconds
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
NAME=rembg
HEART="$ROOT/storage/tmp/rembg.heartbeat"
LOG="$ROOT/storage/logs/rembg.log"
IDLE=${1:-300}

mkdir -p "$(dirname "$LOG")"

ts() { date -u +"%Y-%m-%dT%H:%M:%SZ"; }

if [ -f "${HEART}" ]; then
  last=$(cat "${HEART}" 2>/dev/null || echo 0)
  now=$(date +%s)
  echo "$(ts) check idle: now=${now} last=${last} idle=${IDLE}" >> "$LOG" 2>&1 || true
  if (( now - last > IDLE )); then
    echo "$(ts) idle threshold exceeded, stopping rembg" >> "$LOG" 2>&1 || true
    docker stop "${NAME}" >/dev/null 2>&1 || true
    docker rm "${NAME}" >/dev/null 2>&1 || true
    rm -f "${HEART}"
    echo "$(ts) rembg stopped and heartbeat removed" >> "$LOG" 2>&1 || true
  fi
fi

exit 0
