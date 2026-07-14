# Prompt 20 — Implement secure guest photo uploads and organiser moderation

## Global execution contract

For every prompt:

1. Read all `.cursor` files.
2. Read the accepted `docs/technical-plan.md` when it exists.
3. Inspect current code before editing.
4. Preserve the existing PDF Builder product-page flow.
5. Do not implement V2 features.
6. Use actual commands and report actual results.
7. Update documentation when implementation deviates.
8. Do not state that a test passed unless it ran successfully.

Confirmed V1 includes product type, pre-purchase customisation, account/project creation, My Account editing, public animated invitations, guests, private address book, delivery, open tracking, RSVP/reminders, wishlist, guest photo uploads, admin support, refund restrictions, expiration, privacy, and cleanup.

Explicit V2: SMS, phone verification, guest pricing/capacity, additional-capacity purchases, custom domains, full event microsite, collaborator accounts, direct Ønskeskyen synchronization, and direct social publishing.

---

```text
Read the photo threat model, storage, signed-intent, public token, and moderation requirements.

Implement:

- signed expiring upload intent
- rate limiter
- public upload endpoint
- private temp/final storage
- image validation/re-encoding
- photo repository/service
- My Account moderation UI
- authorized download/stream
- cleanup jobs

Baseline limits unless technical plan accepted different values:

- JPEG, PNG, WebP
- no SVG
- max 10 MB per file
- max 25 megapixels
- max 10 files per request
- configurable project storage soft limit

Flow:

1. Validate active published project and guest/generic context.
2. Issue short-lived signed upload intent.
3. Verify intent, token context, expiry, rate limit.
4. Validate actual MIME from bytes.
5. Validate dimensions before full expensive decode where possible.
6. Reject polyglot/executable/invalid files.
7. Use random filename/storage UUID.
8. Strip EXIF and re-encode when supported.
9. Write atomically to private storage.
10. Generate safe thumbnail asynchronously when useful.
11. Insert photo row pending.
12. Queue organizer notification according to setting.
13. Do not create public media attachment pages.
14. Do not auto-publish a public gallery.
15. Organizer can:
    - view pending
    - approve
    - reject
    - download
    - delete
16. Download streams through authorization.
17. Delete removes derivatives and is idempotent.
18. Cleanup abandoned temp files.
19. Log safe events without bytes or tokens.

Tests:

- valid JPEG/PNG/WebP
- MIME spoof
- SVG reject
- oversized bytes
- oversized dimensions/decompression bomb guard
- expired intent
- wrong token/project
- rate limit
- traversal filename
- EXIF handling where environment supports
- moderation authorization
- private download authorization
- cleanup
- privacy erasure

Run tests and asset build.
```
