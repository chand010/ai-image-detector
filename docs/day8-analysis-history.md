# Day 8 — Display recent analysis history

## What changed

- Added `public/history.php`, which displays the 50 most recent records from the `analyses` table.
- Added a link from the upload page to the history page.

## Security decisions

- The query uses a fixed SQL statement and limits results to 50 rows.
- All database text rendered into HTML is escaped with `htmlspecialchars`.
- The page displays metadata and status only. It does not expose original image files or filesystem paths.
- If the database is unavailable, the visitor sees a generic message and the server logs only a generic event.
- The page is currently available to anyone who can reach the prototype. Before deploying publicly, add authentication and authorization so one user cannot view another user's history.

## What to test on your computer

1. Build and run the container using a strong `DB_PASSWORD` environment variable.
2. Open `http://localhost:8080`.
3. Upload a valid image.
4. Open `http://localhost:8080/history.php`.
5. Confirm that a new row appears and that its status says `not_configured`.
6. Confirm the original image was not saved.
7. Stop MySQL temporarily only in a local test environment and verify the history page gives a generic error rather than displaying credentials or a stack trace.

## Important

This feature is committed, but it has not been runtime-tested here. Also, the AI detection engine is not connected yet; the history page reports saved metadata, not whether an image is AI-generated.
