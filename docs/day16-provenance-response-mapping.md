# Day 16 — Provenance response mapping

## Goal

Normalize the documented Content Provenance API response into the application's provider-neutral result shape. This is an offline transformation only: it makes no HTTP requests and does not read or transmit image files.

## Mapping rules

- Accept only the documented `content_provenance_check` response object and a results array.
- Map known `c2pa` and `synthid` entries independently.
- Validate each known signal's outcome; C2PA also requires a recognized validation state.
- Mark an empty results list as `inconclusive`, not as human-created.
- Treat malformed responses as a generic `failed` result; never expose provider response bodies or secrets.
- Ignore unknown future signal types for forward compatibility.
- Keep explanations cautious: `not_detected` means no supported signal was found, not that the image is human-made. Invalid C2PA credentials are explicitly not treated as reliable evidence.

## Files

- `src/Detection/OpenAIProvenanceResponseMapper.php` — pure response mapping logic.
- `tests/provenance_response_mapper.php` — offline regression checks.

## How to test on your computer

From the repository root, run:

```bash
php -l src/Detection/OpenAIProvenanceResponseMapper.php
php -l tests/provenance_response_mapper.php
php tests/provenance_response_mapper.php
```

Expected output is `Response mapper tests passed.` only if all assertions pass. These commands have not been run in this GitHub editing session.

## Not enabled yet

This mapper is not connected to the upload handler. No image is sent to an external provider. External processing remains disabled pending privacy/access review and an explicit product decision. SonarCloud remains pending.
