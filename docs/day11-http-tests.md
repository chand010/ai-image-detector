# Day 11 — HTTP security smoke tests

## Added

- `tests/http_security_smoke.sh` starts PHP's built-in development server locally and sends real HTTP requests to the existing public endpoints.
- GitHub Actions now runs this script after PHP lint and source-level security checks.

## Requests tested

- The home/upload form responds with HTTP 200 and includes a CSRF token field.
- A GET request to `/upload.php` responds with HTTP 405.
- A POST request with an invalid CSRF token responds with HTTP 403 and a generic message.
- A malformed analysis ID at `/result.php?id=not-a-number` responds with HTTP 400 and a generic message.

## Run locally

From the repository root, on Linux, macOS, or a shell with Bash, PHP, and curl:

```bash
bash tests/http_security_smoke.sh
```

The script starts a temporary PHP development server and shuts it down when the tests finish. It uses port 18763 by default; set `TEST_HTTP_PORT` if that port is already in use.

## Limitations

These are HTTP smoke tests, not full integration tests. They do not upload a valid image, test MySQL connectivity, or verify the database-dependent 404/503 result paths. Those require a working database setup in the Docker container. The PHP built-in server is for local testing only, not production hosting.

The changes are committed, but the workflow result must be checked in GitHub Actions before claiming that the tests passed.
