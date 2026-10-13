# Day 2 — System Requirements

**Project:** AI Image Detector  
**Status:** Requirements draft for review  
**Date:** 2026-10-13

## 1. Purpose
Build a web application that accepts an image and provides a cautious, evidence-based analysis of whether it may have been AI-generated. It must state which checks actually ran and must not present uncertain signals as proof.

## 2. Scope

### Included in the first learning version
- A simple HTML upload page served by Apache and implemented in raw PHP.
- Server-side upload validation.
- JPEG and PNG support initially; add formats only after tests and library support are confirmed.
- Server-side MIME detection, file-size limits, and image dimension/decoding checks.
- A detection-service boundary for a documented, authorized watermark detector or evaluated ML classifier.
- A result page distinguishing checks performed from unavailable or inconclusive checks.
- Minimal optional MySQL records using PDO prepared statements.
- An Ubuntu-based Dockerfile, without Docker Compose.
- Documentation, repeatable manual tests, and security checks.

### Not included until explicitly approved
- A claim that the app detects every AI-generated image.
- Scraping or reverse-engineering private SynthID endpoints.
- Permanent storage of uploaded images by default.
- User accounts, payments, bulk uploads, or an admin dashboard.
- Training a new ML model from scratch.
- Treating a detector result as forensic proof.

## 3. Functional requirements

| ID | Requirement | Acceptance check |
|---|---|---|
| FR-01 | Show an upload form for allowed image extensions. | Form lists JPEG and PNG; server validation still applies. |
| FR-02 | Reject missing uploads, upload errors, oversized files, and unsupported types. | Each invalid case returns a clear error and does not start detection. |
| FR-03 | Validate file type using server-side inspection, not the browser MIME value. | A renamed text file is rejected. |
| FR-04 | Decode or inspect the image and enforce dimension/pixel limits. | Corrupt and extremely large-dimension samples are rejected safely. |
| FR-05 | Use a random server-side identifier, not the original filename as a path. | Path traversal cannot control storage paths. |
| FR-06 | Run only detection checks that are actually implemented and available. | Result names each check and reports unavailable checks honestly. |
| FR-07 | Present cautious output such as “evidence found”, “no supported watermark detected”, or “inconclusive”. | Absence of a watermark is never equated with human authorship. |
| FR-08 | Remove temporary uploads after analysis unless a retention policy is explicitly added. | Successful and failed requests clean up temporary files. |
| FR-09 | If history is enabled, store minimal metadata using PDO prepared statements. | SQL values are parameterized, not concatenated from user input. |
| FR-10 | Return safe user-facing errors and log diagnostics without secrets or stack traces. | Internal errors do not expose paths or credentials. |

## 4. Non-functional requirements
- **Security:** server-side validation, least-privilege filesystem permissions, non-executable upload storage, safe output encoding, prepared statements, and no secrets committed to Git.
- **Privacy:** do not retain originals by default; document stored metadata and deletion policy.
- **Reliability:** invalid input fails safely; detector outages do not crash upload handling.
- **Usability:** explain results in plain language and give a useful next step.
- **Maintainability:** separate upload validation, image inspection, detection, persistence, and presentation into small raw-PHP components.
- **Portability:** provide a Dockerfile, .dockerignore, setup instructions, and documented configuration variables.
- **Observability:** log concise errors without image contents, credentials, or unnecessary personal data.
- **Testability:** include valid, invalid, oversized, malformed, and ambiguous test cases.

## 5. Initial configurable limits
These are conservative prototype defaults, not universal standards:
- Maximum upload size: 5 MiB.
- Allowed types: JPEG and PNG.
- Maximum dimension: 6,000 pixels on either side, plus a configurable total-pixel ceiling.
- One image per request.
- No permanent image retention.
- Detector timeouts must be bounded.

Enforce limits in PHP and at the web-server/container layer where applicable. Pixel limits should be checked before expensive processing where possible, while recognizing that decoding itself can consume resources.

## 6. Data design
If history is enabled, store only what is needed:
- id: random or database-generated identifier.
- created_at: analysis time.
- detector_version: version/name of the detector actually used.
- status: completed, inconclusive, or failed.
- summary: short, bounded, non-sensitive result.
- file_sha256 (optional): hash for deduplication/integrity only if justified.

Do not store the original filename, original image, full EXIF payload, or user IP by default. A hash can still be linkable to a known image, so document its purpose and retention. Never store passwords or API secrets in this table.

## 7. Detection-result contract
Every result should include:
- status: completed, inconclusive, or failed.
- checks: list of checks that ran.
- evidence: short explanation of observed signals.
- limitations: what the result cannot establish.
- detector_version: implementation/version used.
- confidence: only if the detector provides a calibrated and documented score; otherwise omit it.

Suggested UI language:
- “A supported watermark was detected.”
- “No supported watermark was detected. This does not prove the image is human-created.”
- “Metadata contains clues, but metadata alone cannot determine authorship.”
- “The detector is unavailable, so no AI-generation conclusion was made.”

## 8. Deployment decision: no Docker Compose
The project must use a Dockerfile and must not depend on Docker Compose.

Docker guidance generally recommends one main service per container. The original project requirement asks for LAMP in a Docker container, so confirm whether MySQL must run inside the same container or whether the app container may connect to a separately managed MySQL server. For maintainability and safer production operation, the recommended deployment is Apache/PHP in the app container and MySQL as a separately managed service, started/configured without Compose. If assessment strictly requires MySQL inside the same container, document this as a learning-only trade-off and use a proper process supervisor.

## 9. Definition of done for the first prototype
- Upload form and server validation work.
- Valid JPEG/PNG can be inspected safely.
- Invalid and oversized uploads are rejected.
- Temporary files are cleaned up on success and failure.
- UI explains that metadata is not proof and no universal detector is available.
- No unsupported SynthID integration is claimed.
- Docker image builds from the Dockerfile without Compose.
- Setup, limitations, tests, and known risks are documented.
