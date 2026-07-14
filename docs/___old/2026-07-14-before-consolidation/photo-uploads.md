# Guest photo uploads (V1)

Secure guest photo uploads with organiser moderation only — no public gallery.

## Flow

1. Guest opens the public invitation Photos section.
2. Client requests a short-lived signed upload intent (`POST …/photos/intent`).
3. Client uploads up to 10 files per request with the intent header (`POST …/photos/upload`, multipart).
4. Server validates MIME from bytes, dimensions, and size; re-encodes/strips EXIF when GD is available.
5. Files are stored under private `photos/pending/` with random UUID filenames.
6. Rows are inserted in `pks_oi_photos` with `moderation_status = pending`.
7. Optional organiser notification is queued (`photo_notification` delivery type).
8. Organiser reviews in My Account → Photos: approve, reject, download, delete.
9. Approved files move to `photos/approved/` with optional `photos/thumbnails/` derivative.

## Limits

| Limit | Value |
|-------|-------|
| MIME types | JPEG, PNG, WebP (content sniff) |
| SVG / scripts | Rejected |
| Max file size | 10 MB |
| Max pixels | 25 MP |
| Max files / request | 10 |
| Intent TTL | 15 minutes |
| Intent rate limit | 5 / minute per token |
| Project soft quota | 512 MB active photos |

## Security

- Upload intents are HMAC-signed (`wp_salt( 'pks_oi_photo_upload' )`) and bound to project, guest, and token hash.
- Downloads stream through authorized My Account handlers — no public URLs.
- Approved photos are **not** auto-published (ADR-014).

## Schema note

Photos use `UNIQUE KEY storage_path (storage_uuid, relative_path)` so multiple photos per project are allowed.

## Tests

`tests/Integration/Photo/PhotoTest.php` covers valid formats, MIME spoof, SVG rejection, oversized files, intent expiry, wrong token, rate limiting, filename sanitization, download authorization, orphan cleanup, and guest erasure.
