# Prompt 27 — One-shot fallback prompt

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

Use only for a new controlled workspace when staged execution is not practical. The staged prompts are preferred.

```text
Build the complete Prikogstreg Online Invitations system from the supplied specifications.

Read first:

- .cursor/rules.md
- .cursor/agent.md
- .cursor/prompt.md
- online-invitation-integration-audit.md
- online-invitation-integration-contract.json
- both complete plugin repositories
- relevant active theme integration files

Before coding, create the full technical, database, builder-integration, security, privacy, and test plans.

Then implement every staged requirement from Prompts 2 through 26, in the same dependency order:

1. PDF Builder regression baseline.
2. PDF Builder AJAX security hardening.
3. Formal BPP adapter.
4. Context-aware editor.
5. state validation/migration/public renderer.
6. new plugin scaffold.
7. CPT/tables/migrations/repositories.
8. private file storage.
9. online_invitation product type.
10. pre-purchase cart/checkout/account flow.
11. idempotent project creation.
12. My Account application.
13. project edit/preview/publish/demo.
14. public animated invitation.
15. guests and private address book.
16. RSVP.
17. e-mail delivery/reminders.
18. wishlist.
19. guest photo uploads.
20. admin/refunds/expiration.
21. privacy/cleanup.
22. accessibility/i18n/assets.
23. comprehensive tests.
24. hardening/performance review.
25. documentation/release review.

Preserve the current PDF Builder standard-product flow.

V1 has unlimited guests.

Do not add SMS, phone verification, paid capacity, additional-capacity purchases, full event microsite, custom domains, collaborator accounts, direct Ønskeskyen sync, or direct social publishing.

Use project-owned custom tables and private file-backed HTML/state. Use the private CPT only as an admin shell. Use Action Scheduler. Store token hashes only. Publish only sanitized snapshots. Never expose raw draft HTML.

Run every available test/build command and report actual results. Do not claim unperformed manual tests passed. Stop and report a release blocker when safe implementation is impossible from the available repository evidence.
```
