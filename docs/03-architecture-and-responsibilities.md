# Architecture and responsibilities

**Last verified:** 2026-07-14 (product configurator frontend shipped)

---

## Storefront frontend ownership

| Layer | Current owner | Planned owner |
|-------|---------------|---------------|
| Product page shell (two-column layout, title, price) | Prikogstreg theme | Prikogstreg theme (unchanged) |
| Inner-invitation canvas (`#customizer-area`) | Theme calls `BPP_PDF_Plugin::content_single_product()` | Theme (unchanged) — BPP remains design engine |
| Field form (`field[]`, `page[]` inputs) | BPP via `woocommerce_bpp_options` | BPP (unchanged) — OI only composes the action |
| Size/format defaults for simple `online_invitation` | OI `BuilderFrontendBridge` in plugin template |
| Add-to-cart form + hook sequence | OI `templates/product/add-to-cart-online-invitation.php` |
| Envelope/background/readiness messaging | OI `EnvelopeFrontend` + `ProductReadiness` on product page |
| Quantity-one enforcement | `WC_Product_Online_Invitation` + `QuantityGuard` | Unchanged |
| Cart payload validation | `CartPayloadValidator` + `InvitationCart` | Unchanged |

**Architecture decision (2026-07-14):** **Option B implemented** — `OnlineInvitationProductFrontend` registers `woocommerce_online_invitation_add_to_cart` and loads `templates/product/add-to-cart-online-invitation.php`, composing BPP through existing actions/filters. No theme edits and no pdf-plugin edits.

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
| `online_invitation` WooCommerce product type + product-page add-to-cart UI | PDF Designer UI / editor JS |
| Envelope + background product meta + storefront readiness | Theme global layout |
| `ProductFrontend/*` (add-to-cart, envelope preview, readiness) | Physical print fulfillment |
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
| Calling `BPP_PDF_Plugin::content_single_product()` for canvas when BPP class exists | RSVP/guest logic |
| Variable BPP path: `bpp_wc_attribute_html` in `variable.php` | `online_invitation` add-to-cart form |
| My Account shell styling | Public token resolution |
| Optional template overrides under `prikogstreg-online-invitations/` | |

**Theme hooks consumed by plugin:** `prikogstreg_my_account_show_sidebar`, `prikogstreg_my_account_sidebar`, `prikogstreg_my_account()->is_online_invitations_context()`.

**Theme API exposed:** `pks_oi_*` functions in `src/MyAccount/theme-api.php`; filters `pks_oi_user_project_count`, `pks_oi_user_projects_nav`.

---

## Current `online_invitation` storefront request flow

Source-verified path as of 2026-07-14 (Option B shipped):

```
GET /product/{slug}/
  → WooCommerce loads product; class WC_Product_Online_Invitation (extends WC_Product_Simple)
  → Theme woocommerce/content-single-product.php
       left column: if class_exists('BPP_PDF_Plugin') → BPP_PDF_Plugin::content_single_product($postID)
       right column: woocommerce_single_product_summary
  → woocommerce_template_single_add_to_cart (priority 30)
  → do_action('woocommerce_online_invitation_add_to_cart')
  → OnlineInvitationProductFrontend::render_add_to_cart()
    → ProductReadiness (customer summary + admin diagnostics when `manage_woocommerce`)
  → templates/product/add-to-cart-online-invitation.php (`.pks-oi-product-configurator`)
       1. EnvelopeFrontend — envelope preview from `EnvelopeDesign`
       2. Canvas hint — points to theme `#customizer-area`
       3. Optional `pks_oi/product_purchase_options`
       4. BuilderFrontendBridge → `woocommerce_bpp_options`
       5. Preview hooks (`woocommerce_after_add_to_cart_quantity`)
       6. Purchase hooks (native button omitted when BPP customizable)
       hidden quantity=1
```

**Registration proof:**

| Question | Answer | Source |
|----------|--------|--------|
| Add-to-cart handler? | `OnlineInvitationProductFrontend::render_add_to_cart` | `OnlineInvitationProductFrontend.php` |
| Delegates to simple template? | **No** | `ProductTypeRegistrar.php` |
| OI plugin-owned frontend template? | **Yes** | `templates/product/add-to-cart-online-invitation.php` |
| Theme override path (optional) | `prikogstreg-online-invitations/product/add-to-cart-online-invitation.php` | `OnlineInvitationProductFrontend::locate_template()` |

**Duplicate prevention:** When `bpp/is_product_customizable` is true, the native `single_add_to_cart_button` is **not rendered** (PHP omission, not CSS). BPP `.bpp-pdf-add-to-cart` attaches via `woocommerce_after_add_to_cart_quantity`. `BuilderFrontendBridge` guards single `woocommerce_bpp_options` emission.

---

## `ProductFrontend` structure (shipped)

```
src/WooCommerce/ProductFrontend/
├── OnlineInvitationProductFrontend.php   # registers add-to-cart handler; loads template
├── BuilderFrontendBridge.php             # BPP field form + hidden size/format
├── EnvelopeFrontend.php                  # envelope/background preview on product page
├── ProductReadiness.php                  # storefront messages + admin diagnostics
├── ProductFrontendAssets.php             # product.css + product.js on OI product pages only
└── ProductBodyClass.php                  # `pks-oi-product-workspace`, gallery dedupe hook

templates/product/add-to-cart-online-invitation.php   # `.pks-oi-product-configurator` root

assets/src/scss/product.scss + assets/src/js/product.js
```

**Hooks registered:**

- `woocommerce_online_invitation_add_to_cart` → `OnlineInvitationProductFrontend::render_add_to_cart`
- `wp_enqueue_scripts` → `ProductFrontendAssets::maybe_enqueue` (`pks-oi-product` only — not `public.css`)
- `body_class` → `ProductBodyClass::filter_body_class`
- Filter `pks_oi/product_add_to_cart_template` — optional theme override path

**Removed:**

- `woocommerce_online_invitation_add_to_cart` → `woocommerce_simple_add_to_cart`
- `StorefrontBuilderBridge` (deleted)

**Classes unchanged:** `BuilderIntegration`, `BuilderValidity`, `BppAttributeDefaults`, `QuantityGuard`, `ProductMeta`, `ProductDataPanel`, cart/checkout registrars, `BuilderService` (adapter discovery — not storefront canvas).

---

## End-to-end data flow

```
[Admin] BPP template + OI envelope meta on product
    ↓
[Storefront] Theme canvas + OI ProductFrontend template → BPP field form
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
| Theme must fire `woocommerce_bpp_options` on simple products | **OI plugin template** calls `BuilderFrontendBridge` |
| Plugin-owned add-to-cart template | **Shipped** — Option B |
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
