#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE_TAG="ai-image-detector:integration-test"
CONTAINER_NAME="ai-image-detector-test-$RANDOM"
HOST_PORT="${TEST_DOCKER_PORT:-18080}"
BASE_URL="http://127.0.0.1:${HOST_PORT}"
DB_PASSWORD="IntegrationTest_${RANDOM}_SecurePass99"
TMP_DIR="$(mktemp -d)"
SERVER_LOG="$TMP_DIR/container.log"
COOKIE_JAR="$TMP_DIR/cookies.txt"
HEADERS="$TMP_DIR/headers.txt"
BODY="$TMP_DIR/body.html"
PNG_FILE="$TMP_DIR/tiny.png"
SERVER_STARTED=0

cleanup() {
    if [[ "$SERVER_STARTED" == 1 ]]; then
        docker rm -f "$CONTAINER_NAME" >/dev/null 2>&1 || true
    fi
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT

fail() {
    echo "FAIL: $*" >&2
    # Preserve container diagnostics for every failure, not only startup failures.
    if [[ "$SERVER_STARTED" == 1 ]]; then
        docker logs "$CONTAINER_NAME" >"$SERVER_LOG" 2>&1 || true
    fi
    if [[ -s "$SERVER_LOG" ]]; then
        echo "--- Docker container logs ---" >&2
        cat "$SERVER_LOG" >&2
    fi
    exit 1
}

echo "Building Docker image..."
docker build -t "$IMAGE_TAG" "$ROOT_DIR"

echo "Starting test container..."
docker run -d --name "$CONTAINER_NAME" \
    -p "127.0.0.1:$HOST_PORT:80" \
    -e DB_NAME=ai_image_detector \
    -e DB_USER=ai_image_app \
    -e "DB_PASSWORD=$DB_PASSWORD" \
    -e ENABLE_LOCAL_CLASSIFIER=false \
    "$IMAGE_TAG" >/dev/null
SERVER_STARTED=1

http_ready=0
for _ in $(seq 1 90); do
    if curl --silent --show-error --max-time 2 -c "$COOKIE_JAR" "$BASE_URL/" -o "$BODY" 2>/dev/null; then
        http_ready=1
        break
    fi
    sleep 2
done
[[ "$http_ready" == 1 ]] || fail "Container did not serve the upload page within 180 seconds"

# Apache can serve the form before the one-shot DB initializer finishes.
# Wait for the restricted app account to query the actual schema over TCP.
db_ready=0
for _ in $(seq 1 60); do
    if docker exec -e "MYSQL_PWD=$DB_PASSWORD" "$CONTAINER_NAME" \
        mysql --protocol=tcp --host=127.0.0.1 --user=ai_image_app \
        --batch --skip-column-names \
        -e "SELECT 1 FROM ai_image_detector.analyses LIMIT 0;" >/dev/null 2>&1; then
        db_ready=1
        break
    fi
    sleep 2
done
[[ "$db_ready" == 1 ]] || fail "Application database account/schema did not become ready within 120 seconds"

# Simulate an older persistent volume whose ENUM does not allow unavailable
# or inconclusive results. A container restart must migrate it before uploads.
docker exec "$CONTAINER_NAME" mysql --protocol=socket --user=root -e \
    "ALTER TABLE ai_image_detector.analyses MODIFY COLUMN detection_status ENUM('not_configured','completed','failed') NOT NULL DEFAULT 'not_configured';"
docker restart "$CONTAINER_NAME" >/dev/null

db_ready=0
for _ in $(seq 1 60); do
    if docker exec -e "MYSQL_PWD=$DB_PASSWORD" "$CONTAINER_NAME" \
        mysql --protocol=tcp --host=127.0.0.1 --user=ai_image_app \
        --batch --skip-column-names \
        -e "SELECT 1 FROM ai_image_detector.analyses LIMIT 0;" >/dev/null 2>&1; then
        db_ready=1
        break
    fi
    sleep 2
done
[[ "$db_ready" == 1 ]] || fail "Database did not recover after migration restart"

STATUS_ENUM="$(docker exec "$CONTAINER_NAME" mysql --protocol=socket --user=root --batch --skip-column-names -e \
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='ai_image_detector' AND TABLE_NAME='analyses' AND COLUMN_NAME='detection_status'")"
[[ "$STATUS_ENUM" == *"'unavailable'"* && "$STATUS_ENUM" == *"'inconclusive'"* ]] || fail "Startup did not migrate detection_status enum"
echo "PASS: startup migrated legacy detection-status enum"

# Re-fetch the CSRF token after restart because PHP sessions may have been reset.
curl --silent --show-error --max-time 15 -c "$COOKIE_JAR" "$BASE_URL/" -o "$BODY" || fail "Could not reload upload page after database migration"
grep -q 'name="csrf_token"' "$BODY" || fail "Upload page is missing the CSRF token field"
echo "PASS: Docker container serves upload form"
echo "PASS: MySQL schema is ready for the restricted application account"

# A tiny 1x1 PNG used only as a disposable test fixture.
base64 -d > "$PNG_FILE" <<'PNG'
iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC
PNG

# HTML attributes may span multiple lines, so parse the complete response.
TOKEN="$(perl -0777 -ne 'if (/name="csrf_token"[^>]*value="([a-f0-9]+)"/s) { print $1 }' "$BODY")"
[[ -n "$TOKEN" ]] || fail "Could not extract CSRF token from upload form"

curl --silent --show-error --max-time 30 \
    -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -D "$HEADERS" -o "$BODY" \
    -F "csrf_token=$TOKEN" \
    -F "image=@$PNG_FILE;type=image/png" \
    "$BASE_URL/upload.php" || fail "Upload request failed"

STATUS="$(awk 'toupper($1) ~ /^HTTP\// { code=$2 } END { print code }' "$HEADERS")"
[[ "$STATUS" == "303" ]] || {
    cat "$BODY" >&2
    fail "Valid PNG upload expected HTTP 303, got ${STATUS:-no status}"
}
echo "PASS: Valid image upload returned HTTP 303"

LOCATION="$(awk 'BEGIN{IGNORECASE=1} /^Location:/ {gsub("\\r",""); print $2}' "$HEADERS" | tail -n1)"
[[ "$LOCATION" =~ ^/result\.php\?id=[1-9][0-9]*$ ]] || fail "Upload redirect did not contain a valid result reference"

curl --silent --show-error --max-time 15 -b "$COOKIE_JAR" -c "$COOKIE_JAR" "$BASE_URL$LOCATION" -o "$BODY" || fail "Could not fetch result page"
grep -q 'Engine unavailable' "$BODY" || fail "Result page did not show the disabled detector status"
grep -q 'Validated image' "$BODY" || fail "Result page did not show validated image details"
echo "PASS: Result page displays saved record and honest detection status"

curl --silent --show-error --max-time 15 -b "$COOKIE_JAR" -c "$COOKIE_JAR" "$BASE_URL/history.php" -o "$BODY" || fail "Could not fetch history page"
grep -q 'image/png' "$BODY" || fail "History page did not include the uploaded PNG metadata"
echo "PASS: History page includes saved image metadata"

echo "PASS: Docker/MySQL integration smoke test completed."
