# Prompt 16 — Implement guest management, unlimited guests, CSV, and the reusable private address book

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
Read guest/address-book schema and authorization rules.

Implement My Account guest and address-book sections.

Guest requirements:

1. Unlimited guests; no capacity check or upsell.
2. Paginated list.
3. Add/edit/archive/restore.
4. Fields from technical plan.
5. Independent high-entropy guest token.
6. Copy personal link.
7. Status summary.
8. Bulk selection.
9. CSV export with formula-injection neutralization.
10. CSV import:
    - file/type/size limits
    - field mapping
    - row validation
    - duplicate/conflict preview
    - bounded batches
    - result report
11. Guest e-mail may be empty for copy-link-only guests.
12. Duplicate e-mail is warning, not hard database uniqueness.
13. Archive revokes token/public access.
14. Restore issues/validates token according to plan.
15. Every query scoped to project ownership.

Address-book requirements:

1. Private to user_id.
2. Paginated searchable list.
3. Create/edit/archive/delete.
4. Add selected contacts to current project.
5. Project guest is a snapshot; later address-book changes do not rewrite it.
6. Explicit “save guest to address book”.
7. Cautious normalized-e-mail duplicate handling.
8. Never merge by name alone.
9. Admin support access requires capability and audit event.
10. No marketing/global use.

Tests:

- user A cannot access user B address book/project guests
- guest token uniqueness
- duplicate e-mail allowed
- archive revokes
- CSV injection
- malformed CSV
- import limits
- address-book snapshot independence
- bulk operation nonce/authorization
- unlimited guest behavior

Update docs and run tests.
```
