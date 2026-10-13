# Day 17 — Disabled-by-default external provider adapter

## What was added

- `src/Detection/OpenAIProvenanceProvider.php`: optional cURL adapter with HTTPS certificate checks, redirects disabled, connection and total timeouts, a bounded response body, generic error codes, and no raw provider body in logs or UI.
- `tests/provenance_provider_disabled.php`: verifies the adapter returns without any network call while external processing is disabled.

## Critical privacy boundary

The adapter is **not connected to the upload handler**. The existing app does not call it, and external processing remains disabled. If the adapter is deliberately called after setting `ENABLE_EXTERNAL_PROVENANCE=true`, it transmits the validated temporary image to the provider. Do not enable this setting until the privacy notice, consent/opt-in decision, account access, and current usage limits have been reviewed.

The adapter uses only a server-generated filename, not the original client filename. It never writes image bytes to the application database or to a permanent file.

## Configuration

The following environment variables are read at runtime:

- `ENABLE_EXTERNAL_PROVENANCE`: must be exactly `true` before the adapter can make a request.
- `OPENAI_API_KEY`: server-side secret; never commit a real key to GitHub.
- The existing application 5 MiB size cap remains in force.

The Docker image needs PHP's cURL extension for the adapter. If it is absent, the adapter safely returns `http_client_unavailable`.

## Local offline test

```bash
php -l src/Detection/OpenAIProvenanceProvider.php
php -l tests/provenance_provider_disabled.php
php tests/provenance_provider_disabled.php
```

Expected output: `External provider disabled-by-default test passed.` These commands have not been run in this editing session.

## Not done

- No external request was made.
- No API key was added.
- The adapter is not called by `public/upload.php`.
- No claim is made that integration or Docker tests pass.
- SonarCloud remains pending.
