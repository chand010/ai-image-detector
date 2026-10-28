# Classroom Security Review — AI Image Detector

**Scope:** Existing raw-PHP application in Ubuntu 24.04 + Apache + MySQL single-container Docker image. This checklist does not introduce Docker Compose or change the database engine.

## Changes applied

- PHP configuration sets expose_php=Off, disables browser error display, enables error logging, and sets strict session mode and safer session-cookie defaults.
- Apache enables the headers module, reduces server-banner detail, disables TRACE, and sends baseline security headers.
- Existing public document root, disabled directory listing, and denial of access to project files outside public/ remain in place.
- The upload handler already uses server-side Fileinfo MIME detection, a JPEG/PNG/WebP allowlist, image decoding, byte and pixel limits, CSRF validation, and prepared SQL statements.
- Uploaded images are intended to be temporary; preserve cleanup after successful and failed analysis.

## Runtime hardening notes

This project intentionally runs Apache, PHP, MySQL, and Supervisor in one container for the assignment. Do not set the whole container to a non-root user without redesigning MySQL initialization and process supervision. Apache worker processes should run as www-data, and MySQL should run as mysql; privileged Apache/Supervisor master processes remain a residual risk of this single-container layout.

After rebuilding and testing, consider these Docker runtime controls in an isolated test container:

    docker run -d \
      --name ai-image-detector-hardened-test \
      --publish 8081:80 \
      --mount source=ai-image-detector-db-test,target=/var/lib/mysql \
      --security-opt no-new-privileges:true \
      --cap-drop ALL \
      --cap-add NET_BIND_SERVICE \
      ai-image-detector:local

Use a separate test database volume and container name first. Do not replace the currently running container or attach the project’s existing database volume for this test. Confirm MySQL initialization, Apache startup, upload processing, logging, and database persistence before adopting runtime flags. If MySQL or Apache needs additional capabilities, investigate the requirement rather than blindly adding privileges.

A read-only root filesystem is not enabled by this change. It needs a deliberate writable-path design for MySQL data, /run, /tmp, Apache runtime files, and logs; enabling it prematurely can prevent the combined container from starting or functioning.

## Verification checklist

- [ ] Rebuild the image and inspect build output.
- [ ] Run PHP lint and the existing test suite.
- [ ] Verify HTTP response headers on the actual Apache container; the PHP built-in-server smoke test does not load Apache configuration.
- [ ] Confirm X-Powered-By is absent and error details are not shown to visitors.
- [ ] Confirm requests for /Dockerfile, /.env, and project files outside public/ are denied.
- [ ] Upload a harmless text file renamed sample.jpg; it must be rejected. Do not upload or execute a web shell.
- [ ] Test a valid JPEG, PNG, and WebP, plus malformed and over-limit files.
- [ ] Confirm the uploaded temporary file is removed after success and failure.
- [ ] Confirm the database is not published on a host port and credentials are not exposed in logs or Git.
- [ ] Run Trivy or Docker Scout against the newly built image and triage findings.
- [ ] Test container runtime flags using an isolated test volume before modifying the existing container.

## Authorized classmate testing

Only scan applications for which the instructor or owner has explicitly authorized testing and defined the target, time window, and permitted tools. Start with low-impact passive checks and normal HTTP requests. Do not attempt container escape, execute uploaded scripts, dump secrets, run destructive SQL, or use high-volume fuzzing against a classmate’s live app. For upload checks, use harmless invalid files and verify rejection without attempting code execution.

Record each finding with affected endpoint/configuration, reproducible low-impact evidence, impact, severity rationale, recommended fix, and retest result. Redact cookies, tokens, API keys, and personal data from reports.

## Detection limitation

The OpenAI provenance adapter is not a general-purpose AI-image classifier. A missing provenance/watermark signal is not proof that an image was created by a human. The UI must distinguish validation, provenance evidence, unavailable providers, and genuine classifier predictions.
