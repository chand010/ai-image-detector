
# AI Image Detector

A security-focused raw-PHP web application with a responsive upload/results UI, an experimental hosted AI-vs-human image classifier, and an optional provenance-signal check. It is built for learning and is **not a universal AI-image detector or forensic proof tool**.

## Current status

- Upload form with server-side validation for JPEG, PNG, and WebP.
- 5 MiB application upload limit, MIME inspection, dimension/pixel limits, and image decode validation.
- MySQL metadata history using PDO prepared statements, scoped to a random browser-session token.
- Responsive dark UI with drag-and-drop upload, local image preview, submitting state, clear result cards, signal details, and session-scoped history.
- Experimental hosted classifier adapter using Hugging Face Inference Providers and `Ateeqq/ai-vs-human-image-detector` (Apache-2.0 model license). The model outputs `ai` or `hum`; treat the returned score as an uncalibrated model score, not proof.
- Classifier requests require **both** per-upload consent and `ENABLE_EXTERNAL_CLASSIFIER=true` with a valid `HF_TOKEN`. External classification is disabled by default.
- OpenAI Content Provenance provider is a separate optional check. It requires the same per-upload consent plus `ENABLE_EXTERNAL_PROVENANCE=true` and `OPENAI_API_KEY`. No live external API request has been verified as part of this code change.
- Uploaded image bytes are not stored in MySQL or permanent application storage. The PHP temporary upload is explicitly deleted after validation/analysis; only metadata and normalized results are saved.
- If no external provider is enabled, the app should show validation-only status instead of inventing a classification. The classifier is an experimental indicator and may be wrong.

Passing image validation means the file was readable and supported; it does not mean it is AI-generated or human-made.

## Architecture

- Ubuntu 24.04 base image
- Apache + PHP + MySQL in one container, as required for this learning project
- Raw PHP only; no framework and no Docker Compose
- MySQL data stored in a named Docker volume
- Apache document root is `public/`; project configuration, database scripts, and source files are outside the web root

> **Learning-project trade-off:** putting the web server and database in one container is not the recommended production architecture. For production, use a separately managed database, HTTPS, secret management, monitoring, backups, and an explicit privacy review.

## Run locally with Docker

Requirements: Docker Engine or Docker Desktop. On Windows, start Docker Desktop and wait until it says the engine is running.

### Option A — Download ZIP (no Git required)

1. Download the ZIP archive of this repository from GitHub and extract it.
2. Open PowerShell in the extracted project folder. Confirm that this folder contains the `Dockerfile`, `public`, `database`, and `docker` folders.
3. Build the image:

   ```powershell
   docker build -t ai-image-detector:local .
   ```

4. Start the app with a strong database password and a persistent database volume:

   ```powershell
   docker run -d --name ai-image-detector -p 8080:80 -e DB_PASSWORD="Replace-With-A-Unique-Strong-Password-123" -e ENABLE_EXTERNAL_CLASSIFIER=true -e HF_TOKEN="YOUR_HUGGING_FACE_TOKEN" -v ai-image-detector-db:/var/lib/mysql ai-image-detector:local
   ```

   Replace the example password with your own unique random password. Use 16–128 characters from letters, numbers, and `_@#%+=.-`. Never commit a real password to GitHub.

5. Open **http://localhost:8080** in your browser. The first startup may take a minute or two while MySQL initializes.
6. If the page does not load or uploads fail, inspect the logs:

   ```powershell
   docker logs -f ai-image-detector
   ```

### Option B — Clone with Git

   ```bash
   git clone https://github.com/chand010/ai-image-detector.git
   cd ai-image-detector
   docker build -t ai-image-detector:local .
   docker run -d --name ai-image-detector -p 8080:80 -e DB_PASSWORD='Replace-With-A-Unique-Strong-Password-123' -e ENABLE_EXTERNAL_CLASSIFIER=true -e HF_TOKEN="YOUR_HUGGING_FACE_TOKEN" -v ai-image-detector-db:/var/lib/mysql ai-image-detector:local
   ```

The container initializes the schema and restricted application database account during startup. Defaults are `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME=ai_image_detector`, and `DB_USER=ai_image_app`.

### Try the application

1. Open the home page and choose a JPEG, PNG, or WebP image under 5 MiB.
2. If the server is configured with `ENABLE_EXTERNAL_CLASSIFIER=true` and a valid Hugging Face token, check the external-analysis consent box for that upload to run the experimental classifier. Without consent or configuration, no classifier request is sent.
3. OpenAI provenance checks are separate and require `ENABLE_EXTERNAL_PROVENANCE=true`, `OPENAI_API_KEY`, and the same per-upload consent.
4. Review the result page. It shows the classifier label/score when available and separately lists provenance signals. Metadata and normalized results are saved without retaining the original image.
5. Open **Analysis History** to see records from the same browser session.
6. Try a non-image file or an oversized image to test validation errors.

**Important:** the API checks supported OpenAI provenance signals only. A `not_detected` outcome is not proof that an image is human-created and does not rule out images generated by other AI systems.

### Stop, restart, or remove the app

   ```powershell
   docker stop ai-image-detector
   docker start ai-image-detector
   docker logs -f ai-image-detector
   ```

To remove the container while keeping its database volume:

   ```powershell
   docker rm -f ai-image-detector
   ```

The named volume keeps database files when the container is replaced. To permanently delete the local database data, deliberately run:

   ```powershell
   docker volume rm ai-image-detector-db
   ```

## Configuration

| Variable | Purpose | Default / status |
|---|---|---|
| `DB_HOST` | MySQL host | `127.0.0.1` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_NAME` | Database name | `ai_image_detector` |
| `DB_USER` | Restricted app account | `ai_image_app` in startup script |
| `DB_PASSWORD` | Database password | Required at container startup |
| `ENABLE_EXTERNAL_CLASSIFIER` | Explicit hosted classifier enable flag | Disabled unless explicitly set to `true` |
| `HF_TOKEN` | Hugging Face Inference Providers token | Required only if classifier is enabled |
| `HF_IMAGE_CLASSIFIER_MODEL` | Hugging Face model ID | `Ateeqq/ai-vs-human-image-detector` |
| `ENABLE_EXTERNAL_PROVENANCE` | Explicit OpenAI provenance enable flag | Disabled unless explicitly set to `true` |
| `OPENAI_API_KEY` | OpenAI provenance credential | Required only if provenance checking is enabled |

Never put real secrets in source control, in the Docker image, or in browser-side code. The example environment file is documentation, not a production secret store.

## Privacy and detection limitations

- The app stores image metadata and normalized provenance results, not original image bytes.
- The upload is processed from PHP's temporary upload path and explicitly unlinked after validation/analysis. No original-image retention option is implemented or planned under the current policy.
- Result pages and history are scoped to a random browser-session token, with strict-mode sessions and HttpOnly/SameSite cookies. This is a basic privacy boundary, not user accounts or a substitute for a full public-deployment access-control review. Existing records created before the ownership migration are not visible to new sessions.
- External provenance processing is disabled by default. Enabling it requires a server-side decision plus explicit consent for each upload. Review provider access, current limits, terms, and data handling before enabling it.
- The hosted classifier model is experimental; its published training/evaluation claims have not been independently reproduced in this project. It may fail on new generators, edits, screenshots, photos, or images unlike its training data.
- The Content Provenance API checks supported signals. It is not a general-purpose classifier for every AI provider.
- Missing signals do not prove human authorship. Compression, cropping, metadata removal, legacy models, and unsupported generators may lead to no signal being detected.

## Tests and current CI status

The test suite includes PHP syntax checks, source-level security assertions, provenance response-mapper tests, disabled-provider tests, HTTP smoke tests, and Docker/MySQL integration tests. See the GitHub Actions run linked from the current pull request for the actual outcome; do not treat an unrun test as passing. SonarCloud configuration was not changed as part of this work.

## Project documentation

- `docs/requirements.md` — functional requirements and definition of done
- `docs/threat-model.md` — security boundaries and risks
- `docs/day14-api-vs-local-model.md` — provider vs local classifier comparison
- `docs/day15-privacy-cost-review.md` — privacy/cost approval checklist
- `docs/day19-security-review.md` — consent gate, temporary-file handling, and security review
- `docs/day24-openai-provenance-api.md` — API contract, cost/privacy, and CI findings
