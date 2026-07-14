# Prompt 9 — Implement private project file storage, manifests, atomic saves, and streaming

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
Read the accepted storage strategy and current repositories.

Implement:

- StoragePath
- ProjectStorage
- ProjectManifest
- AtomicFileWriter
- safe file reader/stream response
- cleanup helpers
- storage health checks

Requirements:

1. Configurable PKS_OI_STORAGE_PATH.
2. Prefer outside public web root.
3. Provide documented protected fallback.
4. Resolve paths only from project storage_uuid and controlled basenames.
5. Directory traversal tests.
6. Atomic temp-write + checksum + rename.
7. File locking.
8. UTF-8 and size validation.
9. state/current.json and state/previous.json.
10. editable page files.
11. published page files.
12. separate published manifest.
13. state_version optimistic concurrency.
14. published_version separate.
15. immediate previous valid state retained.
16. no raw request path accepted.
17. no direct public URL returned for private files.
18. stream downloads through authorized controller helpers.
19. cleanup abandoned temp files.
20. verify checksums before public/preview read.
21. store only relative paths/hashes/version in project table.
22. tests for:
    - create project directories
    - atomic save success
    - simulated partial failure
    - stale version conflict
    - checksum mismatch
    - traversal
    - invalid UTF-8
    - oversized state
    - previous-state recovery
    - deletion idempotency
23. Add a storage diagnostic service for admin support.
24. Do not implement public invitation routes yet.

Update storage documentation and run tests.
```
