# Day 2 — Threat Model

**Project:** AI Image Detector  
**Date:** 2026-10-13  
**Method:** Identify assets, trust boundaries, threats, mitigations, and tests before implementation.

## 1. Security objectives
1. Accept only supported, valid images.
2. Prevent uploaded content from being executed or served as active content.
3. Limit resource consumption during upload and image parsing.
4. Protect database integrity and application secrets.
5. Minimize retention of user images and metadata; require explicit opt-in for image retention and delete retained originals within 7 days.
6. Avoid misleading users with unsupported AI-detection claims.
7. Fail safely when a detector or database is unavailable.

## 2. Assets and trust boundaries

### Assets
- Uploaded image bytes and metadata.
- Temporary upload storage and processing memory.
- Database records and database credentials.
- Detector credentials/API keys, if an authorized integration is later approved.
- Application source code, Docker image, logs, and configuration.
- User trust in reported results.

### Trust boundaries
- Browser to Apache/PHP: all requests and file data are untrusted.
- PHP to image parsers/detectors: file contents may trigger parser bugs or expensive processing.
- PHP to MySQL: user-derived values must be parameterized.
- Container to host and mounted volumes: permissions and configuration must be constrained.
- Application to external detector: consider network failure, privacy, authentication, and provider terms.

## 3. Threat register

| ID | Threat | Impact | Mitigation | Verification test |
|---|---|---|---|---|
| T-01 | A script or non-image file is renamed to .jpg. | Unsafe content or parser misuse. | Allowlist extensions; server-side Fileinfo MIME check; decode image; never trust client MIME. | Upload a text file renamed fake.jpg; expect rejection. |
| T-02 | Crafted filename contains traversal or special characters. | Overwriting files or path confusion. | Ignore client filename for storage; generate random server-side names. | Try traversal and unusual filenames; verify no path escapes storage. |
| T-03 | Oversized file or excessive image dimensions consume resources. | Memory/CPU exhaustion or outage. | Enforce request and PHP size limits, pixel ceiling, timeouts, and concurrency/rate limits where needed. | Test file above size limit and a very large-dimension image. |
| T-04 | Malformed image triggers decoder failure. | Worker crash or denial of service. | Use maintained libraries, catch errors, set resource limits, keep packages patched. | Upload truncated/corrupt JPEG and PNG samples. |
| T-05 | Upload is stored in a web-accessible executable directory. | Uploaded script execution or exposure of private images. | Store outside web root; restrictive permissions; disable execution in public storage. | Confirm uploaded files cannot be fetched or executed by URL. |
| T-06 | User values are inserted into SQL strings. | SQL injection or database corruption. | PDO prepared statements for all values; least-privilege DB account. | Test SQL metacharacters; verify they are handled as literal values. |
| T-07 | Result page reflects untrusted text without escaping. | Cross-site scripting (XSS). | Escape output with htmlspecialchars using UTF-8; do not render metadata as HTML. | Test markup in filename/metadata; it must display as text. |
| T-08 | Secrets are committed to Git or baked into the image. | Unauthorized access and data exposure. | Runtime configuration/secrets, .gitignore, no secrets in Docker build arguments. | Inspect repository, image configuration, and logs for secrets. |
| T-09 | Images or metadata are retained longer than expected, or saved without consent. | Privacy exposure, unauthorized access, and storage growth. | Retention is off by default; require explicit opt-in; store outside web root with restrictive permissions; set a 7-day expiry and automatic cleanup; clean up after failures too. | Verify no image remains without opt-in and opted-in images are deleted by expiry, including failed-analysis cases. |
| T-10 | Detector is unavailable or times out. | Hung request or fabricated result. | Timeout, bounded retries if justified, clear inconclusive/unavailable status. | Simulate timeout; confirm no positive/negative claim is fabricated. |
| T-11 | Absence of watermark/metadata is treated as proof of human authorship. | Misleading or harmful conclusions. | State limitations; distinguish “not detected” from “human-created”; evaluate classifiers before use. | Review result text for false certainty. |
| T-12 | Logs capture request bodies, images, secrets, or raw metadata. | Sensitive data leakage. | Log event IDs, status, sanitized diagnostics only; restrict access and retention. | Inspect logs after valid and invalid upload tests. |

## 4. Risk priorities

**High priority before public deployment:** T-01 validation, T-03 resource exhaustion, T-05 upload storage, T-06 SQL injection if database writes exist, T-08 secrets, T-09 consent, private storage, and automatic deletion.

**Important before detector integration:** T-04 parser resilience, T-10 timeouts, T-11 honest interpretation, T-12 safe logging.

Risk levels are initial engineering judgments and should be updated after implementation and testing.

## 5. Safe testing rules
- Test only this application or systems you own or have explicit permission to assess.
- Use synthetic or public test images permitted for testing; do not upload confidential images.
- Do not exploit third-party detector services or bypass access controls.
- Keep malicious-file tests local and harmless; never execute uploaded content.
- Record expected result, observed result, and remediation for every failed test.

## 6. References
1. OWASP, **File Upload Cheat Sheet** — allowlists, content validation, generated filenames, storage, permissions, and size limits: https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
2. PHP Manual, **Handling file uploads** — upload error checks, file-size checks, server-side MIME detection, and generated names: https://www.php.net/manual/en/features.file-upload.php
3. PHP Manual, **PDO::prepare** — parameterized SQL values: https://www.php.net/manual/en/pdo.prepare.php
4. Docker Docs, **Dockerfile overview** — build instructions and image configuration: https://docs.docker.com/build/concepts/dockerfile/
5. Docker Docs, **Build best practices** — ephemeral containers, .dockerignore, and one main service per container: https://docs.docker.com/build/building/best-practices/

## Day 2 completion checklist
- [ ] Review functional and non-functional requirements.
- [ ] Explain why extension and browser MIME are not enough.
- [ ] Explain why file size and pixel dimensions both matter.
- [ ] Explain “not detected” versus “human-created”.
- [x] MySQL runs in the same container as Apache/PHP for this learning project; no Docker Compose.
- [x] Image retention is off by default; explicit opt-in only; delete retained images within 7 days.

## 7. Confirmed Day 2 decisions and additional controls

### Single-container LAMP (learning-only)
Apache, PHP, and MySQL will run in one Ubuntu-based Docker container because that is the chosen learning-project constraint. This increases operational complexity and creates a larger failure domain than separate services. Use a process supervisor, explicit service health checks, least-privilege users, and persistent storage for the database. Never bake secrets into the image. This layout should be reconsidered for production.

### Opt-in image retention, maximum 7 days
- Do not retain the original image unless the user explicitly selects a save/retain option and understands the 7-day limit.
- Store retained images outside the web root, under random server-generated names, with restrictive permissions.
- Keep only the generated storage key and expiry timestamp in the database; do not store the original filename or full metadata by default.
- Enforce expiry on the server, not merely in the UI. Add a cleanup routine/job that deletes expired images and reconciles orphaned database records.
- Delete files on successful completion when not retained, and on validation, detector, database, and unexpected errors. Test interrupted requests and cleanup failures as well.
- Protect retained images from cross-user access; do not expose direct public URLs. If accounts are introduced later, enforce ownership checks.
- Document the retention policy on the upload form and avoid logging image bytes, full EXIF data, or sensitive details.
