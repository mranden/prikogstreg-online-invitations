# WooCommerce product type — `online_invitation`

**Status:** Implemented in `src/WooCommerce/ProductType/`

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

Tab: **Online Invitation** on the WooCommerce product data panel (`ProductDataPanel`).

| Field | Meta key | Notes |
|-------|----------|-------|
| Envelope preset | `_pks_oi_envelope_preset` | Required. CSS animation shell: `classic`, `modern`, `minimal` |
| Envelope image | `_pks_oi_envelope_image_id` | Optional. WordPress attachment ID via media library picker |
| Background preset | `_pks_oi_background_preset` | Required. Public page background: `neutral`, `floral`, `geometric` |
| Default locale | `_pks_oi_default_locale` | |
| RSVP reminder offset (days) | `_pks_oi_reminder_offset_days` | |
| Guest photos default | `_pks_oi_guest_photos_default` | |
| Internal wishlist default | `_pks_oi_wishlist_default` | |
| PDF Builder optional (testing) | `_pks_oi_builder_optional` | |

Builder template remains on the same product via existing PDF Builder `_bpp_product` meta.

Deprecated: `_pks_oi_envelope_preview_ref` (legacy text field; superseded by `_pks_oi_envelope_image_id`).

### Envelope image resolution

`EnvelopeDesign::resolve_for_product()` applies this priority:

1. Explicit `_pks_oi_envelope_image_id` (valid image attachment).
2. Preset-only envelope (no card image).
3. First WooCommerce **gallery** image (`get_gallery_image_ids()[0]`) when no explicit image is set.

The **featured image is not used** for the envelope — it remains the shop/product thumbnail.

Gallery order changes affect new purchases only. Purchased projects snapshot `envelope_image_id` at creation.

### Admin preview

The Online Invitation tab shows:

- Readiness status (`BuilderValidity::integration_status`)
- Resolved envelope preview (preset + background + image source label)
- Media-library picker for envelope image

---

## Validation

`BuilderValidity` requires before purchase/publish:

1. Active PDF Builder template (`_bpp_product.active`), unless builder optional
2. Resolvable PDF Builder size/format defaults (`BppAttributeDefaults`)
3. Valid envelope preset
4. Valid background preset
5. Valid envelope image attachment when `_pks_oi_envelope_image_id` is set
6. WooCommerce price configured
7. Product type `online_invitation`

Incomplete products:

- Show admin warning notice on the product edit screen
- Revert publish attempt to `draft`
- Block add-to-cart and mark as not purchasable (`BuilderIntegration`)

Envelope validation is owned by Online Invitations — not PDF Builder.

---

## Project snapshot

At order/project creation, `ProjectFactory::build_initial_row()` copies:

- `envelope_preset`
- `background_preset`
- `envelope_image_id` (resolved at purchase time, including gallery fallback)

Stored in `pks_oi_projects` (schema v2). Later product edits do not alter existing projects.

---

## Storefront PDF Builder bridge

`StorefrontBuilderBridge` renders the PDF Builder field form on simple `online_invitation` product pages via `woocommerce_before_add_to_cart_button` → `do_action( 'woocommerce_bpp_options' )`.

Hidden `attribute_pa_bpp_size` / `attribute_pa_bpp_format` inputs are output when no variable attribute UI exists (`BppAttributeDefaults`).

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

Standard customized variable products are unchanged.

---

## Tests

```bash
composer test
```

Coverage includes: envelope design resolution, gallery fallback, attachment validation, project snapshot immutability, product readiness, storefront bridge, and existing product-type registration.
