# Prompt 14 — Embed the builder in My Account and implement save, preview, publish, and demo-to-self

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
Read the adapter, storage, My Account shell, and published trust model.

Implement the project design lifecycle:

1. Design section loads project-owned canonical state.
2. Adapter renders editor in project_edit mode.
3. Adapter enqueues editor assets outside is_product().
4. Project save endpoint:
   - authenticated owner/admin
   - nonce
   - entitlement
   - expected state_version
   - request-size limits
   - adapter validation/normalization
   - atomic storage
   - increment version
   - conflict response for stale version
5. Save never triggers add-to-cart.
6. Event details section with exact accepted fields and timezone handling.
7. Preview:
   - authenticated
   - draft state
   - no public token
   - no open tracking
   - envelope + draft rendered HTML
8. Publish:
   - validate required builder and event data
   - generate adapter public HTML
   - sanitize
   - write published page files and manifest atomically
   - set published_version/publication status
   - keep draft independent
9. Unpublish makes public resolver unavailable later without deleting snapshot.
10. Republish updates published snapshot only after complete success.
11. Demo-to-self:
   - explicit owner action
   - send owner demo e-mail
   - use demo-only token or authenticated preview
   - cannot create real RSVP
   - does not update guest sent/opened stats
12. Add events for save/publish/unpublish/demo.
13. Add tests:
   - owner save
   - other user rejected
   - stale conflict
   - invalid state
   - publish sanitizer failure
   - partial file failure
   - preview no tracking
   - demo idempotency/rate limit
14. Run both plugin asset builds.
15. Document browser testing for editor fidelity.

Do not create public guest route until the next prompt.
```
