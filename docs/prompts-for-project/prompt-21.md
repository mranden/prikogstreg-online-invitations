# Prompt 21 — Implement admin support, refund restrictions, entitlement restore, and expiration

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
Read CPT/admin, entitlement, refund, order status, expiration, and audit requirements.

Implement admin support UI without using the post content editor.

Admin project view must show:

- owner
- order/order item
- product
- project/publication status
- event/expiry
- builder schema/state/published versions
- storage health/checksums
- guest/RSVP/wishlist/photo/delivery counts
- last safe error
- recent events

Support actions:

- open support view
- retry failed project import
- resend welcome
- restrict/unrestrict
- publish/unpublish only through domain service
- set/clear expiry override
- rotate generic token
- inspect delivery failures
- start controlled hard-delete

Requirements:

1. Custom capabilities.
2. Nonce.
3. Validation.
4. Audit event and optional reason.
5. No raw token or state dump.

Refund/cancellation:

1. Detect full refund of invitation line item, not unrelated order refund.
2. Restrict project.
3. Public route unavailable.
4. Cancel pending invitation/reminder/upload processing where appropriate.
5. Preserve data.
6. Prevent send/publish/new public mutations.
7. Avoid repeated duplicate transition events.
8. Existing project on cancelled/failed order follows accepted policy.
9. Restore requires admin capability and explicit action.

Expiration:

1. Effective date = override or event end/start + 90 days.
2. Schedule/recalculate when event date/override changes.
3. Idempotent Action Scheduler job.
4. Mark expired; do not hard-delete.
5. Public unavailable and jobs cancelled.
6. Owner/admin access retained according to plan.
7. No-event-date fallback from technical plan.

Tests:

- full line refund
- partial unrelated refund
- repeated refund hook
- restrict behavior across features
- restore
- expiry calculation
- override
- job idempotency
- no hard delete
- admin capability/nonce
- event logs

Update support and lifecycle docs.
```
