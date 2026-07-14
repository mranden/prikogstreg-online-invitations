# PDF Builder — V1 Feasibility for Online Invitations

**Audit date:** 2026-07-14  
**Question:** Can the current PDF module serve as the **complete poster designer** for `prikogstreg-online-invitations` V1?

**Short answer:** **Yes for the designer capability itself** on the established **variable-product + theme** pipeline. **Conditional** for the new **`online_invitation` simple-product** pipeline until the field-form insertion gap is closed.

---

## 1. Executive feasibility summary

| Capability area | Verdict | Confidence |
|-----------------|---------|------------|
| Admin creates complete visual poster | **Works now** | High (source) |
| Customer edits text/images/fonts/layers pre-purchase | **Works now** on variable BPP products | High (production evidence: 384 variable + active `_bpp_product`) |
| Same flow on `online_invitation` product type | **Blocked / unverified** — field form path missing | High (source), runtime required |
| Cart → order payload persistence | **Works now** | High (source + unit tests) |
| Static public poster display without editor JS | **Feasible** via saved `page[]` + scoped CSS/fonts | Medium (source); runtime required |
| Post-purchase PDF editor reopen | **Not required for stated V1** | N/A per audit scope |

---

## 2. Phase 2 — Admin poster design creation

### 2.1 Admin entry points

| Entry | Path | Evidence |
|-------|------|----------|
| Top-level menu | `?page=bpp-customize` | `BPP_Menu`, `BPP_Controller` |
| Product list action | Customize link with `prdid={id}` | `class-bpp-hooks.php:628-639` |
| Product meta storage | `_bpp_product` serialized `BPP_Product_Model` | `class-bpp-product.php:30, 113-227` |

OI admin links to PDF customizer from product data panel (`ProductDataPanel.php`) — note parameter name `product_id` vs BPP expected `prdid` (**possible admin link bug**, Medium confidence).

### 2.2 Capability matrix (admin)

| # | Capability | Classification | Evidence |
|---|------------|----------------|----------|
| 1 | Administrator creates complete visual poster | **Working now** | Admin customizer saves page HTML, thumbnails, `low_res_html` |
| 2 | Administrator defines editable text fields | **Working now** | `BPP_Text_Field`, field registry `class-bpp-pdf-plugin.php:4-8` |
| 3 | Administrator defines editable image fields | **Working now** | `BPP_Image_Field` |
| 4 | Background artwork locked | **Working now** | Non-field HTML in page template; only fields render inputs |
| 5 | Decorative layers locked | **Working with limitations** | Layer fields can be optional toggles — admin configures `layer` type |
| 6 | Optional layers show/hide | **Working now** | `BPP_Layer_Field`, checkbox insertion in `render_product_customizer_form()` |
| 7 | Multi-page invitations | **Working now** | Multiple `BPP_Page_Model` entries, page thumbnails in `content_single_product()` |
| 8 | Folded invitations | **Working now** | `foldable` flag, `pa_bpp_format` attribute terms |
| 9 | Dimensions stored reliably | **Working now** | `pa_bpp_size` taxonomy + product model `default_size` |
| 10 | Font dependencies stored/discoverable | **Working with limitations** | `bpp_font` CPT + `BPP_fonts_css()`; no formal manifest export |
| 11 | Design images referenced | **Mixed** — attachment URLs in HTML, thumbnails as URLs, customer images often base64 in orders | `class-bpp-woo-cart-functions.php:42-58` |
| 12 | Changing design affects existing orders | **Safe for purchased orders** — payload copied to order-item file at checkout | `BPP_Order_Item_Storage::save_payload()` |
| 13 | Design schema version | **Partial** — adapter `Builder_Schema::CURRENT_VERSION = '1'` for canonical state; admin template has no separate version field | `Builder_Schema.php` |
| 14 | Migration system | **Minimal** — legacy→v1 in adapter only | `Online_Invitation_Builder_Adapter::migrate_state()` |
| 15 | Admin edit breaks purchased invitations | **Unsafe if customer re-edits from product template only** — existing order payloads preserved until cron cleanup (~3 months) | `class-bpp-cron.php` |

---

## 3. Phase 3 — Customer product-page designer

### 3.1 Request flow (established variable-product path)

```text
GET /product/{slug}/
  → theme content-single-product.php
      → BPP_PDF_Plugin::content_single_product($postID)     [left column canvas]
      → woocommerce_single_product_summary
          → variable.php
              → apply_filters('bpp_wc_attribute_html', …)
                  → customized-product-attribute-html.php
                      → do_action('woocommerce_bpp_options')
                          → BPP_Product::render_product_customizer_form()  [field inputs]
  → BPP_PDF_Plugin::wp_enqueue_scripts (is_product + active)
  → BPP_Hooks::localize_script → BPP_CUSTOMISER_DATA, BPP_PRODUCT, __bpp
  → Customer edits → JS updates #working_div innerHTML → hidden page[] inputs
  → .bpp-pdf-add-to-cart (custom button) submits form
```

**Evidence:** theme `content-single-product.php:60-64`, `variable.php:35-63`, `customized-product-attribute-html.php:152`, `class-bpp-hooks.php:771-782`

### 3.2 `online_invitation` path (simple product)

```text
GET /product/{online_invitation}/
  → same left column: content_single_product() IF _bpp_product.active
  → woocommerce_online_invitation_add_to_cart → simple.php
      → quantity + standard add-to-cart button
      → NO bpp_wc_attribute_html
      → NO woocommerce_bpp_options
      → NO render_product_customizer_form()
```

**Evidence:** `ProductTypeRegistrar.php:17`, theme `simple.php` (no BPP hooks)

**Impact:** Canvas may render, but **customer field inputs, size/format UI, and layer toggles may be absent**. Add-to-cart would post empty `field[]` / `page[]` unless JS injects inputs elsewhere.

**Classification:** **Runtime test required** — treat as **V1 blocker** until verified or fixed.

**Confidence:** High (static analysis)

### 3.3 Product-page compatibility checklist

| # | Question | Variable BPP | `online_invitation` | Evidence |
|---|----------|--------------|---------------------|----------|
| 1 | Compatible product class | `WC_Product_Variable` | `WC_Product_Simple` subclass | WC core |
| 2 | Expected add-to-cart form | `variable.php` + BPP filter | `simple.php` | theme |
| 3 | Theme enters BPP branch | Yes if class exists | Same | `content-single-product.php:60` |
| 4 | `is_customized_product()` rejects custom type? | **No** — checks `_bpp_product.active` + filter | Passes if meta active + OI filter | `class-bpp-hooks.php:911-916` |
| 5 | BPP checks `simple`/`variable` explicitly? | **No** | **No** | grep |
| 6 | Only requires active `_bpp_product`? | **Yes** (+ `is_product()` for page UI) | Same | hooks |
| 7 | Size/format attributes render | Yes via attribute template | **Missing** on simple path | variable.php vs simple.php |
| 8 | Price/purchasability | WC native | OI adds `BuilderValidity` gate | `BuilderIntegration.php:30-36` |
| 9 | PDF JS finds form/buttons | Yes on variable path | **Unverified** — custom BPP button hooks still fire on `is_customized_product_page()` | `class-bpp-hooks.php:845-854` |
| 10 | Add-to-cart with custom type | Proven on variable | **Runtime required** | — |
| 11 | Quantity required by JS/PHP | Theme min-order + BPP JS (buggy selector) | OI forces qty=1 server-side | `QuantityGuard.php` |
| 12 | Quantity safely fixed to 1 | OI yes | OI yes | High |
| 13 | Guest capacity interferes with PDF form | **N/A** — not implemented | No UI | High |
| 14 | Guest capacity separate from poster state | **Yes** (no implementation) | — | High |
| 15 | Variation assumptions | **Yes** for size/format | Simple product has no variations | High |
| 16–25 | Image upload, mobile, preview fidelity, a11y, AJAX nonces | **Working with limitations** on variable path | **Runtime required** for `online_invitation` | Medium |

### 3.4 Scripts, styles, and localized objects

| Asset | Condition | Objects |
|-------|-----------|---------|
| `public.dist.js`, `public.css`, cropper, textFit, jQuery UI | `is_product()` && `$product->active` | `BPP_PUBLIC_OBJ` |
| `BPP_CUSTOMISER_DATA`, `BPP_PRODUCT` | `BPP_Hooks::localize_script()` on product pages | field definitions |
| `cart-pdf.js` | **Every frontend page** | `BPP_CART_PDF_OBJ` |
| Editor_Asset_Loader (My Account) | `mode=project_edit` | **Does not** localize `BPP_CUSTOMISER_DATA` | `Editor_Asset_Loader.php:77-90` |

### 3.5 DOM requirements (editor)

- `#customizer-area`, `#working_div`, `.customizer-page-content`
- `.product-addons-for-customizer` field containers
- `.bpp-pdf-add-to-cart` custom submit
- Hidden `page[]`, `field[]` inputs generated by `public.dist.js`

### 3.6 Preview and page HTML capture

- Preview: client-side html2canvas + optional `save_cart_pdf` AJAX
- Final design: browser copies `#working_div` innerHTML into `page[]` on submit
- Server does **not** regenerate HTML from fields (`BPP_Product::update_fields()` unused on storefront)

**Classification:** **Working now** on variable path; **runtime test required** for `online_invitation`.

---

## 4. Production evidence

Runtime diagnostic (`tests/audit/runtime-diagnostics.php`):

- **384** variable products vs **275** simple
- **All sampled `_bpp_product` products are `variable`**
- **1** `online_invitation` product (ID 284185) — **no** `_bpp_product` meta, `bpp_active: false`

**Conclusion:** PDF Builder poster designer is **battle-tested on variable WooCommerce products**. The shop has **not yet** configured an `online_invitation` product with an active PDF design.

---

## 5. Minimal interventions for V1 feasibility

### Required (storefront)

1. **Insert field form on `online_invitation` product pages** — smallest options:
   - **OI-owned:** hook `woocommerce_before_add_to_cart_button` → `do_action('woocommerce_bpp_options')` when `bpp/is_product_customizable`
   - **Theme-owned:** add same to `simple.php` when customized (presentation only)
   - **PDF-owned:** hook simple add-to-cart to render attribute/field partial

2. **Size/format defaults for simple products** — hidden inputs from `BPP_Product` defaults when no `pa_bpp_*` UI.

### Strongly recommended

3. Fix BPP JS selector `.bpp-pdf-plugin-add-to-cart` → `.bpp-pdf-add-to-cart`
4. Configure test `online_invitation` product with active `_bpp_product` (clone from existing variable invitation design)

### Not required for stated V1

5. My Account post-purchase editor field form / `BPP_CUSTOMISER_DATA` in `Editor_Asset_Loader`
6. Adapter `generate_pdf()` (explicitly not ready)
7. Separate template ID filter registration

---

## 6. Classification legend

| Status | Meaning |
|--------|---------|
| Working now | Proven by source and/or production data |
| Working with limitations | Functional with known gaps |
| Runtime test required | Static analysis incomplete |
| Not implemented | Missing |
| Unsafe | Security or data-integrity risk |
| Not required for V1 | Out of stated V1 scope |

---

*See `online-invitation-go-no-go.md` for final decision.*
