# Prompt 12 — Create projects idempotently from qualifying order statuses and import builder state

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
Read project lifecycle, storage, order hooks, and PDF adapter.

Implement:

- ProjectOrderListener
- ProjectCreationLock
- ProjectFactory
- ProjectService
- initial entitlement service
- order-item project link
- import/copy from builder order payload
- admin retry path
- project welcome e-mail registration may be scaffolded but only send when usable

Qualifying statuses:
- on-hold
- processing
- completed

Requirements:

1. Iterate order items with WooCommerce CRUD.
2. Ignore non-online_invitation items.
3. Exactly one project per order item.
4. Use:
   - order-item project ID meta
   - unique DB order_item_id
   - creation lock
5. Resolve/associate customer account.
6. Validate product and adapter availability.
7. Load source order-item builder payload through adapter.
8. Migrate/validate state.
9. Create private CPT shell.
10. Insert project row.
11. Create private storage.
12. Split/write canonical state and page HTML atomically.
13. Set schema/state version and checksum.
14. Link project ID to order item.
15. Create generic token hash but do not expose public route yet.
16. Calculate initial expiry from event date when available; otherwise leave safely pending.
17. Queue project welcome exactly once only after usable state exists.
18. Welcome has direct My Account project link.
19. Roll back safely on failure or leave a retryable explicit failure state; never a hidden orphan.
20. Admin can retry failed import.
21. Repeated status transitions and concurrent hooks do not duplicate project/e-mail.
22. Add events for creation/import/failure.
23. Tests:
    - each qualifying status
    - repeated transitions
    - concurrent/idempotent call
    - mixed order
    - missing adapter
    - malformed payload
    - file failure
    - existing project relink
    - welcome once
24. Do not expose project publicly yet.

Run all relevant tests.
```
