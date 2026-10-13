# Day 4 — Docker and the LAMP Environment

**Project:** AI Image Detector  
**Required stack:** Ubuntu, Apache, raw PHP, MySQL, Dockerfile only; no Docker Compose.  
**Architecture choice:** Apache/PHP and MySQL share one container for this learning assignment. This is not the preferred deployment pattern for most production systems.

## Learning goals

- Explain the role of a Dockerfile, image, container, and volume.
- Build an Ubuntu-based image with Apache, PHP, MySQL, GD, and Supervisor.
- Keep the Apache document root restricted to `public/`.
- Understand why database data needs a persistent volume and secrets must not be baked into an image.

## Files added

- `Dockerfile`: installs the system packages and copies the project and service configuration.
- `.dockerignore`: excludes Git history, local environment files, logs, uploads, and backups from the build context.
- `docker/apache-ai-image-detector.conf`: serves only `/var/www/html/public` and disables directory listings.
- `docker/supervisord.conf`: runs Apache and MySQL in the same container.

## Important implementation notes

1. The current upload endpoint validates an image but does not persist it and does not perform AI-origin detection.
2. MySQL starts with the image, but the database and least-privileged application account still need to be initialized.
3. Mount a named Docker volume at `/var/lib/mysql`; otherwise replacing the container can lose database data.
4. Do not place database passwords in the Dockerfile or commit them to Git. Use runtime configuration and a least-privileged database account.
5. This first configuration is a learning scaffold and has not been validated by a Docker build in this repository environment. Build and test it before treating it as deployable.
6. The container starts both services as required by this assignment. A production deployment should generally separate the database and web service, add TLS, backups, monitoring, patching, and hardened secret management.

## Build and test on a computer with Docker installed

From the repository root:

```bash
docker build -t ai-image-detector:day4 .
docker run -d --name ai-image-detector -p 8080:80 -v ai-image-detector-db:/var/lib/mysql ai-image-detector:day4
docker ps
docker logs ai-image-detector
```

Then open `http://localhost:8080` in a browser.

If the build fails, save the complete error and investigate it before continuing. Do not assume a successful Git commit means the image builds or runs.

## Day 4 practical tasks

- [ ] Explain the difference between an image and a running container.
- [ ] Build the image and record any errors.
- [ ] Run the container with the named database volume.
- [ ] Verify the home page is served from `public/index.php`.
- [ ] Confirm a request for a project file outside `public/` is denied or not routed.
- [ ] Inspect logs and check that both Apache and MySQL remain running.
- [ ] Stop and remove the container, then recreate it with the same named volume and verify the database files persist.

## Acceptance criteria

- The image builds successfully.
- The container starts Apache and MySQL.
- The browser can load the upload form.
- The web root is `public/`, not the repository root.
- Database files are stored in the named volume.
- No credentials or uploaded private images are committed to Git.

## References

- Dockerfile reference: https://docs.docker.com/reference/dockerfile/
- Docker volumes: https://docs.docker.com/engine/storage/volumes/
- Docker build context and .dockerignore: https://docs.docker.com/build/concepts/context/
- PHP file uploads: https://www.php.net/manual/en/features.file-upload.php
