# Day 21 — Repair the PHP security-check syntax blocker

**Date:** 20 October 2026

## Learning goals

- Explain why PHP string interpolation immediately before a colon can be ambiguous.
- Use `sprintf()` to build diagnostic messages without weakening assertions.
- Distinguish lint, source-level checks, and runtime integration tests.
- Preserve the distinction between file validation and AI-origin detection.

## Change made

In `tests/security_checks.php`, three diagnostic-message expressions now use `sprintf()` instead of interpolating `$name` directly before a colon. All existing `$needles` and `$forbidden` assertions are preserved.

This change addresses the known parse-error location only. It does not establish that all tests pass or that the application is secure.

## Local verification tasks

Run from the repository root:

```bash
php -l tests/security_checks.php
php tests/security_checks.php
find . -type f -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
php tests/provenance_response_mapper.php
php tests/provenance_provider_disabled.php
```

If Docker is available, inspect and run the workflow's smoke tests:

```bash
bash tests/http_security_smoke.sh
bash tests/docker_integration_smoke.sh
```

Record the actual outputs. PHP lint checks syntax only. Source-level security checks inspect source text and are not substitutes for runtime testing.

## Current detector status

The upload flow validates supported image formats and records metadata, but no AI-origin classifier is connected. The optional external provenance adapter remains disabled and is not wired to uploads. The current upload flow does not retain original image bytes.

Do not describe a validated upload as AI-generated or human-created. A missing provenance signal does not prove human authorship. Evaluate a local classifier independently before integrating it, and document its false-positive and false-negative behavior.

## Architecture constraints

- Ubuntu 24.04, Apache, MySQL and PHP in one Docker container for this learning assignment.
- Build from a Dockerfile; do not add Docker Compose.
- Raw PHP only; no PHP framework.
- Keep secrets out of source control, Dockerfile instructions, browser code and logs.
- Keep external image processing disabled until access, privacy, terms, and applicable cost are reviewed.

## References

- OpenAI Content Provenance guide: https://developers.openai.com/api/docs/guides/content-provenance
- Candidate local detector for evaluation only: https://github.com/andrezaiats/aidetect

## Status

The source correction has been committed to a feature branch for review. Local lint, source checks, integration tests, and the resulting GitHub Actions run still need verification. No test is claimed to have passed in this note.
