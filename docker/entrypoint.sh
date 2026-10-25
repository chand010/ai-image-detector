#!/usr/bin/env bash
set -Eeuo pipefail

# Initialize the MySQL data directory on first start.
# Database/user creation is deliberately handled as a separate setup step;
# this script never guesses or silently creates application credentials.
if [[ ! -d /var/lib/mysql/mysql ]]; then
    echo "Initializing MySQL data directory..."
    mkdir -p /var/lib/mysql /var/run/mysqld
    chown -R mysql:mysql /var/lib/mysql /var/run/mysqld
    mysql_install_db --user=mysql --datadir=/var/lib/mysql
fi

# A container restart can preserve /var/run files in the writable layer while
# PID 1 and Apache have exited. Remove only Apache's stale PID marker so
# apache2ctl does not exit with "httpd already running" on the next start.
rm -f /var/run/apache2/apache2.pid

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
