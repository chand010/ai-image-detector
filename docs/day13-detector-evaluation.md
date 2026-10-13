# Day 13 — Detector research and integration decision

**Status:** Research and interface planning only. No AI detector has been connected, and no detection accuracy is claimed.

## 1. Why use separate evidence sources?

A single binary classifier cannot reliably identify every AI-generated image. A model's output is a prediction influenced by its training data; it is not proof of authorship. Provenance credentials and watermark checks are different: they can provide positive evidence for supported generation systems, but a missing signal does not establish that an image was made by a human.

The first version should report each check independently and allow an `inconclusive` result.

## 2. Candidate approaches

### A. Content provenance verification

OpenAI documents a Content Provenance API for checking supported images and audio for signals such as a trusted C2PA manifest or supported SynthID watermark. The documented result distinguishes a detected signal from one that was not detected; a missing signal is not proof of human authorship. API access, eligibility, limits, cost, supported formats, and terms must be confirmed before implementation.

Official references:
- OpenAI Content Provenance API: https://developers.openai.com/api/docs/guides/content-provenance
- OpenAI provenance explanation: https://help.openai.com/en/articles/8912793-provenance-signals-in-openai-generated-content

**Pros:** evidence tied to supported provenance signals; PHP can call a documented HTTP API without changing the main application stack.
**Cons:** coverage is limited to supported signals; external service availability, access requirements, image privacy, request limits, and possible costs must be assessed.

### B. Image-classification model

A public Hugging Face model can estimate whether an image resembles its training categories. For example, model cards describe classifiers trained on CIFAKE or other datasets, and some explicitly warn that accuracy may vary on newer generators, compression, or out-of-domain images.

References to evaluate, not endorsements:
- CapCheck classifier and limitations: https://huggingface.co/aaronkantrowitz/ai-image-detection
- Newer AI-vs-human classifier and limitations: https://huggingface.co/aaronkantrowitz/ai-human-generated-image-detection
- Hugging Face AI-image-detection model index: https://huggingface.co/models?other=ai-image-detection

Before choosing one, verify its current license, model weights and inference dependencies, training/evaluation datasets, model age, CPU/RAM requirements, and whether its benchmark includes generators and photographs representative of our users. Report the model's score as a model score, not as a calibrated probability unless calibration has been evaluated.

**Pros:** can offer a broad learned signal when locally hosted or called through an authorized provider.
**Cons:** may produce false positives/negatives; newer generators, edited images, screenshots, and compressed files may behave differently; model hosting adds cost and operational complexity.

### C. Metadata inspection

Metadata may contain software or provenance clues, but it can be missing, removed, or edited. Use it only as supporting context. Do not infer AI origin solely from EXIF software fields or the absence of camera metadata.

## 3. Recommended sequence

1. Keep secure upload validation separate from detection.
2. Confirm whether an authorized provenance API is available and appropriate for this project.
3. If available, implement it as one adapter with explicit timeout, safe error handling, and minimal logging.
4. Evaluate a separate classifier only after checking licensing, privacy, infrastructure, and a representative validation dataset.
5. Keep the final report multi-signal: list checks performed, signals detected, unavailable checks, and uncertainty.
6. Never send an image to an external provider without a clear privacy disclosure and an approved data-handling decision.

## 4. Proposed provider contract

A detector adapter should accept a validated temporary image path and return a structured result with:
- `provider`: stable provider identifier.
- `status`: `completed`, `inconclusive`, `unavailable`, or `failed`.
- `signals`: zero or more evidence records with a type, outcome, and brief explanation.
- `model_score`: optional score in the provider's own scale; do not assume calibration.
- `error_code`: safe internal code only; never return raw exceptions, credentials, or provider response bodies to the browser.

The adapter must not save the image, expose secrets, or invent a result when a provider times out. Database status values and UI wording must be updated together when a real adapter is selected.

## 5. Acceptance criteria before enabling detection

- The chosen provider has documented, authorized access and a reviewed license/terms.
- External data sharing is disclosed and approved.
- API keys are provided at runtime, never committed to Git or baked into the image.
- Timeouts and provider errors return an honest unavailable/inconclusive result.
- A test suite covers known supported provenance signals, missing signals, malformed responses, timeout, and provider failure.
- Evaluation reports false positives and false negatives on a documented test set.
- User-facing wording does not describe the result as forensic proof.

## 6. Decision for this milestone

**Do not enable a detector yet.** First confirm authorized provider access and privacy constraints. Until then, the application should continue to say that image validation succeeded but AI-origin detection is not configured.

SonarCloud remains pending and is not part of this day's work.
