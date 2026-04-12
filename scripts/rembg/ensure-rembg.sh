#!/usr/bin/env bash
# Ensure rembg container is running and update heartbeat file
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
NAME=rembg
IMAGE=danielgatis/rembg
PORT=5000
HEART="$ROOT/storage/tmp/rembg.heartbeat"
LOG="$ROOT/storage/logs/rembg.log"

mkdir -p "$(dirname "$HEART")"
mkdir -p "$(dirname "$LOG")"

ts() { date -u +"%Y-%m-%dT%H:%M:%SZ"; }

if ! docker ps --format '{{.Names}}' | grep -q "^${NAME}$"; then
  echo "$(ts) starting rembg container..." >> "$LOG" 2>&1 || true
  docker run -d --name ${NAME} --restart unless-stopped \
    --cpus="1.0" --memory="2g" -p ${PORT}:5000 \
    ${IMAGE} rembg serve --port 5000 >/dev/null 2>&1 || true
  # allow short time for server to initialize
  sleep 3
  echo "$(ts) rembg container start requested" >> "$LOG" 2>&1 || true
  # perform a lightweight pre-warm request to trigger first-run compilation
  PREWARM_TMP="/tmp/rembg_prewarm.png"
  # 1x1 transparent PNG (base64)
  cat > "$PREWARM_TMP" <<'PNG'
iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X9QsAAAAASUVORK5CYII=
PNG
  # send prewarm request (do not fail script if it times out)
  (curl -sS --max-time 120 -F "file=@${PREWARM_TMP}" "http://127.0.0.1:${PORT}/api/remove?model=u2net&alpha_matting=true&alpha_matting_foreground_threshold=240&alpha_matting_background_threshold=10&alpha_matting_erode_size=20" > /dev/null 2>&1 && echo "$(ts) rembg prewarm request ok (u2net+am)" >> "$LOG" 2>&1) || (echo "$(ts) rembg prewarm request timed out or failed (u2net+am)" >> "$LOG" 2>&1)
  rm -f "$PREWARM_TMP" || true
else
  echo "$(ts) rembg container already running" >> "$LOG" 2>&1 || true
fi

# update heartbeat timestamp
date +%s > "${HEART}"
echo "$(ts) heartbeat updated" >> "$LOG" 2>&1 || true

exit 0
