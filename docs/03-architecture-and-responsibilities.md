# Architecture and responsibilities

**Last verified:** 2026-07-14

---

## Responsibility matrix

### PDF Builder (`pdf-plugin`)

| Owns | Does not own |
|------|----------------|
| Admin poster/poster template (`_bpp_product`) | Envelope configuration |
| Editable text, image, layer fields | Project lifecycle |
| Image cropper, fonts, fixed canvas dimensions | Guests, RSVP, wishlist |
| Product-page customer editor JS | Public invitation routes |
| `page[]` HTML + `field[]` state at add-to-cart | Published snapshot storage |
| Cart/order-item JSON file (`BPP_Order_Item_Storage`) | My Account project UI |
| Optional print PDF generation | Token links |

**Integration surface:** filter `bpp/integration/service` → `Online_Invitation_Builder_Adapter` implementing `Builder_Adapter_Interface` (`load_state`, `validate_state`, `save_state`, `render_public_html`, `render_preview_html`, `render_editor`).

### Online Invitations (this plugin)

| Owns | Does not own |
|------|----------------|
| `online_invitation` WooCommerce product type | PDF Designer UI / editor JS |
| Envelope + background product meta | Theme global layout |
| `StorefrontBuilderBridge` (field form on simple products) | Physical print fulfillment |
| Quantity-one enforcement | Variable-product BPP attribute UI (unchanged) |
| Cart/checkout validation, account requirement | |
| Order → project import (idempotent) | |
| Private project storage + DB rows | |
| Envelope snapshot (`envelope/manifest.json`) | |
| Publish pipeline + sanitizer | |
| My Account app (`online-invitations`) | |
| Guests, address book, RSVP, wishlist, photos | |
| Delivery queue + WC emails | |
| Public `/invitation/{token}/` + REST | |
| Envelope animation + published poster viewport | |
| Entitlement, expiration, refund restriction | |

### Prikogstreg theme

| Owns | Does not own |
|------|----------------|
| WooCommerce single-product layout | Invitation business rules |
| Header, navigation, product gallery column | Project storage |
| Calling `BPP_PDF_Plugin::content_single_product()` for canvas | RSVP/guest logic |
| My Account shell styling | Public token resolution |
| Optional template overrides under `prikogstreg-online-invitations/` | |

**Theme hooks consumed by plugin:** `prikogstreg_my_account_show_sidebar`, `prikogstreg_my_account_sidebar`, `prikogstreg_my_account()->is_online_invitations_context()`.

**Theme API exposed:** `pks_oi_*` functions in `src/MyAccount/theme-api.php`; filters `pks_oi_user_project_count`, `pks_oi_user_projects_nav`.

---

## End-to-end data flow

```
[Admin] BPP template + OI envelope meta on product
    ↓
[Storefront] Theme canvas + OI StorefrontBuilderBridge → BPP field form
    ↓
[Cart] field[], page[], size, format + OI markers/checksum
    ↓
[Checkout] BPP order-item file + OI reference meta
    ↓
[Order hook] ProjectService::import_for_project (adapter load_state mode=import)
    ↓
[Storage] state/, pages/editable/, envelope/manifest.json
    ↓
[My Account] edit event, guests, design state
    ↓
[Publish] pages/published/, poster-manifest.json, poster CSS snapshot
    ↓
[Public] token → envelope → published HTML + RSVP/wishlist/photos
```

---

## Key integration filters

| Filter | Provider | Purpose |
|--------|----------|---------|
| `bpp/integration/service` | pdf-plugin | Adapter instance |
| `bpp/is_product_customizable` | OI `BuilderIntegration` | Simple `online_invitation` customize gate |
| `pks_oi/bpp_attribute_defaults` | OI `BppAttributeDefaults` | Size/format defaults |
| `pks_oi/envelope_attachment_source_path` | OI `ProjectStorage` | Envelope image copy at import |
| `pks_oi/capture_poster_display_css` | OI publish | Optional BPP display CSS capture |
| `pks_oi/capture_poster_fonts_css` | OI publish | Optional font CSS capture |

---

## Deviations from early plans

| Old assumption | Current code |
|----------------|--------------|
| Theme must fire `woocommerce_bpp_options` on simple products | **OI owns this** via `StorefrontBuilderBridge` — theme change not required |
| Public poster needs BPP editor JS | **No** — `PublishedPosterAssetSnapshotter` + `PosterDisplayAssets` |
| Envelope read from live product meta on public | **No** — `EnvelopeViewModel` prefers `envelope/manifest.json` |
| Adapter `render_public_html` always multi-page | **Single combined page** when adapter path used; multi-page via fallback `state['page']` |
| Guest capacity packages in V1 | **Not implemented** — unlimited guests |

---

## What not to do

- Move envelope, RSVP, or project logic into pdf-plugin
- Render raw draft `page[]` on public routes
- Rebuild the PDF Designer in OI
- Put business logic in the theme
- Depend on Checkout Blocks for invitation carts
