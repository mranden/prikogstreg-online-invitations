# Prompt 19 — Implement V1 wishlist and atomic guest reservations

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
Read wishlist schema, surprise privacy, public token model, and project settings.

Implement:

Owner side:

1. Optional external Ønskeskyen URL.
2. Validate only safe HTTP/HTTPS URL.
3. Internal wishlist item CRUD.
4. Fields:
   - title
   - description
   - optional URL
   - optional image
   - quantity requested
   - sort order
   - active/hidden
5. Reorder with nonce/authorization.
6. Reservation count.
7. Project setting controlling whether organizer sees reservation identity.
8. Default preserves surprise privacy.

Public side:

1. Render active items only.
2. Personal/generic valid token may reserve.
3. Reserve quantity.
4. Release own reservation.
5. Do not show another guest's identity.
6. Generic visitor must have or create a guest context before reservation.
7. Repeated reserve/release is idempotent.

Atomicity:

1. Use pks_oi_wishlist_reservations.
2. Use transaction or conditional locking/update accepted in plan.
3. Never exceed quantity requested.
4. Unique item/guest active reservation semantics.
5. Handle simultaneous final-item reservation.
6. Update quantity_reserved consistently or calculate from reservation rows according to plan.
7. Log events.
8. Restricted/expired/unpublished project rejects public mutations.

Security:

- URL/image validation
- no arbitrary HTML
- rate limit
- token authorization
- owner authorization
- no Ønskeskyen scraping/sync

Tests:

- external URL
- item CRUD
- two-guest race
- multi-quantity
- repeat request
- release
- hidden item
- surprise privacy
- invalid token
- restricted project
- XSS input

Update docs and run tests.
```
