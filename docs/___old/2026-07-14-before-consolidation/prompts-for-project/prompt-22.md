# Prompt 22 — Implement privacy tools, retention, archive/delete, and cleanup

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
Read privacy-retention plan and all implemented storage/tables/jobs.

Implement:

1. WordPress privacy policy suggested text.
2. Personal data exporter.
3. Personal data eraser.
4. Customer project archive request.
5. Customer hard-delete request flow accepted in plan.
6. Admin-controlled hard-delete.
7. Retention/cleanup scheduler.
8. Scheduled action cancellation.
9. Private file deletion.
10. Photo/derivative deletion.
11. Token invalidation.
12. Delivery/event log minimization.
13. Address-book export/erasure.
14. Guest/RSVP/wishlist/photo export/erasure handling.
15. Order/legal record preservation boundaries documented, not silently deleted.
16. Hard deletion is idempotent and reports partial failures.
17. Expiry does not equal deletion.
18. Uninstall preserves data by default.
19. Optional destructive uninstall constant/setting only if accepted and clearly documented.
20. No raw IP retention by default.
21. Rate-limit identifiers expire quickly and are minimized/hashed.

Tests:

- exporter owner data
- no other owner data
- address book
- guest data
- photo metadata/files
- eraser partial/legal-preserved data
- project hard delete
- retry after partial file failure
- scheduled action cancellation
- tokens invalid
- uninstall preservation

Update docs/privacy-retention.md with actual implementation and unresolved legal decisions.
```
