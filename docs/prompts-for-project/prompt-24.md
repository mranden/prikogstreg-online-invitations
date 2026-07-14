# Prompt 24 — Add comprehensive automated tests, fixtures, negative security tests, and end-to-end coverage

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
Read docs/test-plan.md and all current tests.

Perform a full test-completion pass across both plugins.

Required Online Invitation coverage:

A. Installation
- activation
- schema
- migration idempotency/failure
- CPT/capabilities
- requirements notices

B. Product/cart/checkout
- product class/type
- quantity one
- mixed carts
- builder payload validation
- account creation/association
- actual production checkout path
- HPOS-safe CRUD

C. Project lifecycle
- qualifying statuses
- idempotent/concurrent creation
- file import
- welcome once
- refund restriction
- restore
- expiration

D. Storage
- atomic writes
- conflict
- traversal
- checksums
- previous recovery
- private streaming
- delete retry

E. Authorization
- user A vs user B
- admin capability
- project/guest/order/photo IDOR
- nonce failures
- token revocation
- generic/personal token isolation

F. Public HTML/security
- stored XSS fixtures
- invalid URLs
- no raw draft fallback
- no IDs/token logs
- cache/noindex behavior

G. Guests/address book
- unlimited
- duplicate e-mail warning
- CSV injection/import limits
- owner isolation
- snapshot independence

H. RSVP
- first/change/deadline
- generic response
- abuse limit
- notifications

I. Delivery
- idempotency
- retries
- reminder schedule/reschedule/skip
- cancellation

J. Wishlist
- atomic reservation race
- privacy
- release
- invalid project state

K. Photos
- MIME spoof
- SVG
- size/dimensions
- signed intent
- private download
- moderation
- erasure

L. Privacy
- exporter/eraser
- deletion
- uninstall preservation

Required PDF Builder coverage:

- adapter contract
- legacy payload
- product-page regression
- editor outside is_product
- schema migration
- state validation
- public sanitizer
- secured endpoints

End-to-end test:

Automate as much as the environment supports:
customize product -> cart -> mixed checkout -> project -> My Account edit/save -> event -> guest/address book -> publish -> personal link -> RSVP -> wishlist -> photo upload -> refund restriction.

Requirements:

1. Use fixtures, not production data.
2. Do not weaken production code for tests.
3. Separate unit, integration, and E2E.
4. Document environment requirements.
5. Run every available suite.
6. Fix failures caused by implementation.
7. Record external/manual blockers honestly.
8. Update docs/test-plan.md with exact commands and results.
```
