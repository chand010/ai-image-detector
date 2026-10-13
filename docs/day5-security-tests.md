# Day 5 — Local Security Test Plan

**Scope:** Test only the local AI Image Detector container you own. Do not send these tests to third-party systems.
**Current implementation:** `public/upload.php` validates an image but does not save it and does not detect AI origin.

## Before testing

Start the container and confirm the home page loads. Use disposable, non-confidential test files. Record the expected status, observed status/message, and pass/fail for each test.

## Test cases

| ID | Test | Expected result | What it checks |
|---|---|---|---|
| D5-01 | Open the home page and submit a small valid JPEG. | HTTP 200 and a validation message; it must say no file was saved and AI detection is not configured. | Happy path and honest result language. |
| D5-02 | Submit a valid PNG or WebP image. | HTTP 200 if the installed decoder supports it. | Allowed MIME types and decoder support. |
| D5-03 | Submit a plain text file renamed `fake.jpg`. | Rejected; no validation success. | Server-side MIME checks rather than extension trust. |
| D5-04 | Submit a corrupt or truncated image. | Rejected safely with a generic message; no stack trace. | Decoder failure handling. |
| D5-05 | Submit a file larger than 5 MiB. | Rejected by PHP, the web server, or application with a safe message. | Request-size enforcement. |
| D5-06 | Submit a valid image whose pixel count exceeds 20 million. | Rejected before image decoding. | Dimension/pixel ceiling. |
| D5-07 | Submit a POST without a CSRF token or with an incorrect token. | HTTP 403. | CSRF enforcement. |
| D5-08 | Request `/upload.php` using GET. | HTTP 405 with `Allow: POST`. | HTTP method restriction. |
| D5-09 | Request a known project file outside `public/`, such as `/docs/day3-implementation-plan.md`. | Not served as a public project file. | Web-root isolation. |
| D5-10 | Inspect application and container logs after failures. | No PHP stack traces, credentials, or uploaded image contents. | Safe error handling and logging. |

## How to test CSRF in a browser

1. Open the upload form and use browser developer tools or a local HTTP client to inspect the form.
2. Submit a request without the `csrf_token` field, then submit one with an incorrect token.
3. Confirm both are rejected with HTTP 403.
4. Do not share a real session cookie or CSRF token publicly.

A CSRF token is not a substitute for authentication, authorization, secure cookies, or upload validation.

## Generate a harmless oversized-dimension test image

If Python and Pillow are installed locally, you can generate a synthetic image with 5,000 × 5,000 pixels (25 million pixels). This file may compress well, so it specifically exercises the pixel-count limit rather than the 5 MiB byte limit.

```python
from PIL import Image

image = Image.new("RGB", (5000, 5000), "white")
image.save("test-oversized-dimensions.png")
```

The expected response is a safe rejection for excessive dimensions. Do not repeatedly submit large files or run a high-volume stress test.

## Results record

Copy and complete this table in your notes:

| Test ID | Observed status/message | Pass/Fail | Follow-up |
|---|---|---|---|
| D5-01 | | | |
| D5-02 | | | |
| D5-03 | | | |
| D5-04 | | | |
| D5-05 | | | |
| D5-06 | | | |
| D5-07 | | | |
| D5-08 | | | |
| D5-09 | | | |
| D5-10 | | | |

## Important current limitations to track

- The app does not yet use MySQL for upload history.
- The app does not retain original images; this matches the current prototype, but opt-in seven-day retention is not implemented yet.
- There is no configured AI-origin detector or SynthID integration.
- The current Docker configuration has not been independently rebuilt and security-tested by this document's author; run the checks in your environment and record actual outcomes.
- A production deployment needs TLS, secure session-cookie settings, request/concurrency limits, service health checks, database initialization with a least-privileged account, backups, and a reviewed secrets strategy.

## Completion criteria

Day 5 is complete when the learner records outcomes for all ten tests, explains any failure, and does not label an image as human-created merely because no supported AI watermark was detected.
