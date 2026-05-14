#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"

DATA_DIR="$BASE_DIR/var/mariadb/data"
RUN_DIR="$BASE_DIR/var/mariadb/run"
LOG_DIR="$BASE_DIR/var/mariadb/log"

SOCKET="$RUN_DIR/mysqld.sock"
PIDFILE="$RUN_DIR/mysqld.pid"
LOGFILE="$LOG_DIR/mysqld.log"

mkdir -p "$DATA_DIR" "$RUN_DIR" "$LOG_DIR"

if ! command -v mysqld >/dev/null 2>&1; then
  echo "mysqld not found in PATH. Install MariaDB server binaries (e.g. sudo apt-get install mariadb-server) and try again." >&2
  exit 1
fi

SQL_CLIENT=""
if command -v mariadb >/dev/null 2>&1; then
  SQL_CLIENT="mariadb"
elif command -v mysql >/dev/null 2>&1; then
  SQL_CLIENT="mysql"
fi

ALREADY_RUNNING=0

if [[ -f "$PIDFILE" ]]; then
  PID="$(cat "$PIDFILE" || true)"
  if [[ "${PID}" != "" ]] && kill -0 "$PID" 2>/dev/null; then
    # PID exists; verify it's our mysqld.
    ARGS="$(ps -p "$PID" -o args= 2>/dev/null || true)"
    if [[ "$ARGS" == *"mysqld"* && "$ARGS" == *"--socket=$SOCKET"* ]]; then
      echo "MariaDB already running (pid=$PID)"
      ALREADY_RUNNING=1
    else
      rm -f "$PIDFILE"
    fi
  else
    rm -f "$PIDFILE"
  fi
fi

if [[ "$ALREADY_RUNNING" -eq 0 ]]; then
  # Clean up stale socket from a previous run.
  if [[ -S "$SOCKET" ]]; then
    rm -f "$SOCKET"
  fi

  if [[ ! -d "$DATA_DIR/mysql" ]]; then
    echo "Initializing local MariaDB datadir..."
    mariadb-install-db --datadir="$DATA_DIR" --auth-root-authentication-method=normal --skip-test-db
  fi

  echo "Starting local MariaDB..."
  mysqld \
    --no-defaults \
    --datadir="$DATA_DIR" \
    --socket="$SOCKET" \
    --pid-file="$PIDFILE" \
    --log-error="$LOGFILE" \
    --skip-networking=1 \
    --skip-name-resolve=1 \
    --character-set-server=utf8mb4 \
    --collation-server=utf8mb4_unicode_ci \
    --innodb_file_per_table=1 \
    &

  for _ in $(seq 1 80); do
    if [[ -S "$SOCKET" ]]; then
      break
    fi
    sleep 0.1
  done

  if [[ ! -S "$SOCKET" ]]; then
    echo "MariaDB failed to start. Check: $LOGFILE" >&2
    exit 1
  fi
else
  if [[ ! -S "$SOCKET" ]]; then
    echo "MariaDB is running but socket not found: $SOCKET" >&2
    exit 1
  fi
fi

echo "MariaDB socket ready: $SOCKET"

ENV_FILE="$BASE_DIR/.env"

# Prefer exported env vars. Use .env as fallback.
DB_NAME="${DB_DATABASE:-}"
DB_USER="${DB_USERNAME:-}"
DB_PASS="${DB_PASSWORD:-}"
SOURCE="environ"

FILE_DB_NAME=""
FILE_DB_USER=""
FILE_DB_PASS=""

if [[ -f "$ENV_FILE" ]]; then
  FILE_DB_NAME="$(grep -E '^DB_DATABASE=' "$ENV_FILE" | sed 's/^DB_DATABASE=//' | tail -n 1 || true)"
  FILE_DB_USER="$(grep -E '^DB_USERNAME=' "$ENV_FILE" | sed 's/^DB_USERNAME=//' | tail -n 1 || true)"
  FILE_DB_PASS="$(grep -E '^DB_PASSWORD=' "$ENV_FILE" | sed 's/^DB_PASSWORD=//' | tail -n 1 || true)"
fi

if [[ "$DB_NAME" == "" ]]; then
  if [[ "$FILE_DB_NAME" != "" && ! "$FILE_DB_NAME" == *"/"* ]]; then
    DB_NAME="$FILE_DB_NAME"
    SOURCE="envfile"
  fi
fi

if [[ "$DB_USER" == "" ]]; then
  if [[ "$FILE_DB_USER" != "" ]]; then
    DB_USER="$FILE_DB_USER"
    SOURCE="envfile"
  fi
fi

if [[ "$DB_PASS" == "" && "$FILE_DB_PASS" != "" ]]; then
  DB_PASS="$FILE_DB_PASS"
fi

if [[ "$DB_NAME" != "" && "$DB_USER" != "" ]]; then
  if [[ "$SQL_CLIENT" == "" ]]; then
    echo "mariadb/mysql client not found; cannot create database/user. Install mariadb-client (or mysql-client) and try again." >&2
    exit 1
  fi

  # Only auto-generate and write DB_PASSWORD if we sourced settings from .env.
  if [[ "$DB_PASS" == "" && "$SOURCE" == "envfile" && -f "$ENV_FILE" && "$(grep -E '^DB_PASSWORD=' "$ENV_FILE" | tail -n 1 || true)" == "DB_PASSWORD=" ]]; then
    DB_PASS="$(openssl rand -hex 16)"
    # Pass values via environment variables so credentials never appear in
    # the Python source (visible in /proc on some systems).
    _PY_ENV_FILE="$ENV_FILE" _PY_DB_PASS="$DB_PASS" python3 - <<'PY'
import os, pathlib
p = pathlib.Path(os.environ["_PY_ENV_FILE"])
db_pass = os.environ["_PY_DB_PASS"]
lines = p.read_text().splitlines(True)
out = []
replaced = False
for line in lines:
    if line.startswith("DB_PASSWORD="):
        out.append(f"DB_PASSWORD={db_pass}\n")
        replaced = True
    else:
        out.append(line)
if not replaced:
    out.append(f"\nDB_PASSWORD={db_pass}\n")
p.write_text(''.join(out))
PY
    echo "Generated DB_PASSWORD and updated .env"
  fi

  # Use stdin instead of -e so that credentials are not visible in process args.
  "$SQL_CLIENT" --protocol=SOCKET --socket="$SOCKET" -uroot <<EOSQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOSQL
  echo "Ensured database/user exist: $DB_NAME / $DB_USER"

  TEST_DB_NAME="${DB_NAME}_test"
  "$SQL_CLIENT" --protocol=SOCKET --socket="$SOCKET" -uroot <<EOSQL
CREATE DATABASE IF NOT EXISTS \`$TEST_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`$TEST_DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOSQL
  echo "Ensured test database exists: $TEST_DB_NAME"
fi
