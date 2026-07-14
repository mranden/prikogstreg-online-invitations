# Prompt 2 — Establish regression tests and a safe baseline for the existing PDF Builder

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
Read all project instructions and the accepted technical plan.

Work only in pdf-plugin plus test/document files required for this phase.

Before adding integration features, establish a regression baseline for the existing PDF Builder.

Tasks:

1. Inspect the current PHP, JavaScript, Composer, npm, and WordPress test setup.
2. Add a proportionate automated test harness if one does not exist.
3. Add fixtures for:
   - active customized product template
   - text field
   - image field
   - layer field
   - flat invitation
   - folded invitation
   - legacy order-item payload file
   - payload containing multiple page HTML strings
4. Add tests/static assertions for:
   - plugin bootstrap
   - BPP_Product template loading from _bpp_product
   - BPP_Hooks::is_customized_product()
   - BPP_Order_Item_Storage save/get roundtrip
   - legacy order-item meta fallback
   - fonts retrieval
   - current product-page asset gating
   - existing product customizer form rendering
   - existing cart item keys
   - existing order-item persistence
5. Add a documented manual regression checklist for:
   - customize a standard product
   - edit text/image/layer fields
   - preview
   - add to cart
   - checkout
   - order PDF
   - admin order-item re-edit
6. Do not change runtime behavior except tiny testability seams that preserve output.
7. Do not replace Webpack, jQuery, turn.js, cropper, or html2canvas in this phase.
8. Guard any missing helper function in test bootstrap without hiding a production dependency; document the real source.

Run all available existing and new checks.

Update:
- docs/builder-integration.md
- docs/test-plan.md

Report:
- baseline tests added
- commands run
- current failures that existed before integration
- untestable areas
- manual checks required
```
