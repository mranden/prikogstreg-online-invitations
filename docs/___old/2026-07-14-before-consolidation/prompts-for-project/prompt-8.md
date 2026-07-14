# Prompt 8 — Implement the private project CPT, capabilities, custom tables, migrations, and repositories

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
Read:
- rules
- agent database section
- docs/database-schema.md
- current plugin scaffold

Implement:

1. Private CPT pks_oi_project.
2. Custom capabilities and role mapping for administrators/shop managers.
3. Versioned installer/migrator.
4. All accepted custom tables:
   - pks_oi_projects
   - pks_oi_guests
   - pks_oi_address_book
   - pks_oi_wishlist_items
   - pks_oi_wishlist_reservations
   - pks_oi_photos
   - pks_oi_deliveries
   - pks_oi_events
5. Repository classes for each table.
6. Domain status constants/enums.
7. Database schema version option pks_oi_db_version.
8. Migration lock with expiry.
9. Activation install and normal-bootstrap pending migration runner.
10. Admin notice on migration failure.
11. UTC timestamp helpers.
12. Transaction helper only where database support/operation requires it.
13. Repository methods must use prepared SQL and explicit column maps.
14. Unique/index constraints from the accepted schema.
15. No raw HTML or large JSON in tables.
16. CPT deletion must not bypass domain cleanup.
17. Add tests for:
    - clean install
    - second install idempotency
    - migration retry
    - unique order_item_id
    - token hash uniqueness
    - owner-scoped address-book queries
    - repository CRUD
    - prepared-query behavior
    - no public CPT query
    - capabilities
18. Generate docs/database-schema.md from actual schema if it differs.
19. Run database integration tests.

Do not implement user UI yet.
```
