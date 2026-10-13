# Day 1 Research — AI Image Detector

**Project:** AI Image Detector  
**Date:** 2026-10-13  
**Stack constraints:** Ubuntu + Apache + MySQL + PHP (LAMP), raw PHP only, one Dockerfile, no Docker Compose.

## 1. Project goal

Build a web application that accepts an image and reports evidence that may help assess whether it was generated or edited by AI. The app must not promise perfect certainty. It should explain what was checked, the result, and the limitations.

## 2. What does AI-image detection mean?

There is no single universal test that reliably identifies every AI-generated image. Different tools can produce different signals:
- **Provenance/watermark check:** looks for a watermark or content credential associated with a supported generation system.
- **Metadata inspection:** reads fields such as software, timestamps, and camera information. Metadata can be absent, edited, or stripped, so it is only supporting evidence.
- **ML classifier:** estimates whether an image resembles examples in its training data. It can make false-positive and false-negative predictions, especially on unfamiliar models, edited images, screenshots, or compressed images.

The UI should say **“signals consistent with AI generation”**, not make an unsupported definitive claim like “100% AI”.

## 3. SynthID research

Google DeepMind describes SynthID as a watermarking approach that embeds imperceptible signals into supported AI-generated media. Its documentation says users can check certain Google AI images and videos through Gemini, Search, and Chrome, and describes a SynthID Detector portal.

**Important integration finding:** an end-user portal is not automatically a developer API or an embeddable SDK. Before implementing direct integration, verify official developer documentation, access requirements, terms, and supported input types. Do not scrape the portal, reverse-engineer private endpoints, or claim that this app supports SynthID unless we have a documented and authorized integration.

SynthID is not a universal detector for all AI image generators. If no watermark is found, the correct result is **“No supported watermark detected”**, not “human-created”.

## 4. Proposed detection strategy

For the first version, use a transparent, staged pipeline:

1. **Secure file validation** — reject malformed, oversized, or unsupported uploads.
2. **Basic image inspection** — obtain image dimensions and a server-detected MIME type; inspect available metadata only as a clue.
3. **Detection adapter interface** — keep any future official watermark service or ML classifier behind a separate component so it can be added without rewriting upload handling.
4. **Honest result wording** — show checks performed, evidence found, and uncertainty.
5. **Optional MySQL history** — store minimal analysis metadata (timestamp, safe result summary, generated random ID). Avoid storing original images by default unless there is a clear need and retention policy.

Do not label metadata alone as an AI detector. The first milestone can be a secure upload and inspection prototype while we validate which detector can be integrated legitimately.

## 5. Security requirements for image uploads

Treat every upload as untrusted input.

- Set a small maximum upload size and enforce it on both the application and PHP configuration.
- Check the upload error code and verify the temporary upload with is_uploaded_file().
- Do not trust the browser-supplied filename or $_FILES['...']['type'].
- Detect the file type server-side (for example with Fileinfo) and verify it is an allowed image format.
- Attempt to decode/read the image using a trusted image library or image-inspection function; reject malformed files.
- Generate a random server-side filename. Never use a user-supplied path.
- Store uploaded files outside the web root, or in a non-executable directory; never allow PHP execution in the upload location.
- Apply request limits and timeouts; consider decompression bombs and unusually large dimensions, not only file size.
- Escape all displayed user-controlled text. Use PDO prepared statements for database values.
- Do not put secrets, passwords, database dumps, or real user images in Git.
- Delete temporary images after analysis unless the user has explicitly chosen retention.

These controls reduce risk but do not replace testing or deployment hardening.

## 6. Initial architecture

    Browser
      |
      | multipart/form-data
      v
    Apache + raw PHP
      |
      +--> Upload validation --> Temporary/private storage
      |
      +--> Image inspection --> Detection adapter (future official API or classifier)
      |
      +--> Result formatter --> Browser
      |
      +--> Optional PDO/MySQL record (minimal metadata only)

    Dockerfile --> Ubuntu base + Apache + PHP extensions + MySQL client/driver

**Architecture note:** MySQL is normally a separate service in production. The project constraint asks for an Ubuntu-based LAMP container and no Docker Compose, so we must decide whether the learning prototype runs MySQL in the same container or connects to an external MySQL server. Running multiple services in one container is less maintainable; document the trade-off and keep database configuration external. We will settle this before writing the Dockerfile.

## 7. Initial repository plan

    ai-image-detector/
    ├── README.md
    ├── docs/
    │   ├── day1-research.md
    │   ├── requirements.md
    │   └── threat-model.md
    ├── public/
    │   └── index.php
    ├── src/
    │   ├── UploadValidator.php
    │   ├── ImageInspector.php
    │   └── DetectionService.php
    ├── storage/              # runtime only; do not commit uploads
    ├── tests/
    ├── Dockerfile
    ├── .dockerignore
    └── .gitignore

This is a proposed layout, not code already implemented. We will add files incrementally and test each step.

## 8. Day 1 practical tasks

- [ ] Read the official SynthID page and write down which detection options are publicly described.
- [ ] Distinguish an end-user portal from a documented API/SDK; do not assume one exists.
- [ ] Explain three limitations of metadata-based detection.
- [ ] Write five upload-related threats and one mitigation for each.
- [ ] Decide whether the prototype needs to retain uploaded images or only return a result.
- [ ] Answer the review questions below.

### Review questions

1. Why does “no SynthID watermark found” not prove that an image is human-created?
2. Why is the browser-provided MIME type not sufficient for validating an upload?
3. What security benefit comes from storing uploaded files outside the web root?
4. Why should the UI communicate uncertainty rather than claim perfect accuracy?
5. What must be verified before integrating a third-party detector?

## 9. Sources

1. Google DeepMind, **SynthID** — overview, supported media, and user-facing detection options: https://deepmind.google/models/synthid/
2. Google DeepMind, **Identifying AI-generated images with SynthID** — watermark approach and known limitations: https://deepmind.google/blog/identifying-ai-generated-images-with-synthid/
3. PHP Manual, **Handling file uploads**: https://www.php.net/features.file-upload.php
4. PHP Manual, **POST method uploads**: https://www.php.net/manual/en/features.file-upload.post-method.php
5. PHP Manual, **Common pitfalls**: https://www.php.net/manual/en/features.file-upload.common-pitfalls.php
6. Docker Docs, **Build best practices**: https://docs.docker.com/build/building/best-practices/
7. Docker Docs, **Dockerfile overview**: https://docs.docker.com/build/concepts/dockerfile/

## Day 1 completion criteria

Day 1 is complete when the developer can explain the detection limitations, list upload threats and mitigations, and describe the planned architecture. Do not start claiming real AI-detection capability until an actual detector has been integrated and evaluated with a test dataset.
