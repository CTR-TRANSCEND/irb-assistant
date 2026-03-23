# MariaDB Database Setup - 503c Assistant

## Overview

This directory contains scripts for setting up and managing MariaDB for the 503c Assistant application.

## Scripts

### 1. setup-production.sh

**Purpose**: Initial one-time setup for production database

**What it does**:
- Starts system MariaDB service (using systemctl)
- Creates a database user (credentials provided via env vars / prompt)
- Creates databases: `irb_503c_assistant` and `irb_503c_assistant_test`
- Updates `.env` file with production database configuration

**Usage**:
```bash
cd /home/juhur/PROJECTS/project_IRB-assist/503c-assistant
./ops/db/setup-production.sh
```

**Requirements**:
- Sudo access (interactive)
- MariaDB installed and available via systemctl

### 2. manage-production.sh

**Purpose**: Manage MariaDB service for production

**Usage**:
```bash
./ops/db/manage-production.sh {start|stop|restart|status}
```

**Examples**:
```bash
# Start MariaDB service
./ops/db/manage-production.sh start

# Stop MariaDB service
./ops/db/manage-production.sh stop

# Restart MariaDB service
./ops/db/manage-production.sh restart

# Check service status
./ops/db/manage-production.sh status
```

### 3. start.sh (Local Development)

**Purpose**: Start local user-space MariaDB for development

**Usage**: `./ops/db/start.sh`

**Note**: This runs MariaDB in local user-space. For production, use `manage-production.sh` instead.

### 4. stop.sh (Local Development)

**Purpose**: Stop local user-space MariaDB for development

**Usage**: `./ops/db/stop.sh`

**Note**: Only works with local user-space MariaDB started by `start.sh`.

## System Commands

For direct MariaDB service management (alternative to manage-production.sh):

```bash
# Start MariaDB
sudo systemctl start mariadb

# Stop MariaDB
sudo systemctl stop mariadb

# Restart MariaDB
sudo systemctl restart mariadb

# Enable MariaDB on boot
sudo systemctl enable mariadb

# Check status
sudo systemctl status mariadb
```

## Database Connection Details

After running `setup-production.sh`:

| Setting | Value |
|---------|-------|
| Host | localhost |
| Port | 3306 |
| Database | irb_503c_assistant |
| Test Database | irb_503c_assistant_test |
| User | <DB_USER> |
| Password | <DB_PASS> |
| Socket | /var/run/mysqld/mysqld.sock |

## SQL Commands Reference

### Manual Database Setup

If you need to set up the database manually:

```sql
-- Create database
CREATE DATABASE IF NOT EXISTS `irb_503c_assistant` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER IF NOT EXISTS '<DB_USER>'@'localhost' IDENTIFIED BY '<DB_PASS>';

-- Grant privileges
GRANT ALL PRIVILEGES ON `irb_503c_assistant`.* TO '<DB_USER>'@'localhost';

-- Create test database
CREATE DATABASE IF NOT EXISTS `irb_503c_assistant_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `irb_503c_assistant_test`.* TO '<DB_USER>'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;
```

### Connect to Database

```bash
# Connect as root
sudo mariadb -u root -p

# Connect as hurlab user
mariadb -u <DB_USER> -p
# Enter password when prompted

# Connect directly to the database
mariadb -u <DB_USER> -p irb_503c_assistant
```

## Laravel Migration Commands

After database setup:

```bash
cd /home/juhur/PROJECTS/project_IRB-assist/503c-assistant

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Fresh migration (drop all tables and re-run)
php artisan migrate:fresh --seed
```

## Troubleshooting

### MariaDB service won't start

```bash
# Check error logs
sudo journalctl -u mariadb -n 50

# Check MariaDB error log
sudo tail -f /var/log/mysql/error.log
```

### Connection issues

```bash
# Verify MariaDB is running
sudo systemctl status mariadb

# Check if socket exists
ls -la /var/run/mysqld/mysqld.sock

# Test connection
mariadb -u <DB_USER> -p -e "SELECT 1"
```

### Permission issues

```bash
# Reset hurlab user password
sudo mariadb -u root -e "ALTER USER '<DB_USER>'@'localhost' IDENTIFIED BY '<DB_PASS>'; FLUSH PRIVILEGES;"
```

## Configuration Files

- `.env` - Application environment configuration (database connection settings)
- `.env.example` - Example environment file template
