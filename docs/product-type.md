# WooCommerce product type — `online_invitation`

**Status:** Implemented in `src/WooCommerce/ProductType/` (Prompt 10)

---

## Registration

| Item | Value |
|------|-------|
| Slug | `online_invitation` |
| Class | `WC_Product_Online_Invitation` extends `WC_Product_Simple` |
| Virtual | Yes |
| Sold individually | Yes |
| Quantity | Always 1 |

Registrar: `ProductTypeRegistrar` (wired from `Plugin::boot()`).

---

## Admin product settings

Tab: **Online Invitation** on the WooCommerce product data panel.

| Field | Meta key |
|-------|----------|
| Envelope preset | `_pks_oi_envelope_preset` |
| Envelope preview reference | `_pks_oi_envelope_preview_ref` |
| Background preset | `_pks_oi_background_preset` |
| Default locale | `_pks_oi_default_locale` |
| RSVP reminder offset (days) | `_pks_oi_reminder_offset_days` |
| Guest photos default | `_pks_oi_guest_photos_default` |
| Internal wishlist default | `_pks_oi_wishlist_default` |

Builder template remains on the same product via existing PDF Builder `_bpp_product` meta.

---

## Validation

`BuilderValidity` requires before purchase/publish:

1. Active PDF Builder template (`_bpp_product.active`)
2. Valid envelope preset (`classic`, `modern`, `minimal`)
3. Valid background preset (`neutral`, `floral`, `geometric`)

Incomplete products:

- Show admin warning notice on the product edit screen
- Revert publish attempt to `draft`
- Block add-to-cart and mark as not purchasable

---

## Quantity enforcement

`QuantityGuard` hooks:

- Product page quantity input (min/max = 1)
- `woocommerce_add_to_cart_validation`
- `woocommerce_update_cart_validation`
- `woocommerce_add_to_cart` normalization
- `woocommerce_store_api_product_quantity_limits`
- Cart quantity HTML (hidden input, display `1`)

Mixed carts are supported — only `online_invitation` lines are forced to quantity 1.

---

## PDF Builder integration

Filter: `bpp/is_product_customizable` — returns true for `online_invitation` products with an active builder template.

Standard customized products are unchanged.

---

## Tests

```bash
composer test
```

Coverage: type registration, product class mapping, virtual/sold-individually, quantity guards, mixed cart passthrough, builder validity, Store API limits, `bpp/is_product_customizable` filter.
