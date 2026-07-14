# PDF Builder and envelope integration

**Last verified:** 2026-07-14 (storefront frontend analysis)

---

## PDF Builder responsibilities (external plugin)

The PDF Builder (`pdf-plugin`) provides:

- WooCommerce product meta `_bpp_product` (template definition)
- Product-page canvas (`#customizer-area`) via theme calling `BPP_PDF_Plugin::content_single_product()`
- Field form via action `woocommerce_bpp_options`
- Cart capture of `field[]`, `page[]`, `attribute_pa_bpp_size`, `attribute_pa_bpp_format`
- Order-item persistence to private files

Variable products use the existing variable template path (`bpp_wc_attribute_html` → `woocommerce_bpp_options`). **This path is unchanged and must not be modified by OI.**

---

## PDF Builder storefront contract

### Canvas (left column — theme-owned call)

| Symbol | Location | Caller | Output |
|--------|----------|--------|--------|
| `BPP_PDF_Plugin::content_single_product( $id )` | `pdf-plugin/src/class-bpp-pdf-plugin.php` | Theme `woocommerce/content-single-product.php:60-61` | `#customizer-area`, `#working_div`, `.customizer-page-content`, optional `.bpp-page-thumbnail-container` |

Called for **every** single product when `class_exists('BPP_PDF_Plugin')` — not limited to variable products. Returns early when `_bpp_product.active` is false (no canvas HTML).

Required DOM anchors for BPP `public.js`:

- `#customizer-area` — editor root
- `#working_div` — page container
- `.customizer-page-content` — per-page low-res HTML (becomes `page[]` on submit)
- `.bpp-page-thumbnail` — multi-page navigation when applicable

### Field form (right column — action-owned)

| Symbol | Location | Trigger | Output |
|--------|----------|---------|--------|
| `do_action('woocommerce_bpp_options')` | Emitted by OI bridge or BPP attribute template | `BPP_Hooks::customizer_view_right` | `BPP_Product::render_product_customizer_form()` |
| `.product-addons-for-customizer` | `class-bpp-product.php:417` | Inside field form | Text/image/layer inputs → `field[]` POST keys |

Variable path: theme `variable.php` applies filter `bpp_wc_attribute_html` → `customized-product-attribute-html.php` ends with `do_action('woocommerce_bpp_options')`.

Simple/`online_invitation` path: OI plugin template calls `BuilderFrontendBridge::render_builder_fields()` → `do_action('woocommerce_bpp_options')`.

### Add-to-cart augmentation (BPP hooks — template must preserve)

| Hook | BPP callback | Purpose |
|------|--------------|---------|
| `woocommerce_after_add_to_cart_quantity` | `BPP_Hooks::add_custom_add_to_cart_button` | Renders `.bpp-pdf-add-to-cart` anchor (`acceptCustomOrder`) |
| `woocommerce_after_add_to_cart_button` | `BPP_Hooks::add_preview_popup` | Preview modal markup |
| `wc_bpp_cart_style` | `BPP_Hooks::add_style_to_cart_button` | Inline `display:none` on native submit button |
| `body_class` | `BPP_Hooks::add_customizer_class_to_body` | Adds `customize-product` when `bpp/is_product_customizable` |

BPP also enqueues editor assets via `BPP_PDF_Plugin::wp_enqueue_scripts()` and localizes AJAX config via `BPP_Hooks::localize_script`.

### Cart payload (submitted with form POST)

| Input | Source |
|-------|--------|
| `field[]` | BPP field form (`render_product_customizer_form`) |
| `page[]` | BPP JS updates hidden inputs from canvas DOM |
| `attribute_pa_bpp_size` | Variable UI, or OI hidden defaults (`BppAttributeDefaults`) |
| `attribute_pa_bpp_format` | Same |
| `quantity` | WC quantity input (OI forces `1`) |
| `add-to-cart` | Product ID |

OI `CartPayloadValidator` validates structure; BPP `save_order_meta` / `persist_order_item_payload_to_file` persist to order-item files.

### OI integration filters (no pdf-plugin edit required)

| Filter | OI class | Effect |
|--------|----------|--------|
| `bpp/is_product_customizable` | `BuilderIntegration` | Enables BPP customize path for `online_invitation` when template active |
| `bpp/integration/service` | pdf-plugin → `Online_Invitation_Builder_Adapter` | Post-purchase import/edit (via OI `BuilderService`) |
| `pks_oi/bpp_attribute_defaults` | `BppAttributeDefaults` | Permitted size/format for simple products |

---

## Required WooCommerce hook sequence (`online_invitation` template)

The plugin-owned add-to-cart template **must** fire this order so BPP hooks and WC core behave identically to the variable path:

```
woocommerce_before_add_to_cart_form
  <form class="cart" method="post" enctype="multipart/form-data">
    [OI: ProductReadiness + EnvelopeFrontend markup]
    woocommerce_before_add_to_cart_button
      [OI: BuilderFrontendBridge — hidden size/format + woocommerce_bpp_options]
    woocommerce_before_add_to_cart_quantity
    woocommerce_quantity_input (min=max=1)
    woocommerce_after_add_to_cart_quantity   ← BPP custom add-to-cart button
    <button class="single_add_to_cart_button" …>   ← native submit; hide via wc_bpp_cart_style
    woocommerce_after_add_to_cart_button   ← BPP preview popup
  </form>
woocommerce_after_add_to_cart_form
```

**BPP depends on:** `woocommerce_bpp_options`, `woocommerce_after_add_to_cart_quantity`, `woocommerce_after_add_to_cart_button`, and (recommended) `wc_bpp_cart_style` on the native button. It does **not** hook `woocommerce_before_add_to_cart_form` or `woocommerce_before_add_to_cart_button` for field rendering.

**Not required to reproduce:** theme `includes/product-addons.php` (ACF upsells), theme minimum-order meta UI.

---

## Theme single-product trace (active Prikogstreg)

| Step | File / hook | Behaviour |
|------|-------------|-----------|
| Layout shell | `woocommerce/content-single-product.php` | Two-column flex; title in summary |
| Left media | Lines 60-64 | `BPP_PDF_Plugin::content_single_product($postID)` when BPP class exists; else standard `woocommerce_before_single_product_summary` |
| Summary | `woocommerce_single_product_summary` | Price, excerpt, add-to-cart at priority 30 |
| Variable add-to-cart | `woocommerce/single-product/add-to-cart/variable.php` | `bpp_wc_attribute_html` replaces attribute table; includes theme `product-addons.php` |
| Simple add-to-cart | `woocommerce/single-product/add-to-cart/simple.php` | Used today for `online_invitation`; no `bpp_wc_attribute_html`; includes `product-addons.php` |
| Variation button | `variation-add-to-cart-button.php` | Uses `do_action('wc_bpp_cart_style')` on submit button |
| Body class | BPP `body_class` filter | `customize-product` when customizable |
| Product JS | `assets/js/woocommerce/product.js` | Skips addon pricing when `.product-addons-for-customizer` present |

**Theme parts that are generic presentation:** column layout, mobile back arrow, video block, Trustpilot row, ACF content rows, `wc_product_class()` wrapper.

**Theme parts that are PDF-specific coupling:** unconditional `content_single_product()` call; `bpp_wc_attribute_html` in `variable.php` only; `wc_bpp_cart_style` in variation button template only.

**`online_invitation` theme branches:** none on the product page (My Account OI context is separate).

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

## Online invitation add-to-cart template (shipped)

**Template:** `templates/product/add-to-cart-online-invitation.php`  
**Renderer:** `OnlineInvitationProductFrontend` (`src/WooCommerce/ProductFrontend/`)

**Builder gateway:** `BuilderFrontendBridge` — the only OI class that may trigger `woocommerce_bpp_options` or resolve BPP size/format defaults. It receives `BuilderService` for adapter availability checks and delegates defaults to `BppAttributeDefaults`.

| Bridge responsibility | Implementation |
|-----------------------|----------------|
| Builder plugin available | `class_exists( 'BPP_Product' )` |
| Product customizable | `bpp/is_product_customizable` filter (OI `BuilderIntegration`) |
| Template active + pages | `BuilderValidity::has_active_builder_template()` + `has_template_pages()` |
| Field form (once per product) | `do_action( 'woocommerce_bpp_options' )` when hook registered |
| Hidden size/format | `BppAttributeDefaults::resolve()` → filter `pks_oi/bpp_attribute_defaults` |
| Posted attribute validation | `BppAttributeDefaults::normalize_posted_attributes()` via `CartPayloadValidator` |
| Canvas | **Theme only** — `BPP_PDF_Plugin::content_single_product()`; add-to-cart template must not output `#customizer-area` |
| Product-page assets | **BPP native** for editor JS/CSS; **OI** enqueues `product.css` / `product.js` only (no BPP duplication, no global invitation app) |

Duplicate protection: per-product render guard + `did_action( 'woocommerce_bpp_options' )` check. Native WooCommerce submit button is omitted in PHP when BPP purchase button is used (not CSS-hidden).

**Optional theme override paths:**

1. `{child-theme}/prikogstreg-online-invitations/product/add-to-cart-online-invitation.php`
2. `{child-theme}/woocommerce/single-product/add-to-cart/online-invitation.php`

Filter: `pks_oi/product_add_to_cart_template`

---

## Product type integration

| Class | Role |
|-------|------|
| `WC_Product_Online_Invitation` | Virtual, sold individually, min/max qty 1 |
| `BuilderIntegration` | `bpp/is_product_customizable` for `online_invitation` |
| `BuilderValidity` | Admin + runtime BPP/envelope/price checks |
| `BuilderService` | Adapter discovery (`bpp/integration/service`) — import/edit/publish, **not** storefront canvas |
| `BppAttributeDefaults` | Permitted size/format + filter `pks_oi/bpp_attribute_defaults` |
| `QuantityGuard` | Rejects cart qty ≠ 1 |
| `ProductPagePlaceholder` | Placeholder when builder optional (left-column gap — see known gaps) |
| `BuilderFrontendBridge` | BPP field form + hidden size/format in plugin template |
| `OnlineInvitationProductFrontend` | Add-to-cart handler + template loader |
| `EnvelopeFrontend` / `ProductReadiness` | Storefront envelope preview + readiness (`aria-live` customer summary; `manage_woocommerce` admin diagnostics) |
| `ProductFrontendAssets` | `product.css` + `product.js` on `online_invitation` product pages only |
| `ProductBodyClass` | `pks-oi-product-workspace`, `pks-oi-has-builder-canvas` (hides duplicate WC gallery when canvas active) |

---

## Product-page configurator UX (shipped)

**Root class:** `.pks-oi-product-configurator` (template `add-to-cart-online-invitation.php`)

| Step | Section | Owner |
|------|---------|-------|
| 1 | Readiness / unavailable | `ProductReadiness` (before form) |
| 2 | Envelope preview | `EnvelopeFrontend` — resolves `EnvelopeDesign`, reuses preset/background classes |
| 3 | Inner canvas | Theme `BPP_PDF_Plugin::content_single_product()` (left column); OI canvas hint in form |
| 4 | Builder controls | `BuilderFrontendBridge` → `woocommerce_bpp_options` |
| 5 | Optional product settings | `pks_oi/product_purchase_options` (hidden when no hooks registered) |
| 6 | Preview | BPP hooks on `woocommerce_after_add_to_cart_quantity` |
| 7 | Purchase | Native submit omitted when customizable; BPP `.bpp-pdf-add-to-cart` via WC hooks |

Envelope preview is **not** the full public invitation app — it demonstrates envelope colour/artwork/background and the envelope↔inner relationship only. Envelope logic stays in OI; no envelope code in pdf-plugin.

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

Prefer OI-side integration. Minimal pdf-plugin changes are acceptable only when proven unavoidable.

**Storefront frontend plan — pdf-plugin changes: none required.**

Proof:

1. Canvas render is already invoked by the theme — no new BPP entry point needed.
2. Field form is already exposed via action `woocommerce_bpp_options` — OI can fire it from a plugin template.
3. Customize gating uses existing filter `bpp/is_product_customizable` — OI already registers it.
4. Cart hooks (`woocommerce_after_add_to_cart_quantity`, `woocommerce_after_add_to_cart_button`, `wc_bpp_cart_style`) are global — they attach to any template that preserves hook order.
5. Post-purchase adapter already ships in pdf-plugin (`bpp/integration/service`).

Do **not** copy BPP JS/PHP templates into OI. Do **not** fork `render_product_customizer_form()` or `content_single_product()`.

Previously acceptable minimal pdf-plugin changes (unchanged):

- `bpp/is_product_customizable` hook point (already exists)
- Inactive template messaging in `Editor_Renderer` (My Account only)

**Not required** for public poster CSS — OI snapshots at publish.

---

## Possible pdf-plugin blockers (none identified)

| Potential blocker | Assessment |
|-------------------|------------|
| BPP requires variable product type | **False** — `is_customized_product()` uses `bpp/is_product_customizable` filter only |
| BPP must own add-to-cart template | **False** — hooks are action-based |
| Plugin template cannot load via WC | **Unverified runtime** — standard `wc_get_template( $name, $args, $plugin_path )` pattern; no source blocker |
| Hidden size/format insufficient for cart | **False** — `CartPayloadValidator` + BPP order meta already accept this shape on simple products |
| `content_single_product()` must be hooked inside BPP | **False** — theme call is the established contract |

If runtime QA shows BPP JS fails when the product type slug is `online_invitation` (e.g. hard-coded `simple` checks), that would be the first candidate for a minimal pdf-plugin patch — **no such check found in source review**.

---

## Known storefront gaps (ops)

A configured `online_invitation` product must have:

- `_bpp_product.active = true`
- At least one permitted BPP size matching `BppAttributeDefaults`
- Theme rendering BPP canvas in product left column

Staging product 284185 (local) had inactive BPP at last audit — browser E2E blocked until fixed.
