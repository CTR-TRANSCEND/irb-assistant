#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$BASE_DIR"

if ! command -v php >/dev/null 2>&1; then
  echo "php not found in PATH. Install PHP 8.2+ (php-cli) and try again." >&2
  exit 1
fi

if ! command -v node >/dev/null 2>&1; then
  echo "node not found in PATH. Install Node.js and try again." >&2
  exit 1
fi

if [[ ! -f ".env" ]]; then
  cp .env.example .env
  php artisan key:generate
fi

DB_CONNECTION="$(grep -E '^DB_CONNECTION=' .env | sed 's/^DB_CONNECTION=//' | tail -n 1 || true)"
DB_DATABASE="$(grep -E '^DB_DATABASE=' .env | sed 's/^DB_DATABASE=//' | tail -n 1 || true)"
APP_URL="$(grep -E '^APP_URL=' .env | sed 's/^APP_URL=//' | tail -n 1 || true)"

USE_MYSQL=0

if [[ "$DB_CONNECTION" == "sqlite" ]]; then
  if php -m | grep -qiE '^pdo_sqlite$|^sqlite3$'; then
    if [[ "$DB_DATABASE" == "" ]]; then
      DB_DATABASE="$BASE_DIR/database/database.sqlite"
    fi
    mkdir -p "$(dirname -- "$DB_DATABASE")"
    if [[ ! -f "$DB_DATABASE" ]]; then
      touch "$DB_DATABASE"
    fi
  else
    echo "SQLite driver not installed; using local MariaDB for E2E." >&2
    USE_MYSQL=1
  fi
else
  USE_MYSQL=1
fi

if [[ "$USE_MYSQL" -eq 1 ]]; then
  if [[ "${E2E_SKIP_DB:-}" != "1" ]]; then
    # TEST-ONLY defaults: These credentials are used exclusively for the
    # ephemeral local E2E test database, never for production. Override via
    # E2E_DB_DATABASE / E2E_DB_USERNAME / E2E_DB_PASSWORD if needed.
    DB_DATABASE="${E2E_DB_DATABASE:-irb_503c_assistant_e2e}"
    DB_USERNAME="${E2E_DB_USERNAME:-irb_e2e}"
    DB_PASSWORD="${E2E_DB_PASSWORD:-$(openssl rand -hex 16)}"

    export DB_DATABASE DB_USERNAME DB_PASSWORD
    ./ops/db/start.sh
  fi

  BASE_URL="${E2E_BASE_URL:-${APP_URL:-http://127.0.0.1:8000}}"
  HOST="$(BASE_URL="$BASE_URL" python3 - <<'PY'
import os, urllib.parse
u = urllib.parse.urlparse(os.environ['BASE_URL'])
print(u.hostname or '127.0.0.1')
PY
  )"
  PORT="$(BASE_URL="$BASE_URL" python3 - <<'PY'
import os, urllib.parse
u = urllib.parse.urlparse(os.environ['BASE_URL'])
port = u.port
if port is None:
  port = 443 if u.scheme == 'https' else 80
print(port)
PY
  )"

  export E2E_WEB_SERVER_COMMAND="DB_CONNECTION=mysql DB_HOST=localhost DB_PORT=3306 DB_SOCKET=var/mariadb/run/mysqld.sock DB_DATABASE=${DB_DATABASE} DB_USERNAME=${DB_USERNAME} DB_PASSWORD=${DB_PASSWORD} php artisan serve --host=${HOST} --port=${PORT}"

  env DB_CONNECTION=mysql DB_HOST=localhost DB_PORT=3306 DB_SOCKET=var/mariadb/run/mysqld.sock DB_DATABASE="$DB_DATABASE" DB_USERNAME="$DB_USERNAME" DB_PASSWORD="$DB_PASSWORD" php artisan migrate --force
  env DB_CONNECTION=mysql DB_HOST=localhost DB_PORT=3306 DB_SOCKET=var/mariadb/run/mysqld.sock DB_DATABASE="$DB_DATABASE" DB_USERNAME="$DB_USERNAME" DB_PASSWORD="$DB_PASSWORD" php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder
else
  php artisan migrate --force
  php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder
fi

if [[ ! -d "node_modules" ]]; then
  npm install
fi

# Ensure browsers are installed (no sudo deps).
npx playwright install chromium

npm run test:e2e
