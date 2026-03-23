#!/usr/bin/env bash
# MariaDB Service Management Script for 503c Assistant
# Usage: ./manage-production.sh [start|stop|restart|status]

ACTION="${1:-status}"

case "$ACTION" in
    start)
        echo "Starting MariaDB service..."
        sudo systemctl start mariadb
        echo "MariaDB service started."
        echo "Socket: /var/run/mysqld/mysqld.sock"
        ;;
    stop)
        echo "Stopping MariaDB service..."
        sudo systemctl stop mariadb
        echo "MariaDB service stopped."
        ;;
    restart)
        echo "Restarting MariaDB service..."
        sudo systemctl restart mariadb
        echo "MariaDB service restarted."
        ;;
    status)
        echo "MariaDB service status:"
        sudo systemctl status mariadb --no-pager
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac
