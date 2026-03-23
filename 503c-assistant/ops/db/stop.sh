#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
PIDFILE="$BASE_DIR/var/mariadb/run/mysqld.pid"
SOCKET="$BASE_DIR/var/mariadb/run/mysqld.sock"

if [[ ! -f "$PIDFILE" ]]; then
  echo "MariaDB not running (no pidfile)"
  exit 0
fi

PID="$(cat "$PIDFILE" || true)"
if [[ "${PID}" == "" ]]; then
  echo "MariaDB pidfile empty"
  exit 0
fi

if ! kill -0 "$PID" 2>/dev/null; then
  echo "MariaDB not running (stale pidfile pid=$PID)"
  exit 0
fi

ARGS="$(ps -p "$PID" -o args= 2>/dev/null || true)"
if [[ ! "$ARGS" == *"mysqld"* || ! "$ARGS" == *"--socket=$SOCKET"* ]]; then
  echo "MariaDB pidfile points to unexpected process (pid=$PID). Not stopping." >&2
  exit 1
fi

echo "Stopping MariaDB (pid=$PID)..."
kill "$PID"

for _ in $(seq 1 80); do
  if ! kill -0 "$PID" 2>/dev/null; then
    echo "MariaDB stopped"
    exit 0
  fi
  sleep 0.1
done

echo "MariaDB did not stop cleanly; sending SIGKILL"
kill -9 "$PID" || true
