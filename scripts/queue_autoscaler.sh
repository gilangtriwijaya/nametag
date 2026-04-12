#!/usr/bin/env bash
set -euo pipefail

# Queue autoscaler script
# - Checks DB jobs count and starts transient 'php artisan queue:work --once' workers
# - Designed to be run periodically (systemd timer).

APP_DIR="/home/deploy/apps/nametag"
PHP_BIN="/usr/bin/php"
ARTISAN="$APP_DIR/artisan"
MAX_PARALLEL=3
TIMEOUT=300

cd "$APP_DIR" || exit 1

# Logging
LOG_DIR="$APP_DIR/storage/logs/autoscaler"
mkdir -p "$LOG_DIR"
AUTOSCALER_LOG="$LOG_DIR/queue-autoscaler.log"

# get number of unreserved AND ready jobs (available_at <= now)
JOBS=$($PHP_BIN $ARTISAN tinker --execute "echo (\Illuminate\Support\Facades\DB::table('jobs')->whereNull('reserved_at')->where('available_at','<=',time())->count());" 2>/dev/null || echo 0)
JOBS=$(echo "$JOBS" | tr -d '\r' | tail -n1)
JOBS=${JOBS:-0}

if [ "$JOBS" -le 0 ]; then
  echo "$(date -u +'%Y-%m-%dT%H:%M:%SZ') no ready jobs (jobs=$JOBS)" >> "$AUTOSCALER_LOG"
  exit 0
fi

# running transient workers (matching artisan queue:work --once)
RUNNING=$(pgrep -af "artisan queue:work --once" 2>/dev/null | wc -l || true)
RUNNING=${RUNNING:-0}

echo "$(date -u +'%Y-%m-%dT%H:%M:%SZ') ready_jobs=$JOBS running_workers=$RUNNING max_parallel=$MAX_PARALLEL" >> "$AUTOSCALER_LOG"

# determine how many to start
TO_START=$((JOBS - RUNNING))
if [ "$TO_START" -le 0 ]; then
  exit 0
fi
if [ "$TO_START" -gt "$MAX_PARALLEL" ]; then
  TO_START=$MAX_PARALLEL
fi

for i in $(seq 1 $TO_START); do
  UNIT_NAME="nametag-queue-worker-$(date +%s%N)"
  # Start a background worker and log the spawn attempt
  LOG_DIR="$APP_DIR/storage/logs/queue-workers"
  mkdir -p "$LOG_DIR"
  LOG_FILE="$LOG_DIR/${UNIT_NAME}.log"

  echo "$(date -u +'%Y-%m-%dT%H:%M:%SZ') spawning worker unit=$UNIT_NAME" >> "$AUTOSCALER_LOG"
  echo "command: env CACHE_STORE=file APP_ENV=production $PHP_BIN -d memory_limit=256M $ARTISAN queue:work --once --sleep=3 --tries=3 --timeout=$TIMEOUT" >> "$AUTOSCALER_LOG"

  env CACHE_STORE=file APP_ENV=production \
    $PHP_BIN -d memory_limit=256M $ARTISAN queue:work --once --sleep=3 --tries=3 --timeout=$TIMEOUT >> "$LOG_FILE" 2>&1 &

  echo "$(date -u +'%Y-%m-%dT%H:%M:%SZ') spawned pid=$! log=$LOG_FILE" >> "$AUTOSCALER_LOG"
done

exit 0
