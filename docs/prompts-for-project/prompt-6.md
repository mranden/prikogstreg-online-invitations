# Prompt 6 — Implement PDF Builder state validation, schema migration, preview, and safe public HTML

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
Read the audit sections on canonical state, page[] HTML, public rendering, and XSS risk.

Work in pdf-plugin.

Implement complete adapter behavior for:

- create_initial_state()
- validate_state()
- get_schema_version()
- migrate_state()
- render_preview_html()
- render_public_html()
- save_state() normalization result

Requirements:

1. Define canonical state array shape with PHPDoc and JSON fixture.
2. Include:
   - schema_version
   - product/template reference
   - field values
   - page ordering
   - page HTML
   - size/format
   - thumbnails/metadata only when safe
3. Mirror required-field validation server-side for text, image, and layer fields.
4. Reject unknown field UUIDs/types that are not part of the template.
5. Validate size/format against product template.
6. Validate maximum field/page counts and byte sizes.
7. Add migration from legacy payload with no schema_version to current schema.
8. Preserve legacy order-item loading.
9. Implement a builder-specific public HTML allowlist:
   - preserve required structural elements/classes/data attributes
   - allow only reviewed style properties
   - strip script, iframe, object, embed, forms, event handlers, dangerous URLs, CSS imports/expressions
10. Do not rely on raw wp_kses_post() alone without documenting the allowlist.
11. render_preview_html may use draft state in an authenticated context.
12. render_public_html must return sanitized public HTML only.
13. Public renderer must include required font/style references safely.
14. Do not enqueue full editor assets merely to display stored HTML unless proven necessary.
15. Add malicious fixtures and tests:
    - script tag
    - onerror
    - javascript URL
    - malicious style
    - external iframe
    - malformed HTML
    - legitimate builder markup remains functional
16. Add checksums or deterministic output tests where possible.
17. Document fixed-dimension responsive scaling limitations.
18. Run PHP tests and Webpack build when assets change.

Do not expose a logged-out public route yet. This prompt only completes the builder rendering contract.
```
