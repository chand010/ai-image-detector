# Day 3 — Project Structure and Implementation Plan

**Project:** AI Image Detector  
**Stack:** Ubuntu base image, Apache, PHP, MySQL, Dockerfile only (no Docker Compose)  
**Current milestone:** secure upload-validation prototype; no AI detector is configured yet.

## Learning goals

By the end of Day 3, the learner should be able to:
- explain the responsibilities of the public web root, application logic, database schema, and private upload storage;
- describe the request path from browser to Apache/PHP;
- explain why client-provided filename, extension, and MIME type are not trustworthy;
- distinguish image validation from AI-origin detection;
- describe the operational trade-off of running Apache/PHP and MySQL in one container.

## Proposed repository layout

```text
ai-image-detector/
├── app/
│   ├── upload.php          # POST handler and validation
│   └── database.php        # PDO connection (later milestone)
├── database/
│   └── schema.sql          # metadata tables (later milestone)
├── docs/
│   ├── day1-research.md
│   ├── requirements.md
│   ├── threat-model.md
│   └── day3-implementation-plan.md
├── public/
│   ├── index.php           # browser-facing upload form
│   └── assets/style.css
├── docker/
│   └── entrypoint.sh       # coordinates services in the single container
├── .dockerignore
└── Dockerfile
```

The public web root must contain only files intended to be served. Uploaded originals belong outside it, for example in `/var/lib/ai-image-detector/uploads`. Never trust an uploaded filename as a path, and never execute or directly serve uploaded files.

## Request flow

1. The browser sends a multipart POST request with an image and a CSRF token.
2. PHP checks the request method, CSRF token, PHP upload error, and configured byte-size limit.
3. Server-side inspection uses Fileinfo to identify the content type, then checks image dimensions and attempts to decode the image with GD.
4. The application accepts only explicitly allowed formats (initially JPEG, PNG, and WebP) and rejects invalid, unsupported, oversized, or excessively large-dimension files.
5. In this first prototype, the image is validated but **not saved**. AI detection and opt-in retention will be added in later milestones.
6. The UI must say that no AI detector is configured and must not claim the image is human-made or AI-generated.

## Security decisions

- **Do not trust the browser:** filename, extension, and client-supplied Content-Type can be forged.
- **Allowlist formats:** accept JPEG, PNG, and WebP only in this prototype; verify actual content server-side.
- **Limit bytes and decoded pixels:** a small compressed file can expand into a huge image and exhaust memory/CPU.
- **CSRF protection:** use a session token for the upload POST.
- **Output encoding:** escape any dynamic text rendered in HTML.
- **No public uploads:** future originals will be stored outside Apache's document root using a random server-generated storage key.
- **Retention consent:** future storage is disabled by default; save only after explicit consent, record expiry, and delete by expiry no later than seven days. Handle failure paths and orphan files.
- **Secrets:** never commit passwords, API keys, private images, or real user data. Configure runtime secrets outside source control.
- **Resource limits:** configure PHP/web-server request limits as well as application checks. Application checks alone do not prevent Apache/PHP from receiving a large request.

## Single-container LAMP design

The learning requirement is one Docker container with Ubuntu, Apache/PHP, and MySQL, built from one Dockerfile and run without Compose. Docker generally recommends one main service per container; combining a web server and database increases lifecycle, logging, backup, startup, and recovery complexity. We accept that trade-off for this constrained learning exercise.

The container must supervise processes and stop cleanly. Persistent database data must be mounted to a named Docker volume at `/var/lib/mysql`; the writable container layer is not a safe database persistence strategy. Private image storage, when implemented, should also use a deliberate persistence and cleanup policy.

## Detection-method reality check

SynthID embeds an imperceptible watermark into supported AI-generated media. Google's official information describes SynthID verification for supported Google AI content, but a public verification portal is not automatically a developer API. Do not scrape the portal or invent an API endpoint. Before integrating any detector, verify its current official developer documentation, authentication requirements, permitted use, file limits, and privacy terms.

A missing SynthID watermark does **not** prove that an image was made by a human. Other image generators may not embed SynthID, and edits can affect detection. The interface should use cautious outcomes such as “watermark detected,” “not detected by this method,” or “inconclusive,” according to the detector's documented response.

## Day 3 practical tasks

1. Review the directory layout and explain why uploads are outside `public/`.
2. Review `public/index.php` and `app/upload.php` in this commit.
3. Test a normal JPEG, PNG, and WebP image.
4. Test a text file renamed to `.jpg`, a corrupted image, an unsupported format, an oversized file, and a very large-dimension image.
5. Confirm the application does not save any uploaded file in this milestone.
6. Explain why “valid image” is not the same as “AI-generated image.”

## Acceptance criteria

- Uploads require a POST request and a valid CSRF token.
- The server enforces the configured file-size cap and checks actual image content.
- Unsupported or corrupt images are rejected safely.
- Dynamic output is HTML-escaped.
- The prototype never claims to have detected AI origin.
- No uploaded original is retained in this milestone.

## References

- Google DeepMind, SynthID overview: https://deepmind.google/models/synthid/
- Google DeepMind, identifying AI-generated images with SynthID: https://deepmind.google/blog/identifying-ai-generated-images-with-synthid/
- Google Gemini Help, image/video/audio verification and limitations: https://support.google.com/gemini/answer/16722517
- Docker, running multiple processes in a container: https://docs.docker.com/engine/containers/multi-service_container/
- Dockerfile reference: https://docs.docker.com/reference/dockerfile
- PHP, `imagecreatefromstring()`: https://www.php.net/manual/en/function.imagecreatefromstring.php
- PHP, file upload handling: https://www.php.net/manual/en/features.file-upload.php
- PHP, Fileinfo functions: https://www.php.net/manual/en/book.fileinfo.php
