# PDF Builder and envelope integration

**Last verified:** 2026-07-14

---

## PDF Builder responsibilities (external plugin)

The PDF Builder (`pdf-plugin`) provides:

- WooCommerce product meta `_bpp_product` (template definition)
- Product-page canvas (`#customizer-area`) via theme calling `BPP_PDF_Plugin::content_single_product()`
- Field form via action `woocommerce_bpp_options`
- Cart capture of `field[]`, `page[]`, `attribute_pa_bpp_size`, `attribute_pa_bpp_format`
- Order-item persistence to private files

Variable products use the existing variable template path (`bpp_wc_attribute_html` → `woocommerce_bpp_options`). **This path is unchanged.**

---

## OI adapter

Filter: `bpp/integration/service`  
Class: `BPP\Integration\Online_Invitation_Builder_Adapter`

| Method | Used for |
|--------|----------|
| `load_state` | Import from order (`mode=import`), edit load |
| `validate_state` / `save_state` | My Account design saves |
| `render_public_html` | Publish (single combined HTML when adapter used) |
| `render_preview_html` | My Account preview |
| `render_editor` | My Account design section |

If adapter unavailable: import falls back to direct file reads; publish uses per-page `state['page']` HTML.

---

## Simple product storefront bridge

**Problem:** Theme `simple.php` does not fire `woocommerce_bpp_options` (variable template does).

**Solution:** `StorefrontBuilderBridge` (`src/WooCommerce/ProductType/StorefrontBuilderBridge.php`)

Hooks `woocommerce_before_add_to_cart_button` (priority 10) when:

- `is_product()` and product type `online_invitation`
- `bpp/is_product_customizable` is true (OI sets via `BuilderIntegration` when `_bpp_product.active`)
- Builder not marked optional (`_pks_oi_builder_optional`)
- Product is not `variable`

Renders:

1. Hidden `attribute_pa_bpp_size` and `attribute_pa_bpp_format` from `BppAttributeDefaults::resolve()`
2. `do_action('woocommerce_bpp_options')` once per request

**Guards:** Skips if form already rendered or `woocommerce_bpp_options` already fired.

---

## Product type integration

| Class | Role |
|-------|------|
| `WC_Product_Online_Invitation` | Virtual, sold individually, min/max qty 1 |
| `BuilderIntegration` | `bpp/is_product_customizable` for `online_invitation` |
| `BuilderValidity` | Admin + runtime BPP template checks |
| `BppAttributeDefaults` | Permitted size/format + filter `pks_oi/bpp_attribute_defaults` |
| `QuantityGuard` | Rejects cart qty ≠ 1 |
| `ProductPagePlaceholder` | Placeholder when builder unavailable |

---

## Envelope configuration (product admin)

Meta keys (`ProductMeta`):

| Key | Purpose |
|-----|---------|
| `_pks_oi_envelope_preset` | `classic`, `modern`, `minimal` |
| `_pks_oi_background_preset` | `neutral`, `floral`, `geometric` |
| `_pks_oi_envelope_image_id` | Optional envelope artwork attachment |
| `_pks_oi_default_locale` | Default project locale |
| `_pks_oi_reminder_offset_days` | Reminder scheduling default |
| `_pks_oi_guest_photos_default` | Feature default |
| `_pks_oi_wishlist_default` | Feature default |
| `_pks_oi_builder_optional` | Test mode — skips BPP requirement |

Envelope design resolution: `EnvelopeDesign` filter `pks_oi/envelope_design`.

---

## Envelope snapshot (project)

At import (`ProjectStorage::import_complete_snapshot`):

1. `EnvelopeSnapshot` normalizes product envelope meta
2. Optional image copied to `envelope/images/` with SHA-256
3. Written to `envelope/manifest.json` (`EnvelopeManifest`)

Public rendering reads manifest first (`EnvelopeViewModel`, `EnvelopeImageResolver`). Product meta changes after purchase do not affect published envelope.

**Public envelope image URL:** `/invitation/{token}/envelope-image/` streams project-owned copy when `media_storage=project_copy`.

---

## Inner poster vs envelope

| Layer | Source at public |
|-------|-------------------|
| Envelope shell | `envelope/manifest.json` + CSS presets |
| Addressee label | Guest name (personal) or neutral text (generic) |
| Inner poster | `pages/published/` only, via `PublicInvitationLoader` |
| RSVP / wishlist / photos | Below poster in `templates/public/envelope.php` |

---

## pdf-plugin changes policy

Prefer OI-side integration. Minimal pdf-plugin changes are acceptable when:

- Required for `bpp/is_product_customizable` filter hook point
- Inactive template messaging in `Editor_Renderer`

**Not required** for public poster CSS — OI snapshots at publish.

---

## Known storefront gaps (ops)

A configured `online_invitation` product must have:

- `_bpp_product.active = true`
- At least one permitted BPP size matching `BppAttributeDefaults`
- Theme rendering BPP canvas in product left column

Staging product 284185 (local) had inactive BPP at last audit — browser E2E blocked until fixed.
