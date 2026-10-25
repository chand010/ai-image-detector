# Day 24 — OpenAI Content Provenance API evaluation

**Review date:** 24 October 2026  
**Implementation state:** provider integrated behind explicit consent and server-side configuration; external requests remain disabled by default. No live API request was made during this review.

## Learning goals

- Distinguish provenance verification from general AI-image classification.
- Read the API request and response contract.
- Apply privacy-by-default rules to external processing.
- Use CI evidence to diagnose and fix the Docker/MySQL integration race.

## Official API contract

The Content Provenance API accepts a single multipart `file` at:

```http
POST https://api.openai.com/v1/content_provenance_checks
Authorization: Bearer <OPENAI_API_KEY>
```

Supported image formats are PNG, JPEG, and WebP. The provider documentation allows files up to 50 MiB, but this application intentionally retains its stricter 5 MiB upload limit. The API responds synchronously; it does not require a background job or polling.

Image results may contain:
- `c2pa`: Content Credentials / signed manifest signal. Fields can include `outcome`, `validation_state`, `issuer`, `model`, and `generated_at`.
- `synthid`: supported embedded watermark signal. Fields can include `outcome`, `model`, and `generated_at`.

The result entries are independent. The API may omit checks that do not apply. Read the C2PA issuer and validation state before attributing a source.

Documented failure cases include:
- `400`: malformed, unsupported, or blocked file.
- `404`: the organization does not have access to the endpoint.
- `429`: rate limit exceeded.

Official sources:
- [Content Provenance guide](https://developers.openai.com/api/docs/guides/content-provenance)
- [Create a content provenance check — API reference](https://developers.openai.com/api/reference/resources/content_provenance_checks/methods/create)
- [OpenAI provenance signals, limits, and privacy](https://help.openai.com/en/articles/8912793-provenance-signals-in-openai-generated-content)
- [Google SynthID Detector portal](https://synthid.com/)

## Suitability and limitations

The API checks supported OpenAI provenance signals, including supported C2PA credentials and SynthID watermarks. It is **not** a general-purpose AI-image detector and does not detect every image created by other companies' models.

Interpret results carefully:
- `detected` is evidence of the specific supported signal, not a complete history of the file.
- `not_detected` means no supported signal was found; it is not proof of human authorship.
- Cropping, compression, format conversion, metadata removal, legacy models, and unsupported generation paths can cause a signal to be absent or degraded.
- Invalid C2PA credentials must not be treated as trustworthy provenance evidence.

Google's SynthID Detector portal is a manual verification workflow. Do not scrape or automate that portal as a substitute for a public API. The OpenAI API's supported SynthID check is a separate, documented programmatic route.

## Cost, access, and privacy

The current OpenAI Help Center says Content Provenance API checks are free under default usage limits. Limits are strict. Higher limits require an application and case-by-case review; approval is not guaranteed, and the stated higher-limit program does not cover general commercial fraud-prevention/authentication or resale use cases. Do not assume a production quota from a successful small evaluation.

OpenAI says submitted content is processed to check for supported provenance signals, is not stored unless legally required, and is not used to train its models. Nevertheless, sending a file to an external service is a data transfer and must be disclosed to users.

## Application safeguards and decision

- Raw PHP remains in use; no framework has been added.
- Ubuntu 24.04 + Apache + MySQL remain in one Dockerfile-built container; no Docker Compose.
- The provider uses a fixed HTTPS endpoint, bearer authentication, TLS verification, no redirects, connection/overall timeouts, and a bounded response body.
- An external request requires both the per-upload consent checkbox and `ENABLE_EXTERNAL_PROVENANCE=true`.
- The server flag remains disabled by default. The API key is server-side only.
- The application keeps the 5 MiB file limit, validates image MIME/dimensions/decoding before analysis, and stores only metadata and normalized results.
- The PHP temporary upload is explicitly unlinked after validation/analysis, including when the provider is not requested or fails.
- No original-image retention option is implemented under the current policy.
- SonarCloud configuration was not changed as part of this work.

## CI finding and correction

The first Docker/MySQL integration run returned HTTP 503 when the test uploaded its image too soon after Apache became available. Diagnostic container logs then exposed a separate root cause: unescaped backticks inside an unquoted shell heredoc in `docker/init-database.sh` were executed as shell command substitution. This broke the database initialization SQL and prevented creation of the application user's grants.

The initializer now uses validated database identifiers without shell-active backticks. The Docker smoke test also waits for the restricted application account to query the actual schema before uploading, and prints container logs on any failure.

## Verification record

GitHub Actions run [#71](https://github.com/chand010/ai-image-detector/actions/runs/40036677518) completed successfully on commit `e00c5aa6bcce8a4dbfff46caf1180130e46ce9ef`. It passed:
- PHP syntax lint
- Source-level security assertions
- Provenance response mapper tests
- Disabled-provider test
- HTTP security smoke tests
- Docker/MySQL integration smoke test

Run [#72](https://github.com/chand010/ai-image-detector/actions/runs/40036873249) also completed successfully after the README update. These runs verify the code present at those commits; any subsequent code change must be tested again. No live OpenAI API request or penetration test has been performed.

## Next practical task

1. Review the result page's rendering of C2PA and SynthID signals.
2. Add offline mapper tests for contradictory or malformed C2PA responses.
3. Keep the provider disabled until access, quota, intended use, and user-facing disclosure have been approved.
4. If a live smoke test is later authorized, use a non-sensitive image and verify that the temporary file is gone afterwards.
