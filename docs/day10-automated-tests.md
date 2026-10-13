# Day 10 — Automated PHP and security regression checks

## Added

- `tests/security_checks.php`: source-level checks for key safeguards in upload, history, result, and database connection code.
- Updated `.github/workflows/php-lint.yml`: GitHub Actions now runs PHP syntax checks and the new security regression script on pushes and pull requests to `main`.

## What the checks cover

- CSRF token comparison and uploaded-file validation.
- MIME allowlist, 5 MiB upload limit, image pixel limit, and image decoding.
- Prepared SQL statements for metadata persistence and result lookup.
- A limit on history records and HTML output escaping.
- Generic error logging and the intended no-original-file-storage behavior.

## Run locally

From the repository root, on a computer with PHP installed:

```bash
php tests/security_checks.php
```

Lint all PHP files:

```bash
find . -type f -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
```

## Limitations

These are source-level regression checks that look for expected code patterns. They do not send HTTP requests, upload malicious files, connect to MySQL, or prove the application is secure. Runtime integration tests should be added after the Docker database startup has been verified. Review test results in the PHP Tests workflow in GitHub Actions.

SonarCloud is intentionally not part of this task.
