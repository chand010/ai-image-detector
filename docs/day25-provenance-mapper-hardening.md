# Day 25 — Provenance mapping: fail-safe edge cases

## Learning goals

- Distinguish a completed network request from a valid provenance signal.
- Reject malformed or contradictory API responses before displaying evidence.
- Confirm the difference between provenance checks and general AI-image classification.
- Use CI evidence to verify regression tests instead of assuming they pass.

## Current design

The raw-PHP application validates JPEG, PNG, and WebP uploads, with a 5 MiB size cap and a 20-megapixel limit. It stores metadata and normalized signals in MySQL, not the original image. The PHP temporary upload is explicitly unlinked after validation and any requested analysis.

The OpenAI Content Provenance provider can only be invoked for a per-upload opt-in, and its own code additionally requires `ENABLE_EXTERNAL_PROVENANCE=true` plus a server-side `OPENAI_API_KEY`. External processing remains disabled by default. No live API request was performed in this session.

The provider checks supported provenance signals. It is not a universal AI image classifier. A missing supported signal is not proof of human authorship.

## Mapper invariants

- The response object must be `content_provenance_check` and `results` must be an array.
- Known signal types (`c2pa`, `synthid`) must have a documented outcome.
- C2PA entries must have a recognized validation state.
- A positive C2PA outcome is rejected if its validation state is `invalid` or `not_present`.
- Unknown future signal types are ignored rather than presented as evidence.
- Malformed entries fail safely without exposing partial signals.
- Empty recognized results are inconclusive, not proof of human authorship.

## Regression tests added

`tests/provenance_response_mapper.php` now covers:
- malformed known signal outcome;
- unknown future signal type;
- positive C2PA outcome with invalid validation state;
- positive C2PA outcome with missing validation state;
- positive C2PA outcome with `not_present` validation state;
- non-array result entry;
- invalid manifest represented without trustworthy wording.

## Verification

GitHub Actions run #78 passed on commit `0af2fce7c69d056e41758c334d4745bed2a01af0`, including PHP syntax lint, shell lint, source-level security assertions, mapper tests, disabled-provider tests, HTTP smoke tests, and Docker/MySQL integration tests.

The edge-case tests added after that commit still require a fresh run. Do not describe them as passing until a new Actions run reports success.

## Architecture constraints retained

- Raw PHP; no framework.
- Ubuntu 24.04, Apache, PHP, and MySQL in one container.
- Dockerfile only; no Docker Compose.
- Temporary processing and deletion; no original-image retention.
- SonarCloud remains pending.
- No live external request without explicit access/privacy review.

## Official references

- OpenAI Content Provenance guide: https://developers.openai.com/api/docs/guides/content-provenance
- API reference: https://developers.openai.com/api/reference/cli/resources/content_provenance_checks/methods/create
- C2PA specification: https://spec.c2pa.org/specifications/specifications/2.1/specs/C2PA_Specification.html
- Google SynthID: https://deepmind.google/models/synthid/
