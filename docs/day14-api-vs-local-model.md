# Day 14 — External provenance API vs locally hosted classifier

**Project:** AI Image Detector  
**Stack:** Ubuntu, Apache, MySQL, raw PHP, one Dockerfile, no Docker Compose  
**Status:** Decision guide. No detector is connected yet; no accuracy claim is made.

## Executive recommendation

For the first real detection integration, investigate the official OpenAI Content Provenance API as a narrow, well-defined provenance check, provided the project can obtain access and approve sending user images to an external provider. It detects supported OpenAI-associated signals; it is not a universal AI-image classifier.

For a no-per-request-provider-cost path, evaluate a locally hosted open-weight image classifier separately. “Open weights” or “free to download” does not mean every use is license-free. Review the exact model license, training/evaluation data, dependencies, and model card. A local model can run in a separate Python inference service while the user-facing app remains raw PHP, but that increases deployment and maintenance complexity.

Do not implement both at once. First prove the upload flow, result schema, error handling, and privacy decision with one adapter.

## Comparison

| Factor | External provenance API | Local image classifier |
|---|---|---|
| What it answers | Whether supported provenance signals, such as C2PA credentials or supported SynthID, were detected. | Whether the image resembles the classifier's AI/human training categories. |
| Coverage | Limited to signals and formats supported by the chosen provider; not a universal AI detector. | Depends on model training data and evaluation. May generalize poorly to new generators, edits, screenshots, or compression. |
| Access | OpenAI documents POST /v1/content_provenance_checks; an organization without access may receive 404. Confirm account eligibility and current limits before building. | Download model weights and runtime dependencies from the selected repository. Access may require a Hugging Face account or model-specific terms. |
| Cost | The official documentation currently describes default provenance checks as free under usage limits; higher limits require eligibility/approval. Confirm current terms and limits before deployment. | No per-request inference vendor fee when run on your own hardware, but hardware, hosting, power, storage, updates, and engineering have real costs. Hosted GPU/CPU inference is paid. |
| Privacy | Image bytes leave your server and are processed by the provider. Review data handling, retention, terms, region, and consent/disclosure requirements before sending user uploads. | Images can remain on your server if the model is fully local. Protect logs, temporary files, access, and backups. If using a hosted model endpoint, images leave your server just like any external API. |
| License | API terms govern usage; check current terms, access requirements, rate limits, and intended use. | Model weights and code can have different licenses. Check commercial use, attribution, redistribution, and any restrictions before choosing a model. |
| Hardware | Minimal inference hardware on your host; requires outbound HTTPS, DNS, certificate validation, API key storage, and reliable connectivity. | Small classifiers may run on CPU but could be slow; larger models may need substantial RAM and a GPU. Benchmark the chosen model on the actual target machine. |
| Integration with raw PHP | Relatively simple: PHP cURL multipart request, environment-based API key, strict timeout, JSON schema validation, safe error mapping. | PHP cannot directly run every Python model. Recommended architecture is PHP calling a separate local Python service over a private network, or a subprocess for a controlled prototype. A separate service is easier to isolate but adds operations. |
| Reliability | Depends on provider availability, network, quotas, and access. Must handle 400/404/429/5xx and timeouts. | No external inference outage if fully local, but the model process can fail, exhaust memory, or be too slow. You maintain packages and model files. |
| Output interpretation | A detected supported signal is evidence of that signal; not_detected is not evidence that the image is human-created or from another provider. | A model score is not automatically a calibrated probability. Report it as a model estimate unless calibration has been evaluated. |
| Best fit | Provenance-specific check when authorized access is available and external processing is acceptable. | Experimental broad classification when you can evaluate it and operate the required model runtime. |

## Official references

### External provenance API

- API guide: https://developers.openai.com/api/docs/guides/content-provenance
- Provenance signal explanation: https://help.openai.com/en/articles/8912793-provenance-signals-in-openai-generated-content
- API data controls: https://developers.openai.com/api/docs/guides/your-data
- Current provenance pricing/access note: https://help.openai.com/en/articles/8912793-provenance-signals-in-openai-generated-content

The current guide says the API checks supported OpenAI-associated C2PA Content Credentials and SynthID signals. It supports PNG, JPEG, and WebP images up to 50 MiB, but this application intentionally retains its own 5 MiB limit. The API documentation notes that an organization without access may receive 404 and that not_detected does not rule out AI generation. The official Help Center currently describes default checks as free under usage limits and says higher-limit eligibility focuses on qualifying public-interest use cases; general commercial verification is outside that program's scope. Confirm current access and limits before implementation.

### Local classifier

- Hugging Face model search: https://huggingface.co/models?other=ai-image-detection
- Hugging Face Inference Providers pricing (for hosted inference, not local execution): https://huggingface.co/docs/inference-providers/en/pricing
- Hugging Face model inference overview: https://huggingface.co/docs/hub/models-inference

The model search is a starting point, not a quality ranking. Before selecting a specific model, inspect its license, model card, training dataset, test results, last update, dependencies, inference requirements, and suitability for commercial use. Hosted inference pricing can change and free credits are limited and subject to change.

## Recommended architecture

Keep the current app and database. Add a PHP interface such as DetectionProviderInterface and one implementation at a time. A provider result should include:
- provider and check_type
- status: completed, inconclusive, unavailable, or failed
- signals: typed outcomes with short explanations
- optional model_score and the score's meaning
- safe internal error code, never raw credentials or provider response text

The upload handler should validate the image first, call the selected adapter with a strict timeout, store only the minimal result metadata, and discard the temporary original. Never store the API key in Git, browser code, database rows, Docker build arguments, or logs. Configure it at runtime through a secret/environment mechanism.

A local classifier can be a separate Python service while the public web app stays raw PHP. Keep that service private to the container/host network; do not expose an unauthenticated inference endpoint to the public internet. This option requires an explicit architecture decision because the existing Dockerfile is a LAMP learning stack and has not yet been tested with a Python service.

## Practical staged plan

1. Confirm whether the project can access the external provenance API and whether external image processing is acceptable.
2. If approved, implement the provenance adapter only; test detected, not-detected, unsupported image, access-denied, rate-limit, malformed-response, and timeout cases.
3. If access is not available or external processing is rejected, choose one local model only after license and hardware review.
4. Build a representative evaluation set with real photographs, AI images from several generators, edited images, screenshots, and compressed images. Track false positives and false negatives.
5. Display what check actually ran and its limitations. Do not collapse provenance and classifier output into an unjustified “AI / human” certainty score.

## Decision

**Preferred first experiment:** official external provenance API, conditional on access and privacy approval.  
**Fallback/second experiment:** a locally hosted classifier after model-license review and a small CPU/RAM benchmark.

Neither option is connected yet. Keep the result page explicit that detection is not configured until the chosen adapter and tests are complete. SonarCloud remains pending.
