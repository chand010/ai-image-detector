# Day 7.3 — Initialize the database automatically

## Files added or updated

- `docker/init-database.sh` waits for MySQL, applies `database/schema.sql`, and creates a dedicated database account.
- `docker/supervisord.conf` runs database initialization after MySQL starts and before Apache starts.

## Required runtime configuration

Set `DB_PASSWORD` when starting the container. Use a strong random password of 16–128 characters from this conservative allowed set: letters, digits, and `_@#%+=.-`. Do not use the example password from `.env.example`.

The current schema name is `ai_image_detector`; keep `DB_NAME=ai_image_detector` unless you also update `database/schema.sql`.

## How it works

1. MySQL starts.
2. The init script waits until MySQL accepts a local socket connection.
3. The schema file creates the database and `analyses` table.
4. A non-root `ai_image_app` user is created/updated with access only to the application's database.
5. Apache starts after the initialization process exits successfully.

The app account receives SELECT, INSERT, and UPDATE permissions only. It does not receive global privileges or schema-alteration privileges.

## Important caveats

- This setup assumes Ubuntu's MySQL root account uses local socket authentication, as expected from the packaged default. Verify this in the actual container.
- The init script is intentionally strict about password characters to avoid SQL quoting ambiguity. This is a temporary setup constraint, not a general password-handling recommendation.
- The container still needs `DB_PASSWORD` supplied at runtime. Do not commit a real password.
- If an existing named database volume already contains MySQL data, initialization will reuse it; it will not reset or erase the database.
- Rebuilding an image does not update an existing container automatically. Recreate the container carefully while retaining the named data volume.
- This code has been committed but not run in a Docker environment here. Verify startup, logs, and a real PDO connection on your computer before treating it as working.

