#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="$BASE_DIR/.env"
ENV_EXAMPLE="$BASE_DIR/.env.example"

# Configuration
DB_NAME="${DB_NAME:-irb_503c_assistant}"
DB_USER="${DB_USER:-irb_503c_user}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"

DB_PASS="${DB_PASS:-}"
if [[ -z "${DB_PASS}" ]]; then
    read -r -s -p "Enter DB password for user '$DB_USER': " DB_PASS
    echo ""
fi
if [[ -z "${DB_PASS}" ]]; then
    echo "ERROR: DB_PASS is required (set DB_PASS env var or enter when prompted)." >&2
    exit 1
fi

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "ERROR: DB_NAME must be alphanumeric/underscore (got '$DB_NAME')." >&2
    exit 1
fi
if [[ ! "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "ERROR: DB_USER must be alphanumeric/underscore (got '$DB_USER')." >&2
    exit 1
fi
if [[ "$DB_PASS" == *"'"* || "$DB_PASS" == *$'\n'* || "$DB_PASS" == *$'\r'* ]]; then
    echo "ERROR: DB_PASS must not contain single quotes or newlines." >&2
    exit 1
fi

echo "=== MariaDB Production Setup for 503c Assistant ==="
echo ""
echo "This script will:"
echo "  1. Start system MariaDB service"
echo "  2. Create database user: $DB_USER"
echo "  3. Create database: $DB_NAME"
echo "  4. Update .env configuration"
echo ""

# Check if running as root or with sudo
if [[ $EUID -eq 0 ]]; then
   echo "This script should not be run as root. Run as regular user with sudo access."
   exit 1
fi

# Check MariaDB service status
echo "Step 1: Checking MariaDB service..."
if systemctl is-active --quiet mariadb 2>/dev/null || systemctl is-active --quiet mysql 2>/dev/null; then
    echo "  MariaDB service is already running."
else
    echo "  Starting MariaDB service..."
    echo "  (Sudo password may be required)"
    sudo systemctl start mariadb 2>/dev/null || sudo systemctl start mysql 2>/dev/null

    if systemctl is-active --quiet mariadb 2>/dev/null || systemctl is-active --quiet mysql 2>/dev/null; then
        echo "  MariaDB service started successfully."
    else
        echo "  ERROR: Failed to start MariaDB service." >&2
        exit 1
    fi
fi

# Enable MariaDB to start on boot
sudo systemctl enable mariadb 2>/dev/null || sudo systemctl enable mysql 2>/dev/null || true

echo ""
echo "Step 2: Setting up database and user..."

# Write SQL commands to a temporary file so that credentials are never passed
# on the command line or visible in /proc. The temp file is removed on exit.
SQL_TMPFILE="$(mktemp)"
trap 'rm -f "$SQL_TMPFILE"' EXIT

cat > "$SQL_TMPFILE" <<EOSQL
-- Create database if not exists
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user if not exists
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';

-- Grant privileges
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';

-- Create test database
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}_test\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${DB_NAME}_test\`.* TO '$DB_USER'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;
EOSQL
chmod 600 "$SQL_TMPFILE"

# Execute SQL from the temp file (password never appears in process args).
echo "  Executing SQL setup..."
if sudo mariadb -u root 2>/dev/null < "$SQL_TMPFILE"; then
    echo "  Database and user setup completed."
elif sudo mysql -u root 2>/dev/null < "$SQL_TMPFILE"; then
    echo "  Database and user setup completed."
else
    echo "  ERROR: Failed to setup database." >&2
    echo "  - Ensure MariaDB/MySQL is running" >&2
    echo "  - Ensure your user can run sudo and connect as root via socket" >&2
    exit 1
fi

echo ""
echo "Step 3: Updating .env configuration..."

# Create .env from .env.example if it doesn't exist
if [[ ! -f "$ENV_FILE" ]]; then
    cp "$ENV_EXAMPLE" "$ENV_FILE"
    echo "  Created .env from .env.example"
fi

# Update database configuration in .env
# Pass all values via environment variables so that credentials never appear in
# the Python source code (which would be visible in /proc on some systems).
_PY_ENV_FILE="$ENV_FILE" \
_PY_DB_HOST="$DB_HOST" \
_PY_DB_PORT="$DB_PORT" \
_PY_DB_NAME="$DB_NAME" \
_PY_DB_USER="$DB_USER" \
_PY_DB_PASS="$DB_PASS" \
python3 - <<'PY'
import os, pathlib

env_file = pathlib.Path(os.environ["_PY_ENV_FILE"])

def _dotenv_escape(value: str) -> str:
    if value == "":
        return ""
    needs_quote = any(ch in value for ch in [" ", "\t", "\n", "#", "\"", "\\"])
    if not needs_quote:
        return value
    escaped = value.replace("\\", "\\\\").replace('"', '\\"')
    return f'"{escaped}"'

updates = {
    "APP_ENV": "production",
    "APP_DEBUG": "false",
    "DB_HOST": os.environ["_PY_DB_HOST"],
    "DB_PORT": os.environ["_PY_DB_PORT"],
    "DB_SOCKET": "",  # Empty for system MariaDB
    "DB_DATABASE": os.environ["_PY_DB_NAME"],
    "DB_USERNAME": os.environ["_PY_DB_USER"],
    "DB_PASSWORD": os.environ["_PY_DB_PASS"],
}

text = env_file.read_text() if env_file.exists() else ""
lines = text.splitlines(True)

seen = set()
out = []
for line in lines:
    matched = False
    for key, raw_value in updates.items():
        if line.startswith(f"{key}="):
            out.append(f"{key}={_dotenv_escape(raw_value)}\n")
            seen.add(key)
            matched = True
            break
    if not matched:
        out.append(line)

missing = [k for k in updates.keys() if k not in seen]
if missing:
    if out and not out[-1].endswith("\n"):
        out.append("\n")
    out.append("\n# Production database configuration\n")
    for key in missing:
        out.append(f"{key}={_dotenv_escape(updates[key])}\n")

env_file.write_text("".join(out))
PY

echo "  Updated .env configuration."
echo "  - DB_HOST=$DB_HOST"
echo "  - DB_PORT=$DB_PORT"
echo "  - DB_DATABASE=$DB_NAME"
echo "  - DB_USERNAME=$DB_USER"
echo "  - DB_PASSWORD=***"

echo ""
echo "=== Setup Complete ==="
echo ""
echo "MariaDB service commands:"
echo "  Start:   sudo systemctl start mariadb"
echo "  Stop:    sudo systemctl stop mariadb"
echo "  Restart: sudo systemctl restart mariadb"
echo "  Status:  sudo systemctl status mariadb"
echo ""
echo "Database connection details:"
echo "  Host: $DB_HOST"
echo "  Port: $DB_PORT"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo ""
echo "Next steps:"
echo "  1. Run: cd /home/juhur/PROJECTS/project_IRB-assist/503c-assistant"
echo "  2. Run: php artisan migrate"
echo "  3. Run: php artisan db:seed"
echo ""
