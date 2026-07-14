# Prompt 1 — Analyze both repositories and create the authoritative technical plan

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
Read completely:

- .cursor/rules.md
- .cursor/agent.md
- .cursor/prompt.md
- online-invitation-integration-audit.md
- online-invitation-integration-contract.json
- the entire pdf-plugin repository
- the entire prikogstreg-online-invitations repository, even if empty
- relevant theme files that call BPP_PDF_Plugin::content_single_product(), apply bpp_wc_attribute_html, or define missing PDF Builder helper functions

Do not implement production code yet.

Create:

- prikogstreg-online-invitations/docs/technical-plan.md
- prikogstreg-online-invitations/docs/architecture-decisions.md
- prikogstreg-online-invitations/docs/database-schema.md
- prikogstreg-online-invitations/docs/builder-integration.md
- prikogstreg-online-invitations/docs/security-review.md
- prikogstreg-online-invitations/docs/privacy-retention.md
- prikogstreg-online-invitations/docs/test-plan.md

The plan must be evidence-based and include exact current file paths and hooks.

Required analysis:

1. Locate the PDF Builder bootstrap, template storage, cart payload, order-item payload storage, product-page renderer, frontend bundles, AJAX handlers, PDF generation, fonts, and theme dependencies.
2. Locate the active theme call to BPP_PDF_Plugin::content_single_product().
3. Locate where bpp_wc_attribute_html is applied.
4. Locate ks_render_custom_field_meta() and get_product_min_order_quantity(), or prove they are absent.
5. Determine whether production uses classic checkout, Checkout Block, or both from repository/theme evidence. Mark manual verification when code cannot prove it.
6. Confirm HPOS compatibility and identify any direct order post queries.
7. Confirm Action Scheduler availability through WooCommerce.
8. Document how product-page customisation data reaches cart/order and the exact payload shape.
9. Document how project creation will import/copy that payload into project-owned storage.
10. Define the exact private storage root strategy and fallback.
11. Define the builder adapter interface and semantics for load_state()/save_state() without circular plugin dependencies.
12. Define exact product settings for envelope/background and builder validity.
13. Define project state and publication state transitions.
14. Define exact table SQL, types, indexes, unique keys, and migration order for:
    - pks_oi_projects
    - pks_oi_guests
    - pks_oi_address_book
    - pks_oi_wishlist_items
    - pks_oi_wishlist_reservations
    - pks_oi_photos
    - pks_oi_deliveries
    - pks_oi_events
15. Define the project file manifest and atomic write flow.
16. Define My Account routes/sections and controller ownership checks.
17. Define generic and personal token formats, hashing, rotation, and lookup.
18. Define generic-link RSVP behavior without overwriting named guests.
19. Define guest CSV fields and spreadsheet-injection handling.
20. Define event-detail fields.
21. Define e-mail sender policy and all WooCommerce e-mail classes.
22. Define Action Scheduler groups, hooks, arguments, and idempotency keys.
23. Define internal wishlist reservation privacy and atomicity.
24. Define photo limits, allowed MIME types, private storage, moderation, and whether an approved public gallery is included. Baseline: moderation/download only; no automatic gallery.
25. Define privacy exporter/eraser and retention categories.
26. Define refund/cancellation/restore behavior at invitation order-line level.
27. Define effective expiry and no-event-date behavior.
28. Define theme template override path.
29. Define build pipelines for both plugins without replacing the existing PDF Builder Webpack setup.
30. Define minimum PHP/WordPress/WooCommerce versions from deployment/repository evidence. Default to PHP 8.1 only when evidence does not require another supported floor.
31. Define automated and manual test matrix.
32. Create a final target file tree for both repositories.
33. Create a dependency graph and end-to-end sequence diagrams.

Architecture review:

- Verify that the CPT is only the private admin shell.
- Verify custom tables own domain records.
- Verify HTML/state/photos are file-backed.
- Verify the order item is the purchase source but project storage is the long-term source.
- Verify no raw token is stored.
- Verify raw draft HTML can never become the public fallback.
- Verify V1 remains unlimited guests.
- Verify no V2 behavior is hidden in the design.
- Verify the theme owns presentation only.
- Verify no service container is introduced without proof it is necessary.

Open decisions:

Record any unresolved production fact in a table with:
- Question
- Evidence searched
- Recommended default
- Risk if wrong
- Whether implementation may proceed safely

Do not ask a question when the safe default is already defined in .cursor/agent.md. Stop only for a decision that changes billing, irreversible storage, public privacy, or ownership.

Finish with:
- concise architecture summary
- critical prerequisites
- backward-compatibility risks
- release-risk ranking
- exact recommended prompt sequence
```
