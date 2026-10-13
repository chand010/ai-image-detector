# Day 7.2 — Docker MySQL initialization

## What changed

The Docker image now uses `docker/entrypoint.sh`. On the first container start, the script initializes the MySQL data directory if it has not already been initialized, then starts Supervisor, which manages MySQL and Apache.

Files:
- `docker/entrypoint.sh`
- `Dockerfile`

## Important limitation

This step initializes MySQL's system data directory only. It does NOT yet create the `ai_image_detector` database, the `analyses` table, or the restricted `ai_image_app` database account. Those must be created in a later setup step before database-backed uploads will work.

The PHP connection helper expects `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`. The container does not yet inject those variables automatically.

## When testing on a computer

Do not delete the named Docker volume containing MySQL data just to restart the container. A new image does not automatically update an existing container: rebuild the image and recreate the container carefully while keeping the data volume.

Because the Dockerfile and startup script have not been executed in this chat environment, treat this as code committed for later testing, not as a verified successful container build.
