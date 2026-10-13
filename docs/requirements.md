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
- Automatic image retention without explicit user opt-in. Images may be retained only when the user explicitly chooses to save them, with a maximum retention period of 7 days.
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
| FR-08 | Delete each uploaded image after analysis unless the user explicitly opts in to saving it. | Default path deletes the image; opt-in path stores it privately and deletes it within 7 days. Cleanup also applies to failed requests. |
| FR-09 | If history is enabled, store minimal metadata using PDO prepared statements. | SQL values are parameterized, not concatenated from user input. |
| FR-10 | Return safe user-facing errors and log diagnostics without secrets or stack traces. | Internal errors do not expose paths or credentials. |

## 4. Non-functional requirements
- **Security:** server-side validation, least-privilege filesystem permissions, non-executable upload storage, safe output encoding, prepared statements, and no secrets committed to Git.
- **Privacy:** image retention is off by default. Only explicitly opted-in images may be retained, in private storage, for no longer than 7 days; document the policy and enforce automatic deletion.
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
- Image retention disabled by default; explicit opt-in required; maximum retention 7 days.
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

Do not store the original filename, full EXIF payload, or user IP by default. Original images may be stored only after explicit opt-in, in private non-web-accessible storage, and must be deleted automatically within 7 days. Keep image files separate from database records; store only a generated storage key and expiry timestamp when retention is opted in. A hash can still be linkable to a known image, so document its purpose and retention. Never store passwords or API secrets in this table.

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

DECISION FOR THIS LEARNING PROJECT: Apache, PHP, and MySQL will run in the same Ubuntu-based container, built from one Dockerfile and without Docker Compose, to satisfy the project requirement. This is a learning-only trade-off: the container needs deliberate process supervision, coordinated startup/shutdown, health checks, and persistent database storage. Keep database credentials in runtime environment configuration, use a least-privilege database account, and do not assume container-local database files survive container replacement unless a persistent volume is configured. For production, separate the database into its own managed service.

## 9. Definition of done for the first prototype
- Upload form and server validation work.
- Valid JPEG/PNG can be inspected safely.
- Invalid and oversized uploads are rejected.
- Images are deleted after analysis by default; explicitly opted-in images are private and automatically deleted within 7 days, including cleanup handling for failed processing.
- UI explains that metadata is not proof and no universal detector is available.
- No unsupported SynthID integration is claimed.
- Docker image builds from the Dockerfile without Compose.
- Setup, limitations, tests, and known risks are documented.

## 10. Confirmed Day 2 decisions

1. **Container layout:** one Ubuntu-based Docker container runs Apache, PHP, and MySQL for this learning project; no Docker Compose. Document and implement process supervision and persistence carefully. This is not the preferred production layout.
2. **Image retention:** off by default. Save an original image only after explicit user opt-in.
3. **Retention duration:** maximum 7 days from upload/save time, followed by automatic deletion.
4. **Storage protections:** retained originals must live outside the web root, with restrictive filesystem permissions and no executable handling. Use random server-generated identifiers, record an expiry timestamp, and never rely only on a UI promise for deletion.
5. **Failure handling:** cleanup must also run after validation errors, detector failures, exceptions, and interrupted processing where feasible. A periodic cleanup job should remove expired files and orphaned records.
