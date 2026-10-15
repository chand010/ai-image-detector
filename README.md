# AI Image Detector

A security-focused, raw-PHP web application for inspecting uploaded images and preparing cautious provenance checks. It is built for learning and is **not a universal AI-image detector or forensic proof tool**.

## Current status

- Upload form and server-side checks for JPEG, PNG, and WebP.
- 5 MiB application upload limit, MIME inspection, image-dimension/pixel limits, and image decode validation.
- MySQL metadata history using PDO prepared statements, scoped to a random browser-session token.
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
   docker run -d --name ai-image-detector -p 8080:80 -e DB_PASSWORD="Replace-With-A-Unique-Strong-Password-123" -v ai-image-detector-db:/var/lib/mysql ai-image-detector:local
   ```

   Replace the example password with your own unique random password. Use 16–128 characters from letters, numbers, and `_@#%+=.-`. Never commit a real password to GitHub.

5. Open **http://localhost:8080** in your browser. The first startup may take a minute or two while the database initializes.
6. If the page does not load, inspect the logs:

   ```powershell
   docker logs -f ai-image-detector
   ```

### Option B — Clone with Git

   ```bash
   git clone https://github.com/chand010/ai-image-detector.git
   cd ai-image-detector
   docker build -t ai-image-detector:local .
   docker run -d --name ai-image-detector -p 8080:80 -e DB_PASSWORD='Replace-With-A-Unique-Strong-Password-123' -v ai-image-detector-db:/var/lib/mysql ai-image-detector:local
   ```

The container creates the schema and a restricted application database account during startup. Defaults are `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME=ai_image_detector`, and `DB_USER=ai_image_app`.

### Try the application

1. Open the home page and choose a JPEG, PNG, or WebP image under 5 MiB.
2. Submit it. The app validates its actual file type, dimensions, and image decoding.
3. Review the result page, which stores and displays metadata without retaining the original image.
4. Open **Analysis History** to see records from the same browser session.
5. Try a non-image file or a large image to see the validation error handling.

**Important:** this is currently a runnable upload-validation and metadata-history prototype. The AI-origin detection engine is not connected, and external image processing is disabled. The result page will say so rather than pretend the image was detected as AI-generated or human-made.

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
| `ENABLE_EXTERNAL_PROVENANCE` | Explicit provider enable flag | Must remain `false` / unset until approval |
| `OPENAI_API_KEY` | Optional provider credential | Not needed while provider is disabled |

Never put real secrets in source control, in the Docker image, or in browser-side code. The example environment file is documentation, not a production secret store.

## Privacy and detection limitations

- The app currently stores image metadata, not original image bytes.
- Result pages and history are scoped to a random browser-session token, with strict-mode sessions and HttpOnly/SameSite cookies. This is a basic privacy boundary, not user accounts or a substitute for a full public-deployment access-control review. Existing records created before the ownership migration are not visible to new sessions.
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
