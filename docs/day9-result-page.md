# Day 9 — Dedicated result page

## What changed

- Added `public/result.php` to show saved metadata for one analysis record.
- Updated `public/upload.php` to redirect successful uploads to the result page using HTTP 303 after the metadata row is saved.

## Result page behavior

The page shows the record ID, format, dimensions, size, and saved timestamp. It clearly states that AI-origin detection is not configured. It does not display an AI probability or claim that an image is human-made.

## Security notes

- The result lookup uses a prepared statement.
- The record ID is validated as a positive integer.
- Database text is HTML-escaped before display.
- Database errors are logged as generic messages and not shown to visitors.
- Original image files are not saved.
- This prototype's history and result pages are not authenticated. Do not deploy them to the public internet with private user data until authentication and per-user authorization are implemented.

## Test checklist

1. Upload a valid image and confirm the browser redirects to `/result.php?id=...`.
2. Confirm the result page shows metadata and clearly says AI detection is not configured.
3. Open `/result.php?id=abc` and confirm it returns a 400 response with a generic message.
4. Open a positive integer ID that does not exist and confirm it returns 404.
5. Confirm no database exception, password, or stack trace is shown in the browser.
6. Confirm the record appears in `/history.php`.

These changes are committed but have not been runtime-tested in Docker here.
