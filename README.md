# AI Image Detector

A security-focused, raw-PHP web application for inspecting uploaded images and preparing cautious provenance checks. It is built for learning and is **not a universal AI-image detector or forensic proof tool**.

## Current status

- Upload form and server-side checks for JPEG, PNG, and WebP.
- 5 MiB application upload limit, MIME inspection, image-dimension/pixel limits, and image decode validation.
- MySQL metadata history using PDO prepared statements.
- Result page separates file validation from AI-origin assessment.
- Optional provenance provider adapter and response mapper exist, but **external image processing is disabled and not connected to uploads**.
- Original uploaded image bytes are not saved by the current upload flow.
- CI/CD debugging is deliberately pending while the core app is completed. SonarCloud and external hosting are also pending.

Passing validation means the file was readable and supported; it does not mean it is AI-generated or human-made.

## Architecture

- Ubuntu 24.04 base image
- Apache + PHP + MySQL in one container, as required for this learning project
- Raw PHP only; no framework and no Docker Compose
- MySQL data stored in a named Docker volume
- Apache document root is `public/`; project configuration, database scripts, and source files are outside the web root

> **Learning-project trade-off:** putting the web server and database in one container is not the recommended production architecture. For production, use a separately managed database, HTTPS, secret management, monitoring, backups, and an explicit privacy review.

## Run locally with Docker

Requirements: Docker Engine or Docker Desktop.

1. Clone the repository and enter its directory:

   ```bash
   git clone https://github.com/chand010/ai-image-detector.git
   cd ai-image-detector
   ```

2. Build the image:

   ```bash
   docker build -t ai-image-detector:local .
   ```

3. Start the container with a strong database password and a persistent database volume. Replace the example password with a unique random value; do not commit it to Git.

   ```bash
   docker run -d \
     --name ai-image-detector \
     -p 8080:80 \
     -e DB_PASSWORD='Replace-With-A-Unique-Strong-Password-123' \
     -v ai-image-detector-db:/var/lib/mysql \
     ai-image-detector:local
   ```

   The container creates the schema and a restricted application database account during startup. Defaults are `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME=ai_image_detector`, and `DB_USER=ai_image_app`.

4. Open `http://localhost:8080` in your browser.

5. Check startup logs if the page is temporarily unavailable:

   ```bash
   docker logs -f ai-image-detector
   ```

To stop and restart:

```bash
docker stop ai-image-detector
docker start ai-image-detector
```

To remove the container while keeping the database volume:

```bash
docker rm -f ai-image-detector
```

The named volume keeps database files when the container is replaced. To permanently delete the local database data, deliberately run `docker volume rm ai-image-detector-db`.

## Configuration

| Variable | Purpose | Default / status |
|---|---|---|
| `DB_HOST` | MySQL host | `127.0.0.1` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_NAME` | Database name | `ai_image_detector` |
| `DB_USER` | Restricted app account | `ai_image_app` in startup script |
| `DB_PASSWORD` | Database password | Required at container startup |
| `ENABLE_EXTERNAL_PROVENANCE` | Explicit provider enable flag | Must remain `false` / unset until approval |
| `OPENAI_API_KEY` | Optional provider credential | Not needed while provider is disabled |

Never put real secrets in source control, in the Docker image, or in browser-side code. The example environment file is documentation, not a production secret store.

## Privacy and detection limitations

- The app currently stores image metadata, not original image bytes.
- Metadata history is not an account-based private workspace; do not deploy this prototype publicly until access control and data visibility are reviewed.
- External provenance processing is a **NO-GO by default**. Before enabling it, review provider access, current limits/costs, terms, and data handling; then add a clear notice and obtain the required user consent.
- A provenance API checks only supported provenance signals. No detected signal does not prove human authorship and does not rule out AI generation.
- The current upload flow validates images but does not yet run an AI-origin detector.
- Opt-in image retention (private storage and automatic deletion within seven days) is specified as a future requirement and is not implemented in the current upload flow.

## Tests and current CI status

Local PHP/source checks and HTTP/Docker smoke tests are available under `tests/`. CI/CD troubleshooting is intentionally deferred until the core application is ready, so do not interpret an old or unverified workflow result as a passing build. Run tests locally and record the actual result when you do.

## Project documentation

- `docs/requirements.md` — functional requirements and definition of done
- `docs/threat-model.md` — security boundaries and risks
- `docs/day14-api-vs-local-model.md` — provider vs local classifier comparison
- `docs/day15-privacy-cost-review.md` — privacy/cost approval checklist
