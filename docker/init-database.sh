#!/usr/bin/env bash
set -Eeuo pipefail

DB_NAME="${DB_NAME:-ai_image_detector}"
DB_USER="${DB_USER:-ai_image_app}"
: "${DB_PASSWORD:?Set DB_PASSWORD to a strong password before starting the container}"

# Validate identifiers before inserting them into SQL statements.
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Invalid DB_NAME format." >&2; exit 1; }
[[ "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Invalid DB_USER format." >&2; exit 1; }
[[ "$DB_PASSWORD" =~ ^[A-Za-z0-9_@#%+=.-]{16,128}$ ]] || {
    echo "DB_PASSWORD must be 16-128 characters using letters, numbers, and _@#%+=.- only." >&2
    exit 1
}

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

# Create any missing tables, then migrate columns on an existing persistent
# volume. CREATE TABLE IF NOT EXISTS does not update existing table schemas.
sed "s/ai_image_detector/$DB_NAME/g" /var/www/html/database/schema.sql |
    mysql --protocol=socket --user=root

column_exists() {
    local column_name="$1"
    mysql --protocol=socket --user=root --batch --skip-column-names -e \
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '$DB_NAME'
           AND TABLE_NAME = 'analyses'
           AND COLUMN_NAME = '$column_name'"
}

ensure_column() {
    local column_name="$1"
    local column_definition="$2"
    if [[ "$(column_exists "$column_name")" == "0" ]]; then
        mysql --protocol=socket --user=root -e \
            "ALTER TABLE $DB_NAME.analyses ADD COLUMN $column_name $column_definition"
        echo "Added missing analyses.$column_name column."
    fi
}

ensure_column owner_token_hash "CHAR(64) NULL"
ensure_column detection_label "VARCHAR(64) NULL"
ensure_column confidence "DECIMAL(5,4) NULL"
ensure_column detection_provider "VARCHAR(64) NULL"
ensure_column detection_error_code "VARCHAR(64) NULL"
ensure_column detection_signals_json "JSON NULL"
ensure_column external_processing_consent "TINYINT(1) NOT NULL DEFAULT 0"

# Older volumes may have an ENUM that rejects newer inconclusive/unavailable
# results. Update it before PHP attempts to save detector output.
status_type="$(mysql --protocol=socket --user=root --batch --skip-column-names -e \
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = '$DB_NAME'
       AND TABLE_NAME = 'analyses'
       AND COLUMN_NAME = 'detection_status'")"
if [[ -z "$status_type" ]]; then
    mysql --protocol=socket --user=root -e \
        "ALTER TABLE $DB_NAME.analyses
         ADD COLUMN detection_status ENUM('not_configured','completed','inconclusive','unavailable','failed')
         NOT NULL DEFAULT 'not_configured'"
elif [[ "$status_type" != *"'inconclusive'"* || "$status_type" != *"'unavailable'"* ]]; then
    mysql --protocol=socket --user=root -e \
        "ALTER TABLE $DB_NAME.analyses
         MODIFY COLUMN detection_status ENUM('not_configured','completed','inconclusive','unavailable','failed')
         NOT NULL DEFAULT 'not_configured'"
    echo "Migrated analyses.detection_status to support all result states."
fi

index_exists="$(mysql --protocol=socket --user=root --batch --skip-column-names -e \
    "SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = '$DB_NAME'
       AND TABLE_NAME = 'analyses'
       AND INDEX_NAME = 'idx_analyses_owner_created'")"
if [[ "$index_exists" == "0" ]]; then
    mysql --protocol=socket --user=root -e \
        "CREATE INDEX idx_analyses_owner_created ON $DB_NAME.analyses (owner_token_hash, created_at)"
fi

# Use a dedicated non-root account with only the app's required privileges.
mysql --protocol=socket --user=root <<SQL
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
GRANT SELECT, INSERT, UPDATE ON $DB_NAME.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "Database schema and restricted application account are ready."
