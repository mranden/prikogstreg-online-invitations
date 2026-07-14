# Manual test — product editor to checkout

**Prompt:** 11  
**Checkout path:** Classic (`page-checkout.php` / Kasse v2) — **not** Checkout Block

---

## Prerequisites

1. WooCommerce active with a **classic** checkout page (`page-checkout.php` template).
2. PDF Builder active with a published `online_invitation` product.
3. Product has:
   - Active `_bpp_product` template (via PDF Builder customizer)
   - Envelope + background presets selected (Online Invitation tab)
4. `composer test` and `npm run build` passing in `prikogstreg-online-invitations`.

---

## 1. Product page customisation

1. Open the `online_invitation` product on the storefront while logged out.
2. Confirm the PDF Builder customizer loads (same flow as standard customizable products).
3. Edit text/images and accept the design.
4. Confirm quantity is fixed at **1** (no quantity selector).
5. Add to cart — expect success notice.

**Negative:** Reload product page, click add to cart **without** customising.  
Expect error: *"Please customise the invitation in the PDF Builder before adding it to your cart."*

---

## 2. Mixed cart

1. Add a simple physical product (qty > 1 if theme allows).
2. Add the customised `online_invitation` product (qty 1).
3. Open cart — both lines present; invitation line shows qty **1** only.
4. Try changing invitation qty in cart (if UI allows) — should stay **1** or show error.

---

## 3. Guest checkout account requirement

1. Log out.
2. Proceed to checkout with invitation in cart.
3. Confirm notice: account is required; password will be set via secure link.
4. Confirm guest checkout is **not** available.
5. Complete checkout with **Create an account** checked.
6. Confirm WooCommerce account-creation e-mail (password setup link) — **no plaintext password**.

---

## 4. Existing customer

1. Log in as existing customer.
2. Add customised invitation to cart.
3. Checkout — no forced account creation; order associates to logged-in user.

---

## 5. Order item payload

1. Place test order (classic checkout).
2. In admin → order → line item meta, verify PDF Builder meta (`field`, `page`, `pa_bpp_*`) exists.
3. Verify Online Invitations reference meta:
   - `_pks_oi_product_type` = `online_invitation`
   - `_pks_oi_payload_version` = `1`
   - `_pks_oi_payload_checksum` present
4. Confirm order-item payload file under `uploads/order-customized-items-data/` (PDF Builder).
5. Confirm **no** `_pks_oi_project_id` yet (project creation is Prompt 12).

---

## 6. Checkout Block guard (if applicable)

1. Temporarily set WooCommerce checkout page to use `woocommerce/checkout` block.
2. Add invitation to cart and visit checkout.
3. Expect redirect to cart with error — block checkout not supported for invitations.

---

## 7. Standard PDF Builder product regression

1. Open a standard customizable (non-invitation) product.
2. Customise and add to cart — unchanged behavior.
3. Guest checkout still allowed when cart has **no** invitation lines.
