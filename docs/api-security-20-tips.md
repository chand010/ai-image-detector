# API Security Guide — 20-Tip Checklist

This project adopts the user's 20 API Security Tips guide as the preferred checklist for API-related security decisions. Apply controls proportionately to the existing raw-PHP application; do not add frameworks, Docker Compose, or a new database engine.

## The 20 tips mapped to this project

1. **Strong authentication** — require authentication before exposing private analysis data or administrative functions. The current session-owner token is not a substitute for user authentication if multi-user accounts are introduced.
2. **Access control** — enforce ownership checks on every analysis/history lookup; never rely on an unpredictable ID alone.
3. **HTTPS encryption** — use HTTPS at the deployed TLS termination point. Do not enable HSTS on the HTTP-only Apache virtual host unless HTTPS is guaranteed for the domain.
4. **Sanitize input** — validate request methods, content types, IDs, and all external input. Prefer allowlists and context-aware output escaping; sanitization alone does not replace validation.
5. **Rate limiting** — cap upload frequency, request size, and expensive inference/provider calls. Use a design that remains effective across concurrent requests.
6. **Secure error messages** — show generic errors to clients; log safe diagnostic events without secrets, image contents, or sensitive metadata.
7. **Data encryption** — encrypt data in transit and protect sensitive data at rest where appropriate; keep API keys out of source control and image layers.
8. **Logging and auditing** — log security-relevant outcomes and request/analysis identifiers while excluding tokens, API keys, image bytes, and unnecessary EXIF data.
9. **Throttle login attempts** — apply if login functionality is added; the current prototype does not provide a conventional login system.
10. **API versioning** — introduce versioned paths only if/when a stable external API is exposed; do not create versioning overhead for existing HTML form endpoints without a need.
11. **Security headers** — maintain and test CSP, X-Content-Type-Options, frame protections, and other headers suitable for the deployed app.
12. **CORS configuration** — same-origin by default; allow only specific origins if cross-origin clients are actually required. Never use wildcard origins with credentialed requests.
13. **Token expiration** — set sensible lifetimes and rotation rules for any access tokens introduced. Avoid adding JWT merely because it appears in the guide.
14. **Secure data validation** — validate inputs and outputs against explicit schemas and allowed states; reject malformed detector responses safely.
15. **Safe API documentation** — document authentication, authorization, limits, data retention, error states, and privacy behavior without publishing secrets or internal infrastructure details.
16. **Security testing** — run unit, integration, upload-abuse, access-control, and HTTP security tests; record observed results rather than assuming success.
17. **Disable default errors** — keep display_errors disabled in deployed PHP and use safe server-side logging.
18. **Secure session management** — use strict session mode, HttpOnly and SameSite cookies, Secure cookies under HTTPS, session ID rotation where appropriate, and server-side invalidation on logout if logout is implemented.
19. **CSRF tokens** — preserve and test CSRF checks for browser-session state-changing requests. CSRF is generally not a substitute for API authentication.
20. **Regular updates** — rebuild from maintained base packages, patch dependencies, and scan the built image with Trivy or Docker Scout.

## Existing controls observed in the repository

- Upload form includes a CSRF token; the upload handler checks it with hash_equals.
- Uploads use server-side Fileinfo MIME detection, a JPEG/PNG/WebP allowlist, decoding, a 5 MiB limit, and a 20-megapixel ceiling.
- Database writes use PDO prepared statements.
- The Apache configuration disables directory listing and denies direct access to project files outside the public web root.
- Recent commits add PHP error-display hardening and baseline Apache security headers; these still require a successful rebuild and runtime verification.

## Priority implementation order

1. Verify ownership checks on history and result endpoints.
2. Add and test rate limiting for uploads and detector calls.
3. Verify security headers and HTTPS behavior on the actual deployment.
4. Review session lifecycle, CSRF, safe errors, and logs end-to-end.
5. Run the security test suite and an image vulnerability scan; document pass/fail evidence.

## Project constraints

- Raw PHP; no application framework.
- Ubuntu 24.04 with Apache and MySQL in one container built with a Dockerfile only; no Docker Compose.
- Preserve temporary image processing and cleanup policy.
- Do not configure SonarCloud unless the user explicitly changes that decision.
- The provenance integration is not a general-purpose AI-image classifier. Do not report an AI/not-AI verdict unless a real classifier has been implemented and tested.

## Authorized classroom testing

Test only targets explicitly authorized by the instructor or owner. Prefer low-impact checks and harmless test inputs. Do not execute uploaded scripts, attempt container escapes, retrieve secrets, run destructive SQL, or flood classmates' services. Report reproducible evidence, impact, remediation, and retest status.