# Prompt 11 — Preserve pre-purchase customisation through cart, checkout, and account creation

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
- current PDF Builder cart flow
- accepted checkout path
- online invitation product implementation

Implement the integration from customized product to order item without creating the project yet.

Requirements:

1. Product page uses the existing PDF Builder editor.
2. Ensure online_invitation product receives the builder customizer and correct assets.
3. Preserve field/page/size/format payload in cart.
4. Preserve existing generated PDF behavior where applicable.
5. Add a namespaced invitation cart marker and payload version without duplicating large data.
6. Validate cart payload server-side against adapter contract.
7. Reject missing/invalid required builder state before add to cart.
8. Quantity is one.
9. Mixed cart works.
10. When cart contains online_invitation:
    - require account creation/association
    - preserve logged-in customer
    - support existing billing e-mail account safely
    - use WooCommerce password-setup flow
    - never e-mail plaintext password
11. Implement classic checkout.
12. Implement/verify Checkout Block path when technical plan says production uses it; otherwise document exact limitation and provide safe prevention/fallback rather than silently losing payload/account.
13. Copy required invitation payload references to order item using WooCommerce hooks/CRUD.
14. Do not create project on thank-you page.
15. Add tests for:
    - guest checkout account creation requirement
    - existing account
    - mixed cart
    - invalid builder state
    - payload survives session/cart/order
    - quantity tampering
    - classic checkout
    - actual production checkout path
16. Add manual test instructions for product editor to checkout.
17. Preserve standard PDF Builder products.

Run tests/builds for both plugins when affected.
```
