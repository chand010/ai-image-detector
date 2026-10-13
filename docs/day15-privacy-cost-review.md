# Day 15 — External provenance privacy and cost review

**Decision status: pending.** External image processing remains disabled. This document records what must be checked before connecting the OpenAI Content Provenance API to the application.

## What the official documentation currently says

- The Content Provenance API checks supported OpenAI-associated provenance signals, including C2PA Content Credentials and SynthID; it is not a general-purpose detector for every AI system.
- Default image/audio provenance checks are currently described as free, with usage limits. Higher limits are subject to eligibility and approval. The documented higher-limit program focuses on qualifying public-interest use cases; general commercial verification is outside that program's stated scope.
- The provider states that content submitted to the Content Provenance API is processed for supported signal checks, is not stored unless legally required, and is not used to train its models.
- An organization without endpoint access may receive HTTP 404. HTTP 429 indicates rate limiting. Unsupported or malformed files may return HTTP 400.

Official sources (review again before launch):
- https://developers.openai.com/api/docs/guides/content-provenance
- https://developers.openai.com/api/docs/guides/your-data
- https://help.openai.com/en/articles/8912793-provenance-signals-in-openai-generated-content

## Application privacy requirements before activation

- [ ] Confirm that the API is available to the project’s organization and current use case.
- [ ] Confirm the latest default usage limits, rate limits, and any cost or account restrictions.
- [ ] Review current provider terms and data-handling documentation.
- [ ] Add a clear notice before upload explaining that images will be sent to an external provider for provenance checks.
- [ ] Decide whether the user must actively opt in for each upload; do not send images externally by default until this decision is made.
- [ ] Keep the application's existing 5 MiB limit even though the provider supports larger files.
- [ ] Send only the validated image bytes and necessary MIME type. Do not send filenames, account details, or other unnecessary data.
- [ ] Keep the API key in runtime configuration, never in source control, client-side code, Docker build arguments, database rows, or logs.
- [ ] Use HTTPS, a strict connection/overall timeout, response-size limits where feasible, JSON schema validation, and safe error mapping.
- [ ] Never retry repeatedly or automatically on 404. Retry only transient failures with bounded backoff and respect Retry-After when present.
- [ ] Do not persist the original image. Store only the minimum analysis metadata and signal outcomes needed by the app.
- [ ] Clearly explain that not_detected means no supported signal was found, not that the image is human-made.

## Operational cost assessment

The documented default provenance checks are currently free but limited. This does not guarantee unlimited availability or eligibility for higher limits. Before public deployment, check the live documentation and account dashboard for current limits and confirm whether expected traffic is within those limits. Do not promise a service-level agreement or a commercial entitlement based on the default access alone.

A local classifier avoids sending images to an inference provider if hosted fully on infrastructure we control, but it still has hosting, storage, compute, maintenance, and evaluation costs. It also requires license review and measurement of false positives/false negatives. “Free model weights” does not mean zero total cost.

## Current implementation boundary

`src/Detection/DetectionProviderInterface.php` defines a small provider contract only. No network call is implemented, no API key is required, and the upload handler does not call an external service. The existing result page should continue to say detection is not configured until access, privacy, implementation, and test decisions are complete.

## Go/no-go decision

**Current status: NO-GO for transmitting user images externally by default.** Continue with a disabled-by-default implementation plan. Enable external processing only after the checklist above is reviewed and an explicit product decision is made.

SonarCloud remains pending and is not part of this work.
