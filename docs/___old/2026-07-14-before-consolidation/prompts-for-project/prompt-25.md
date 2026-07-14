# Prompt 25 — Final hardening, performance, data-integrity, and compatibility audit

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
Act as a senior WordPress/WooCommerce security and data-integrity reviewer.

Do not add new features.

Review both plugins against all project files.

Audit:

1. Bootstrap/dependency failure.
2. WooCommerce/HPOS CRUD.
3. checkout compatibility.
4. project idempotency.
5. database indexes/constraints/migrations.
6. repository prepared SQL.
7. CPT/table consistency.
8. file path safety/atomicity/checksums.
9. state version conflicts.
10. builder adapter ownership boundary.
11. PDF Builder backward compatibility.
12. AJAX/REST permission callbacks.
13. nonce plus authorization.
14. public token hashing/rotation.
15. stored XSS/public sanitizer.
16. upload pipeline.
17. e-mail header/token/log safety.
18. Action Scheduler duplicate/retry behavior.
19. RSVP deadline/races.
20. wishlist reservation race.
21. privacy leakage.
22. CSV injection.
23. refund/expiration restrictions across every public action.
24. pagination and N+1 queries.
25. loading full HTML/state on list screens.
26. upload/PDF memory/time limits.
27. cleanup orphan handling.
28. accessibility-critical output.
29. i18n/text domain.
30. V2 scope leakage.

Perform static searches for:

- direct SQL concatenation
- direct order post queries
- unserialize on untrusted data
- raw echo of page HTML
- raw tokens in logs
- $_REQUEST mass assignment
- unchecked file paths
- move_uploaded_file without validation
- wp_ajax_nopriv without documented protection
- missing permission_callback
- duplicate hooks
- frontend source assets enqueued
- TODO/FIXME/placeholders returning success

Create/update:

- docs/security-review.md
- docs/performance-review.md
- docs/data-integrity-review.md

Fix proven release blockers.

Run full tests/builds.

Clearly separate:
- verified
- requires manual test
- release blocker
- non-blocking recommendation
```
