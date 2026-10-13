# Day 6 — Database foundation

## What we added

The file `database/schema.sql` defines the first MySQL database and an `analyses` table.

Each record can store:
- Image MIME type (for example, `image/jpeg`)
- Image width and height
- File size in bytes
- Detection status
- Optional future detection label and confidence
- Record creation time

## Privacy and safety choices

- The table does not store the original image or its filesystem path.
- Detection is marked `not_configured` by default. The current application validates image files but does not detect AI origin.
- Confidence is optional and constrained to the range 0–1.
- Use a dedicated database account with only the permissions the app needs. Do not run the web application as MySQL root.
- Use prepared statements for all SQL queries when PHP database integration is added.

## Important current limitation

This schema is not connected to the PHP upload handler yet. The application still validates uploads and does not save analysis records. We will configure the database service and application credentials before integrating database writes.

## Next steps

1. Confirm the existing Docker container still starts.
2. Initialize MySQL and create a restricted application user.
3. Connect PHP with PDO and prepared statements.
4. Save only validated image metadata and the explicit detection status.
5. Add tests for database errors and safe user-facing messages.

Never treat file validation as an AI-origin verdict. A missing SynthID watermark does not prove that an image is human-created.
