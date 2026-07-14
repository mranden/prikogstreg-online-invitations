# Prompt 5 — Make the PDF Builder editor context-aware outside single product pages

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
Read the accepted builder integration design and current product-page renderer/assets.

Work in pdf-plugin.

Goal:
The adapter can render the existing editor inside a controlled Online Invitation My Account context without invoking add-to-cart behavior.

Implement:

1. Extract reusable editor asset registration/enqueue logic.
2. Preserve the old wp_enqueue_scripts product-page path.
3. Add context-aware:
   - enqueue_editor_assets($context)
   - render_editor($state, $context)
4. Support modes:
   - product
   - project_edit
   - project_preview
5. Resolve product/template data from validated context, not global $post alone.
6. Localize only required context values.
7. Do not expose user_id, order_id, order_item_id, private paths, or raw project tokens to JavaScript.
8. Add a unique editor instance ID.
9. Avoid hardcoded single-instance globals where safely possible.
10. Preserve legacy globals only for backward compatibility.
11. Separate add-to-cart submission from project save:
    - product mode keeps existing add-to-cart behavior
    - project_edit emits bpp:save-requested with canonical state payload
    - project_edit must not click .single_add_to_cart_button
12. Emit documented CustomEvents:
    - bpp:editor-ready
    - bpp:state-loaded
    - bpp:state-changed
    - bpp:validation-failed
    - bpp:save-requested
    - bpp:save-completed
    - bpp:preview-generated
    - bpp:generation-failed
    - bpp:image-uploaded
13. Preserve existing jQuery events for current storefront behavior.
14. Ensure product-page-only DOM selectors are either scoped or provided by the adapter template.
15. Guard external theme helper dependencies.
16. Add tests/static checks for enqueue outside is_product().
17. Build Webpack assets.
18. Manually document browser checks still required.

Do not build the My Account controller here. Deliver a clean adapter surface for the next plugin.
```
