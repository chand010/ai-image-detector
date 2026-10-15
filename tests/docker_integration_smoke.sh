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
    "$IMAGE_TAG" >/dev/null
SERVER_STARTED=1

ready=0
for _ in $(seq 1 90); do
    if curl --silent --show-error --max-time 2 -c "$COOKIE_JAR" "$BASE_URL/" -o "$BODY" 2>/dev/null; then
        ready=1
        break
    fi
    sleep 2
done
if [[ "$ready" != 1 ]]; then
    docker logs "$CONTAINER_NAME" >"$SERVER_LOG" 2>&1 || true
    fail "Container did not serve the upload page within 180 seconds"
fi

grep -q 'name="csrf_token"' "$BODY" || fail "Upload page is missing the CSRF token field"
echo "PASS: Docker container serves upload form"

# A tiny 1x1 PNG used only as a disposable test fixture.
base64 -d > "$PNG_FILE" <<'PNG'
iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pXcAAAAASUVORK5CYII=
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

STATUS="$(awk 'toupper($1) ~ /^HTTP\\// { code=$2 } END { print code }' "$HEADERS")"
[[ "$STATUS" == "303" ]] || {
    cat "$BODY" >&2
    fail "Valid PNG upload expected HTTP 303, got ${STATUS:-no status}"
}
echo "PASS: Valid image upload returned HTTP 303"

LOCATION="$(awk 'BEGIN{IGNORECASE=1} /^Location:/ {gsub("\\r",""); print $2}' "$HEADERS" | tail -n1)"
[[ "$LOCATION" =~ ^/result\\.php\\?id=[1-9][0-9]*$ ]] || fail "Upload redirect did not contain a valid result reference"

curl --silent --show-error --max-time 15 "$BASE_URL$LOCATION" -o "$BODY" || fail "Could not fetch result page"
grep -q 'AI detection engine has not been connected' "$BODY" || fail "Result page did not explain that detection is not configured"
grep -q 'Image validation' "$BODY" || fail "Result page did not show image validation"
echo "PASS: Result page displays saved record and honest detection status"

curl --silent --show-error --max-time 15 "$BASE_URL/history.php" -o "$BODY" || fail "Could not fetch history page"
grep -q 'image/png' "$BODY" || fail "History page did not include the uploaded PNG metadata"
echo "PASS: History page includes saved image metadata"

echo "PASS: Docker/MySQL integration smoke test completed."
