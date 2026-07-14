# Prompt 3 — Harden the audited PDF Builder endpoints without breaking pre-purchase customisation

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
Read the audit sections on security, AJAX, order-item storage, and current nopriv behavior.

Work in pdf-plugin.

Harden these audited operations:

- save_cart_pdf
- bpp_get_cart_item
- create_pdf_html
- get_field_data
- bpp_get_image
- bpp_fetch_product_customizer_data
- bpp_update_product_customizer_data
- bpp_fetch_product_customizer_cropper_data

Requirements:

1. Inventory every wp_ajax_* and wp_ajax_nopriv_* registration and its real caller.
2. For admin/order operations:
   - require login
   - verify nonce
   - verify explicit WooCommerce/order capability
   - validate order and order-item relation
   - validate product relation
3. For product-page guest operations that must remain unauthenticated:
   - issue a context-specific nonce/token from the product page
   - validate product is customizable
   - validate request sizes and counts
   - validate size/format against product configuration
   - validate uploaded MIME/content
   - add bounded rate limiting
   - return structured JSON errors
4. Remove nopriv registration when no legitimate public caller exists.
5. bpp_get_image must only return safe public image attachments and must not expose private/unattached sensitive media.
6. get_field_data must be admin-only if it is only used by the admin builder.
7. Never authorize an order operation from order_item_id alone.
8. Never expose file paths or raw exception messages.
9. Keep the current product-page customization and guest checkout path functional.
10. Add tests for:
    - missing nonce
    - invalid nonce
    - wrong capability
    - wrong order/item relation
    - non-customizable product
    - oversized request
    - invalid MIME
    - valid product-page request
    - valid admin request
11. Document any intentionally retained nopriv endpoint and why.
12. Avoid broad unrelated refactoring.

Run:
- Composer checks
- PHP syntax
- PDF Builder tests
- npm build if JavaScript/localized nonce data changes

Update:
- docs/security-review.md
- docs/builder-integration.md

Do not continue until critical unauthorized-access tests pass.
```
