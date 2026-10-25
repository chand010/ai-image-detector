# Security review — detection engine and upload flow

**Scope:** static review of the current repository on the feature branch. This is not a penetration test, and no PHP, Docker, or MySQL test suite has been executed in this editing session.

## Changes made in this branch

- Connected the existing provider adapter to the upload workflow only after image validation.
- Added a per-upload consent checkbox. It is unchecked by default; the upload is not sent externally without the explicit `external_processing_consent=yes` form value.
- Retained the second, server-side gate: `ENABLE_EXTERNAL_PROVENANCE` must be exactly `true`. It remains `false` in `.env.example`.
- Persisted only provider/status/signal metadata and the consent decision. The uploaded image is not copied to permanent storage.
- Updated the result page to display supported provenance signals and to warn that missing signals do not prove human authorship.
- Added a migration for databases created before the new provenance columns and status values.
- Fixed the Docker smoke test to retain the browser session cookie while loading result/history pages; updated its expected result text.
- Fixed the source-level security test's redirect assertion so the literal PHP variable name is checked rather than interpolated by the test itself.

## Findings and disposition

### 1. External transmission requires two explicit gates — addressed in code

**Risk:** Images can contain personal or confidential information. A backend API key alone must not silently enable external transmission.

**Controls:** A user must opt in on that specific upload, and the server operator must separately set `ENABLE_EXTERNAL_PROVENANCE=true`. The provider adapter also rejects unsupported MIME types, unreadable paths, and files over 5 MiB; disables redirects; validates TLS; applies connection/overall timeouts; limits response size; and returns generic errors.

**Remaining action:** Keep the server flag false until the project owner has approved the current provider terms, access, use case, and limits. The UI checkbox is consent only for a request the server has been configured to perform; it does not override a disabled server flag.

### 2. Provenance signals are not universal AI detection — addressed in UI

A `not_detected` signal is not evidence that a human created the image. The result page describes the supported signals rather than presenting a universal AI/human verdict. The adapter does not call a general-purpose classifier.

### 3. Existing database deployments need a schema migration — addressed with migration file

The old schema's status enum did not include `inconclusive` or `unavailable`, and it had no place to store provider signal details or the per-upload consent decision. Apply `database/migrations/001_add_provenance_results.sql` to an existing database after taking a backup. New databases can use `database/schema.sql`.

### 4. Docker smoke test lost the browser session — addressed in test script

The upload endpoint binds records to a session owner token. The smoke test previously omitted the cookie jar when requesting the result and history pages, so those requests could be treated as a different session. The test now sends and updates the same cookie jar.

### 5. CI source assertion could interpolate a PHP variable — addressed in test script

A double-quoted test string could interpolate `$analysisId` while checking for a source-code literal. The assertion now uses a single-quoted string with escaped apostrophes, preserving the literal variable name.

## Further hardening before production

- Run the full GitHub Actions workflow and inspect any failing step; this review does not claim it passes.
- Run a dedicated security test against the built Docker container, including malformed files, oversized uploads, invalid CSRF tokens, cross-session record access, and unavailable-provider behavior.
- The single-container Apache/PHP/MySQL setup is retained for the assignment. It increases the failure domain and should not be treated as production-ready without a separate deployment review.
- Review runtime permissions, MySQL application-user privileges, backups, patching, HTTPS termination, rate limiting, and log retention before public deployment.
- Do not add API keys to Git, image layers, client-side code, database rows, or logs.
- SonarCloud remains pending.

## Validation status

**Not run in this editing session:** PHP lint, response-mapper tests, provider disabled-by-default test, HTTP smoke test, Docker/MySQL integration smoke test, and any penetration test. The CI workflow should run when this branch is pushed/updated; inspect the actual run before marking the changes verified.
