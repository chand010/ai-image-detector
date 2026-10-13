#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${TEST_HTTP_PORT:-18763}"
BASE_URL="http://127.0.0.1:${PORT}"
SERVER_LOG="$(mktemp)"
BODY_FILE="$(mktemp)"
SERVER_PID=""

cleanup() {
    if [[ -n "$SERVER_PID" ]]; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    rm -f "$SERVER_LOG" "$BODY_FILE"
}
trap cleanup EXIT

fail() {
    echo "FAIL: $*" >&2
    if [[ -s "$SERVER_LOG" ]]; then
        echo "--- PHP server log ---" >&2
        cat "$SERVER_LOG" >&2
    fi
    exit 1
}

assert_status() {
    local expected="$1"
    local actual="$2"
    local label="$3"
    [[ "$actual" == "$expected" ]] || fail "$label expected HTTP $expected but received $actual"
    echo "PASS: $label returned HTTP $actual"
}

php -S "127.0.0.1:$PORT" -t "$ROOT_DIR/public" >"$SERVER_LOG" 2>&1 &
SERVER_PID=$!

ready=0
for _ in $(seq 1 40); do
    if curl --silent --show-error "$BASE_URL/" -o "$BODY_FILE" 2>/dev/null; then
        ready=1
        break
    fi
    sleep 0.25
done
[[ "$ready" == 1 ]] || fail "PHP development server did not start"

status="$(curl --silent --show-error -o "$BODY_FILE" -w '%{http_code}' "$BASE_URL/")"
assert_status 200 "$status" "Upload form"

grep -q 'name="csrf_token"' "$BODY_FILE" || fail "Upload form did not include a CSRF token"
echo "PASS: upload form contains a CSRF token field"

status="$(curl --silent --show-error -o "$BODY_FILE" -w '%{http_code}' "$BASE_URL/upload.php")"
assert_status 405 "$status" "GET upload endpoint"

status="$(curl --silent --show-error -o "$BODY_FILE" -w '%{http_code}' -X POST -d 'csrf_token=invalid' "$BASE_URL/upload.php")"
assert_status 403 "$status" "POST upload with invalid CSRF token"

grep -q 'could not be verified' "$BODY_FILE" || fail "Invalid CSRF response did not show the expected generic message"
echo "PASS: invalid CSRF response is generic"

status="$(curl --silent --show-error -o "$BODY_FILE" -w '%{http_code}' "$BASE_URL/result.php?id=not-a-number")"
assert_status 400 "$status" "Result endpoint with invalid ID"

grep -q 'valid analysis reference is required' "$BODY_FILE" || fail "Invalid result ID response did not show expected message"
echo "PASS: invalid result ID response is generic"

echo "PASS: HTTP security smoke tests completed."
