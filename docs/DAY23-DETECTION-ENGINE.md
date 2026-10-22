# Detection engine evaluation — Day 23

**Status:** evaluation and response-mapping stage. The production upload flow is not yet connected to a classifier. Do not describe this branch as a working end-to-end detector.

## Learning goals

By the end of this task, you should be able to:

1. Explain the difference between file validation, provenance verification, and statistical AI-image classification.
2. Run a candidate detector independently from the existing PHP/MySQL container.
3. inspect its JSON response and map it into a stable application result.
4. Explain why a probability score is not proof of authorship.
5. Keep the detector disabled until the API, resource use, and local-network configuration have been tested.

## Research findings (checked 22 October 2026)

### SynthID and provenance

Google's SynthID is a watermarking technology for supported AI-generated media. Google announced on **7 October 2026** that SynthID Detector access was expanding globally in English, including supported content from Google and partners. The public web checker is useful for manual evaluation, but this research did not establish a general public API that this PHP app can call directly.

- Google DeepMind announcement: https://blog.google/innovation-and-ai/models-and-research/google-deepmind/synth-id-ai-content/
- Public checker: https://synthid.com/

OpenAI's Content Provenance API checks supported OpenAI provenance signals, including C2PA Content Credentials and SynthID. It is not a universal detector for every AI model. A `not_detected` result means supported evidence was not found; it does not prove human authorship. API access and terms must be reviewed before enabling any external processing.

- Official guide: https://developers.openai.com/api/docs/guides/content-provenance
- C2PA specification: https://spec.c2pa.org/specifications/specifications/2.1/specs/C2PA_Specification.html

### Local classifier candidate: aidetect

Repository: https://github.com/andrezaiats/aidetect

The project describes a CPU-based image-forensics detector with an optional ONNX CLIP probe. Its API is documented as:

- `GET /health` — service status and mode.
- `GET /model` — loaded model information.
- `POST /analyze` — multipart image upload using field name `file`.
- Successful analysis returns fields including `probability`, `ai_score`, `verdict`, `confidence`, and `mode`.

The repository documents important limitations. The default classical-feature scoring is not a verdict, and the optional CLIP probe is off by default. We must record the actual mode returned by the service and evaluate it on known images before interpreting scores.

## Architecture decision

Keep the existing project as raw PHP + Apache + MySQL in one Ubuntu Docker container, built from the existing Dockerfile. Do not add Docker Compose.

For isolated evaluation, run the detector in a separate container. When integration is ready, attach both containers to a user-created Docker network and use the internal hostname `aidetect`; do not expose the detector API publicly. The PHP application must send an image only to a local, allowlisted detector host. The original image must remain temporary and must not be written to MySQL or permanent application storage.

The application should remain usable when the classifier is disabled or unavailable. In that case, show “unavailable / inconclusive,” never “human-made.”

## Practical tasks

### 1. Build the detector independently

In a terminal, clone the candidate into a separate folder and inspect its README and Dockerfile:

~~~bash
git clone https://github.com/andrezaiats/aidetect.git
cd aidetect
docker build -t aidetect:local .
~~~

Start it bound to loopback for manual testing:

~~~bash
docker run -d --name aidetect-test \
  -p 127.0.0.1:8000:8000 \
  -e AIDETECT_MAX_CONCURRENCY=1 \
  aidetect:local
~~~

Check health and mode:

~~~bash
curl --fail --silent --show-error http://127.0.0.1:8000/health
curl --fail --silent --show-error http://127.0.0.1:8000/model
~~~

Test one known image:

~~~bash
curl --fail --silent --show-error \
  -F 'file=@sample.jpg;type=image/jpeg' \
  http://127.0.0.1:8000/analyze
~~~

Use a non-sensitive image. Record the real JSON output, the model mode, CPU/memory use, and any error. Do not invent a result if the service fails.

### 2. Evaluate a small test set

Use separate known-origin samples: real camera photos, AI-generated images, and edited/recompressed images. Record:

| Sample | Known source | Returned mode | Probability | Verdict | Notes |
|---|---|---|---:|---|---|
| A | Camera photo | actual output | actual output | actual output | Record observed behavior |
| B | AI-generated image | actual output | actual output | actual output | Record observed behavior |
| C | Edited/recompressed image | actual output | actual output | actual output | Record observed behavior |

A handful of images only tests that the service operates; it does not establish accuracy. A meaningful benchmark needs a larger balanced dataset, a held-out test split, and per-source results.

### 3. PHP integration checkpoint

This branch adds a response mapper and unit-test fixture for the expected `aidetect` JSON fields. It is not yet an end-to-end provider. Before connecting it to `public/upload.php`, the next implementation step is a provider with these controls:

- Disabled unless `ENABLE_LOCAL_CLASSIFIER=true`.
- Local URL allowlist only; reject arbitrary hosts and redirects.
- Short connection timeout, bounded response size, and a maximum request duration.
- Strict JSON and probability/verdict validation.
- Generic user-facing errors; never expose upstream internals or image contents in logs.
- Save only result metadata; never persist the image bytes.
- Keep failed/unavailable checks separate from a completed low-AI-score result.

Then add a database migration for probability, model mode, and error status; update the result/history pages; and add HTTP/Docker integration coverage. Do not claim the engine is integrated until these steps and the runtime tests pass.

## Tests added in this branch

Run:

~~~bash
php -l src/Detection/LocalClassifierResponseMapper.php
php -l tests/local_classifier_response_mapper.php
php tests/local_classifier_response_mapper.php
~~~

These tests cover response mapping only. They do not test a running API, detector accuracy, the PHP upload flow, Docker networking, or the full application. CI and end-to-end checks remain to be verified.


## Progress update — 23 October 2026: local provider scaffold

The branch now includes `src/Detection/LocalClassifierProvider.php`. It is a guarded HTTP adapter for the `aidetect` service, with the destination fixed to `http://aidetect:8000/analyze` on a private user-defined Docker network. The host is not user-configurable, redirects are disabled, the connection and total time are bounded, the response is capped at 64 KiB, and the provider is disabled unless `ENABLE_LOCAL_CLASSIFIER=true`.

The new `tests/local_classifier_provider_disabled.php` checks the disabled-by-default behavior and rejects unsupported MIME or missing input before making an HTTP request.

Run these checks:

~~~bash
php -l src/Detection/LocalClassifierProvider.php
php -l tests/local_classifier_provider_disabled.php
php tests/local_classifier_provider_disabled.php
php -l src/Detection/LocalClassifierResponseMapper.php
php tests/local_classifier_response_mapper.php
~~~

These tests cover PHP syntax, guard behavior, and response mapping only. They do not call a live detector, test Docker networking, connect the provider to `public/upload.php`, or prove classification accuracy. The provider is **not yet integrated into the upload flow**.

### Next integration checkpoint

1. Start the detector on a private Docker network with the name `aidetect`, without publishing its port.
2. Connect the existing LAMP container to that network.
3. Wire the provider into the validated upload flow only when explicitly enabled.
4. Add database fields/migration for detector status, score, confidence, and mode; update result/history pages.
5. Add an end-to-end Docker test and record the actual run output.
6. Keep external provenance disabled; keep the original image temporary and do not store image bytes.
