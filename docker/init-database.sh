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

# Add session ownership to databases created by earlier versions.
# Existing rows remain unowned and cannot be viewed by new browser sessions.
mysql --protocol=socket --user=root <<'SQL'
SET @owner_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'ai_image_detector'
      AND TABLE_NAME = 'analyses'
      AND COLUMN_NAME = 'owner_token_hash'
);
SET @owner_migration = IF(
    @owner_column_exists = 0,
    'ALTER TABLE ai_image_detector.analyses ADD COLUMN owner_token_hash CHAR(64) NULL AFTER id',
    'SELECT 1'
);
PREPARE owner_migration_stmt FROM @owner_migration;
EXECUTE owner_migration_stmt;
DEALLOCATE PREPARE owner_migration_stmt;

SET @owner_index_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'ai_image_detector'
      AND TABLE_NAME = 'analyses'
      AND INDEX_NAME = 'idx_analyses_owner_created'
);
SET @owner_index_migration = IF(
    @owner_index_exists = 0,
    'CREATE INDEX idx_analyses_owner_created ON ai_image_detector.analyses (owner_token_hash, created_at)',
    'SELECT 1'
);
PREPARE owner_index_stmt FROM @owner_index_migration;
EXECUTE owner_index_stmt;
DEALLOCATE PREPARE owner_index_stmt;
SQL

# Create or update a dedicated, non-root account. Limit it to this database.
mysql --protocol=socket --user=root <<SQL
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
GRANT SELECT, INSERT, UPDATE ON `$DB_NAME`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "Database schema and restricted application account are ready."
