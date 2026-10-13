#!/usr/bin/env bash
set -Eeuo pipefail

DB_NAME="${DB_NAME:-ai_image_detector}"
DB_USER="${DB_USER:-ai_image_app}"
: "${DB_PASSWORD:?Set DB_PASSWORD to a strong password before starting the container}"

# Keep identifiers and password to a conservative character set so they can
# safely be inserted into this SQL setup command without SQL quoting ambiguity.
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Invalid DB_NAME format." >&2; exit 1; }
[[ "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Invalid DB_USER format." >&2; exit 1; }
[[ "$DB_PASSWORD" =~ ^[A-Za-z0-9_@#%+=.-]{16,128}$ ]] || {
    echo "DB_PASSWORD must be 16-128 characters using letters, numbers, and _@#%+=.- only." >&2
    exit 1
}

# Wait for the MySQL server process to accept local socket connections.
for attempt in $(seq 1 60); do
    if mysqladmin --protocol=socket --user=root ping --silent >/dev/null 2>&1; then
        break
    fi
    if [[ "$attempt" -eq 60 ]]; then
        echo "MySQL did not become ready in time." >&2
        exit 1
    fi
    sleep 2
done

# Create the schema first. The SQL file only defines the database and table.
mysql --protocol=socket --user=root < /var/www/html/database/schema.sql

# Create or update a dedicated, non-root account. Limit it to this database.
mysql --protocol=socket --user=root <<SQL
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
GRANT SELECT, INSERT, UPDATE ON `$DB_NAME`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "Database schema and restricted application account are ready."
