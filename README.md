# AI Image Detector

A security-focused raw-PHP web application that validates uploaded images, runs a local AI-origin classifier, and can optionally query a supported provenance service. It is a learning/research prototype, **not forensic proof or a universal AI-image detector**.

## Current implementation

- Raw PHP web application; no PHP framework.
- Ubuntu 24.04 container with Apache, PHP, and MySQL in one container.
- Dockerfile only; no Docker Compose.
- JPEG, PNG, and WebP validation, 5 MiB upload limit, MIME inspection, image decode validation, and a 20-megapixel limit.
- Local Smaug-AI-Image-Detector inference through a small Python helper; no hosted-classifier API token is required.
- CPU-only PyTorch by default. The helper runs inside the same container and is invoked by PHP without a shell.
- MySQL stores normalized labels/scores, basic image metadata, provider signals, and a random browser-session ownership hash. It never stores the uploaded image bytes or original temporary path.
- Original upload is explicitly deleted after analysis, before database persistence. PHP's normal request cleanup remains a fallback.
- Optional OpenAI Content Provenance check is separate from classification. It only receives the image when the user opts in and the server has explicitly enabled the feature and configured OPENAI_API_KEY.
- Database startup performs migrations for existing named volumes, including detection-status enum values and missing result columns.
- GitHub Actions checks PHP syntax, shell syntax, Python score mapping, source-level security assertions, HTTP smoke behavior, and Docker/MySQL integration. Check the actual workflow result before treating tests as passed.
- SonarCloud remains pending and is not configured here.

## Detection engine

The primary classifier is [abacusai/Smaug-AI-Image-Detector](https://huggingface.co/abacusai/Smaug-AI-Image-Detector). The model card documents local CPU use and a score between 0 and 1 representing the model's estimate that an image is AI-generated. The model is not currently served by Hugging Face Inference Providers, so this project loads it locally through the documented SmaugImageDetect helper.

The first analysis downloads model artifacts from Hugging Face into /var/cache/huggingface; this can take time and requires outbound network access on first use. The cache contains model files only, not uploaded images. Mount ai-image-detector-model-cache to preserve the cache when replacing the container. Later requests still load model weights for inference and may take time on CPU.

Result interpretation:
- **Likely AI-generated:** the model score is at least 0.5.
- **Likely real image:** the model score is below 0.5; the displayed score is 1 minus the AI score.
- **Engine unavailable** or **Inconclusive:** the model could not return a usable prediction. The app must not invent a label in these cases.

The displayed score is the model's reported score, **not a calibrated guarantee**. Model performance varies with generator, image editing, screenshots, compression, and data distribution. A score must not be the sole basis for decisions about a person, a claim, or a piece of work.

Model license shown on the model card: Apache-2.0. Review the model card and repository revision before production deployment. Because the model repository includes the Python helper that loads the weights, the container pins SMAUG_MODEL_REVISION to reviewed commit c7c95951e04a87b884a4ad479401b1128c3903bd. Keep this pin unless a newer model revision has been reviewed and tested.

## Build and run

Requirements: Docker Engine or Docker Desktop, internet access for initial package/model downloads, and sufficient disk space for Python/PyTorch dependencies plus the model cache.

### Linux / Kali

From the project root:

1. Build the container image:

   ```bash
   docker build -t ai-image-detector:local .
   ```

2. If a container named ai-image-detector is already running, stop and remove **only that container**. Do not remove the named database or model-cache volumes:

   ```bash
   docker rm -f ai-image-detector
   ```

   If the container does not exist, Docker will report that; continue.

3. Start the app with a strong, unique database password:

   ```bash
   read -rsp 'Database password (16-128 allowed characters): ' DB_PASSWORD; echo
   docker run -d \
     --name ai-image-detector \
     -p 8080:80 \
     -e DB_USER=ai_image_app \
     -e DB_PASSWORD="$DB_PASSWORD" \
     -e ENABLE_LOCAL_CLASSIFIER=true \
     -v ai-image-detector-db:/var/lib/mysql \
     -v ai-image-detector-model-cache:/var/cache/huggingface \
     ai-image-detector:local
   unset DB_PASSWORD
   ```

   Use a unique random password containing 16–128 characters from letters, numbers, and _@#%+=.-. Do not paste a real password into chat or commit it to Git.

4. Follow startup logs:

   ```bash
   docker logs -f ai-image-detector
   ```

5. Open [http://localhost:8080](http://localhost:8080). Choose a supported image and submit it. The first scan can take significantly longer while model artifacts download. The image itself is not uploaded to Hugging Face; only model files are downloaded.

### Windows PowerShell

Build the image:

```powershell
docker build -t ai-image-detector:local .
```

Run with a unique password and persistent volumes:

```powershell
docker run -d --name ai-image-detector -p 8080:80 -e DB_USER=ai_image_app -e DB_PASSWORD="Replace-With-A-Unique-Strong-Password-123" -e ENABLE_LOCAL_CLASSIFIER=true -v ai-image-detector-db:/var/lib/mysql -v ai-image-detector-model-cache:/var/cache/huggingface ai-image-detector:local
```

Replace the example password before running it. Do not commit the password to GitHub.

### Existing volumes and updates

Rebuilding the Docker image does not update a running container. To use new code, rebuild and recreate the app container while reusing both named volumes. The startup initializer migrates the existing database schema. **Do not run docker volume rm** unless you intentionally want to delete stored history or the model cache.

To inspect the container:

```bash
docker ps
docker logs --tail 150 ai-image-detector
docker exec ai-image-detector php -m
docker exec ai-image-detector /opt/ai-detector-venv/bin/python --version
```

## Configuration

| Variable | Purpose | Default |
|---|---|---|
| DB_HOST | MySQL host | 127.0.0.1 |
| DB_PORT | MySQL port | 3306 |
| DB_NAME | Database | ai_image_detector |
| DB_USER | Restricted app account | ai_image_app |
| DB_PASSWORD | Required DB password | No default |
| ENABLE_LOCAL_CLASSIFIER | Enable local inference | true in Docker image |
| SMAUG_MODEL_ID | Model repository ID | abacusai/Smaug-AI-Image-Detector |
| SMAUG_MODEL_REVISION | Immutable model commit | c7c95951e04a87b884a4ad479401b1128c3903bd |
| SMAUG_NUM_THREADS | CPU inference threads | 4 |
| SMAUG_TIMEOUT_SECONDS | Maximum helper runtime | 300 seconds |
| HF_HOME | Model artifact cache | /var/cache/huggingface |
| ENABLE_EXTERNAL_PROVENANCE | Optional external provenance feature | Disabled unless explicitly set to true |
| OPENAI_API_KEY | Credential for optional provenance check | Required only if enabled |

The primary classifier runs locally and does not need HF_TOKEN. The external provenance service is disabled by default. Never put secrets in source control, in the Docker image, or in browser-side code.

## Privacy and security notes

- Uploaded images are processed from PHP's temporary upload path, then explicitly unlinked after analysis. The original image is not retained in the database, model cache, or permanent application storage.
- Model files downloaded from Hugging Face are separate model artifacts and are retained in the model-cache volume.
- Optional external provenance processing requires both per-upload consent and explicit server-side configuration. Without both, the image is not sent to the optional provenance service.
- The result and history pages are scoped to a random browser-session token hash. This is not a user-account system and is not sufficient access control for an unauthenticated public service.
- The one-container web/database design is a learning-project trade-off. For production, use a separately managed database, HTTPS, secret management, backups, monitoring, rate limiting, and a formal privacy/security review.
- Treat model repositories and their executable code as software supply-chain dependencies. Review and pin an immutable model revision before production use.

## Tests and verification

The CI workflow runs:
- PHP syntax checks and shell syntax checks.
- Python helper compilation and score-normalization unit tests (no model download required).
- Source-level security assertions and offline provenance mapper/provider tests.
- HTTP security smoke tests.
- Docker/MySQL integration smoke tests.

These tests do not prove detection accuracy. A live inference test requires downloading the model and running it on representative images. No test should be described as passing until its actual run result is checked.

## Project documentation

See docs/ for requirements, threat model, privacy/cost review, provider research, and security notes. SonarCloud remains pending and must not be configured as part of this work.
