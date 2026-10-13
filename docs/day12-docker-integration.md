# Day 12 — Docker and MySQL integration smoke test

## Added

- `tests/docker_integration_smoke.sh` builds the existing Dockerfile, starts a disposable container with a temporary database password, and exercises the application over HTTP.
- The PHP Tests GitHub Actions workflow now runs this integration script after the lighter PHP and HTTP checks.

## What it checks

1. Docker image builds.
2. Container starts and serves the upload form.
3. A small PNG fixture can be uploaded with the session's CSRF token.
4. A successful upload redirects with HTTP 303 to a result record.
5. The result page honestly reports that AI detection is not configured.
6. The history page displays the saved PNG metadata.

The script removes its test container and temporary files on exit. It does not remove or alter any separately named local project database volume because it creates a disposable container without mounting that volume.

## Run locally

Requirements: Docker, Bash, and curl.

From the repository root:

```bash
bash tests/docker_integration_smoke.sh
```

If port 18080 is already in use, choose another local port:

```bash
TEST_DOCKER_PORT=18081 bash tests/docker_integration_smoke.sh
```

The test builds an image tagged `ai-image-detector:integration-test`. The image remains on your computer after the test; you can remove it manually when no longer needed.

## Important limitations

- This is an integration smoke test, not a full penetration test.
- It uses a disposable test container and a temporary password. It must not use production credentials or production data.
- The database startup and MySQL root socket-authentication assumptions are being tested indirectly. If the test fails, inspect the printed container logs and fix the underlying cause rather than weakening the assertions.
- The test does not prove production readiness, implement authentication, or connect an AI-origin detection model.
- GitHub Actions execution must be checked before claiming the test passed.

SonarCloud is not included.
