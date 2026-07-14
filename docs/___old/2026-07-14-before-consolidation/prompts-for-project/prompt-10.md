# Prompt 10 — Register the WooCommerce online_invitation product type and admin configuration

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
Read WooCommerce product architecture and accepted product settings.

Implement:

- WC_Product_Online_Invitation
- product type selector registration
- product class mapping
- product-data panel
- product CRUD/meta helpers
- quantity enforcement
- builder validity integration

Requirements:

1. Product type slug exactly online_invitation.
2. Extend the simplest appropriate WooCommerce product class.
3. Virtual and sold individually.
4. Quantity one enforced in:
   - product object
   - product page
   - add-to-cart validation
   - cart update
   - Store API/cart route when present
   - server sanity checks
5. Mixed carts remain supported.
6. Fixed price uses WooCommerce price fields.
7. Do not use stock as guest capacity.
8. Admin fields:
   - envelope preset ID
   - envelope preview/reference
   - generic background preset ID
   - default locale
   - reminder default five days
   - guest photo upload default toggle
   - internal wishlist default toggle
   - builder integration status
9. The builder template remains attached to the product through existing _bpp_product data.
10. Use bpp/is_product_customizable so the builder recognizes online_invitation.
11. Product cannot be published/purchased as a valid invitation when required builder/envelope configuration is missing.
12. Show actionable admin validation.
13. Keep existing standard customized products working.
14. Add product-type and quantity tests.
15. Test mixed cart with simple physical product.
16. Test product CRUD and HPOS-independent behavior.
17. Run PHP tests and any admin asset build.

Do not create projects yet.
```
