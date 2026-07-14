# PDF Builder integration — Prikogstreg Online Invitations

**Status:** Authoritative integration specification  
**Audit source:** `pdf-plugin/docs/online-invitation-integration-audit.md`  
**Contract source:** `pdf-plugin/docs/online-invitation-integration-contract.json`

---

## 1. Current PDF Builder architecture (evidence)

### Bootstrap

```
pdf-plugin/index.php
  → constants BPP_PLUGIN_PATH, BPP_PLUGIN_URLS
  → vendor/autoload.php (Composer; PSR-4 BPP\ → src/ unused for legacy classes)
  → require_once src/*.php
  → new BPP_PDF_Plugin()->init()
      → BPP_Hooks::register()
      → BPP_Menu / BPP_Controller (admin ?page=bpp-customize)
Side-effect constructors:
  BPP_Woo_Cart_Functions, Bpp_cart_pdf_handler, BPP_Order_Item_Customizer, BPP_Cron
```

| Concern | File | Key symbols |
|---------|------|-------------|
| Bootstrap | `pdf-plugin/index.php` | Plugin entry |
| Main class | `pdf-plugin/src/class-bpp-pdf-plugin.php` | `BPP_PDF_Plugin::init()`, `content_single_product()`, asset enqueue |
| Hooks | `pdf-plugin/src/class-bpp-hooks.php` | WC + AJAX registration |
| Template | `pdf-plugin/src/class-bpp-product.php` | `_bpp_product` meta, `render_product_customizer_form()` |
| Cart | `pdf-plugin/src/class-bpp-woo-cart-functions.php` | `woocommerce_add_cart_item_data` |
| Order storage | `pdf-plugin/src/class-bpp-order-item-storage.php` | Filesystem `.text` payloads |
| PDF | `pdf-plugin/src/class-bpp-pdf-generator.php` | mPDF 8.0.14 |
| Frontend bundle | `pdf-plugin/dist/js/public.dist.js` | from `assets/js/public.js` via Webpack |
| Admin bundle | `pdf-plugin/dist/js/admin.dist.js` | from `assets/js/admin.js` |

### Theme dependencies (located)

| Symbol | Location | Line |
|--------|----------|------|
| `BPP_PDF_Plugin::content_single_product( $postID )` | `wp-content/themes/prikogstreg/woocommerce/content-single-product.php` | 60–61 |
| Same (live theme) | `wp-content/themes/prikogstreg--live/woocommerce/content-single-product.php` | 61 |
| `apply_filters( 'bpp_wc_attribute_html', ... )` | `wp-content/themes/prikogstreg/woocommerce/single-product/add-to-cart/variable.php` | 63 |
| `ks_render_custom_field_meta()` | `wp-content/themes/prikogstreg/functions.php` | 95 |
| `get_product_min_order_quantity()` | `wp-content/themes/prikogstreg/core/woocommerce.php` | 790 |

PDF Builder calls `ks_render_custom_field_meta()` without guard (`class-bpp-woo-cart-functions.php:86,131`). Theme provides implementation — **no fatal on this site**.

---

## 2. Cart → order payload shape

### Cart item keys (`woocommerce_add_cart_item_data`, priority 99)

Captured in `BPP_Woo_Cart_Functions::add_cart_item_data()`:

```php
[
  'field'          => [ uuid => [ 'text' => ..., 'font' => ..., 'data' => base64|..., 'visible' => ... ] ],
  'page'           => [ 0 => '<div class="customizer-page-content">...</div>', ... ],
  'pa_bpp_size'    => 'a5',
  'pa_bpp_format'  => 'flat',
  'pdf-files'      => 'comma-separated-filenames',
]
```

Source: `$_POST['field']`, `$_POST['page']`, `$_POST['bpp-pdf-files']`, WC attribute POST keys.

### Online Invitations cart markers (Prompt 11, priority 100)

`InvitationCart::annotate_invitation_line()` adds lightweight markers **after** BPP captures payload:

```php
[
  'pks_oi_invitation'       => true,
  'pks_oi_payload_version'  => '1',
  'pks_oi_payload_checksum' => 'sha256-manifest-hash',
]
```

Checksum manifest: field keys, page count/lengths, size, format, product_id — **not** full field/page blobs.

Validation: `CartPayloadValidator` at add-to-cart and `woocommerce_checkout_create_order_line_item`.

Order reference meta (OI only): `_pks_oi_product_type`, `_pks_oi_payload_version`, `_pks_oi_payload_checksum`.

See `docs/checkout-integration.md`.

### Order item persistence

Hooks:

1. `woocommerce_checkout_create_order_line_item` → `BPP_Hooks::save_order_meta` (field/page/thumbnails meta + triggers file save)
2. `woocommerce_new_order_item` (priority 20) → `BPP_Hooks::persist_order_item_payload_to_file`
3. Same hook → `BPP_Woo_Cart_Functions::ks_add_custom_fields_to_order_item_meta` (human-readable labels)
4. Same hook → `Bpp_cart_pdf_handler::save_pdf_files_order_item_meta` (`_pdf_files`)

Filesystem payload (`BPP_Order_Item_Storage::save_payload`):

```json
{
  "field": { "uuid": { "text": "...", "data": "data:image/jpeg;base64,..." } },
  "page": ["<div>...</div>"],
  "_pages_thumbnails": [{ "thumbnail": "https://...", "page_name": "Forside" }],
  "meta": {
    "order_id": 1001,
    "order_item_id": 1002,
    "product_id": 456,
    "updated_at": "2026-07-14T07:00:00+00:00"
  }
}
```

Path: `uploads/order-customized-items-data/{Y}/{m}/{order_id}-{order_item_id}-{product_id}.text`  
Pointer meta: `_bpp_custom_data_file`, `_bpp_custom_data_version` = `"1"`.

---

## 3. Project import flow

```text
Qualifying order status (on-hold|processing|completed)
  → ProjectOrderListener
  → BPP_Order_Item_Storage::get_payload( order_item_id )  [legacy read — one-time]
  → apply_filters( 'bpp/integration/service' )
  → adapter->load_state( [ 'order_item_id' => ..., 'product_id' => ... ] )
  → adapter->validate_state( $state )
  → ProjectStorage::import_from_builder_state( $project, $canonical_state )
      → write state/current.json
      → split page[] into pages/editable/page-NNN.html
      → write manifest.json (state_version = 1)
  → store order_item_id + _pks_oi_project_id on order item
```

After import, My Account editor uses `load_state( [ 'project_id' => ..., 'mode' => 'edit' ] )` reading project files, **not** order item file.

---

## 4. Adapter interface

Discovery:

```php
$adapter = apply_filters( 'bpp/integration/service', null );
```

Implementation: `BPP\Integration\Online_Invitation_Builder_Adapter` in `pdf-plugin/src/Integration/`.

### Method semantics

| Method | Input context | Output | Persistence owner |
|--------|---------------|--------|-------------------|
| `is_available()` | — | bool | — |
| `get_template_id_for_product( $product_id )` | product ID | int (same as product ID in V1) | — |
| `create_initial_state( $template_id, $context )` | template | empty field/page from `BPP_Product` | — |
| `load_state( $context )` | `order_item_id` OR project state array | canonical state | reads legacy file or accepts OI-supplied array |
| `validate_state( $state, $context )` | state | state or `WP_Error` | — |
| `render_editor( $state, $context )` | state | HTML string | — |
| `enqueue_editor_assets( $context )` | `mode=edit`, not `is_product()` | void | — |
| `save_state( $state, $context )` | state from POST/AJAX | canonical state or `WP_Error` | **OI writes files** |
| `render_preview_html( $state, $context )` | draft state | HTML | — |
| `render_public_html( $state, $context )` | published state | sanitized HTML | — |
| `generate_pdf( $state, $context )` | PNG bytes in context | filenames or `WP_Error` | PDF Builder uploads dir |
| `get_schema_version( $state )` | state | string | — |
| `migrate_state( $state, $from, $to )` | state | state or `WP_Error` | — |

### Context array (server-assembled)

```php
[
  'source'        => 'online_invitation', // required
  'mode'          => 'edit|preview|public|pdf',
  'user_id'       => int,                 // OI validates ownership
  'product_id'    => int,                 // verified against project
  'project_id'    => int,                 // never from browser alone
  'order_id'      => int|null,
  'order_item_id' => int|null,
  'template_id'   => int,
  'locale'        => 'da_DK',
  'size'          => 'a5',
  'format'        => 'flat',
  'state_version' => int,
  'is_preview'    => bool,
  'is_public'     => bool,
]
```

### Required PDF Builder hooks (additive)

| Hook | Type | Purpose |
|------|------|---------|
| `bpp/integration/service` | filter | Adapter discovery |
| `bpp/is_product_customizable` | filter | Map `online_invitation` products |
| `bpp/template_for_product` | filter | Template resolution |
| `bpp/validated_customer_state` | filter | Post-validation |
| `bpp/public_html` | filter | Sanitized public HTML |
| `bpp/before_editor_render` | action | Extension point |
| `bpp/customer_state_saved` | action | After normalization |

### JavaScript events (new, `CustomEvent`)

`bpp:editor-ready`, `bpp:state-loaded`, `bpp:state-changed`, `bpp:validation-failed`, `bpp:save-requested`, `bpp:save-completed`, `bpp:preview-generated`, `bpp:generation-failed`, `bpp:image-uploaded`.

Existing `page-change` jQuery event retained for BC.

---

## 12. Context-aware editor (Prompt 5 — spec)

**Full implementation spec:** `docs/prompt-5-editor-context-spec.md`  
**Code owner:** `pdf-plugin` (deferred — Online Invitations repo documents the contract only)

### Modes

| Mode | Surface | Save path |
|------|---------|-----------|
| `product` | WooCommerce single product (theme) | `save_cart_pdf` + add-to-cart — **unchanged** |
| `project_edit` | My Account project editor (OI Prompt 7+) | `bpp:save-requested` → OI REST |
| `project_preview` | Owner preview | Read-only; no cart |

### Adapter methods (Prompt 5)

| Method | Behavior |
|--------|----------|
| `enqueue_editor_assets( $context )` | Calls extracted `Editor_Asset_Loader`; works when `is_product()` is false |
| `render_editor( $state, $context )` | Scoped `[data-bpp-instance-id]` root; resolves product from context not `global $post` |

### Safe JavaScript context (`BPP_EDITOR_CONTEXT`)

Exposed: `instanceId`, `mode`, `productId`, `schemaVersion`, `pageCount`, `locale`, `size`, `format`, `ajaxUrl` (+ nonce only in `product` mode).

**Never exposed:** `user_id`, `order_id`, `order_item_id`, `project_id`, storage paths, tokens.

### CustomEvents

`bpp:editor-ready`, `bpp:state-loaded`, `bpp:state-changed`, `bpp:validation-failed`, `bpp:save-requested`, `bpp:save-completed`, `bpp:preview-generated`, `bpp:generation-failed`, `bpp:image-uploaded`.

Online Invitations listens for `bpp:save-requested` in Prompt 7+; does not call PDF Builder cart AJAX from project edit.

---

## 13. State validation and public HTML (Prompt 6 — spec)

**Full implementation spec:** `docs/prompt-6-state-and-public-html-spec.md`  
**Fixtures:** `docs/fixtures/builder-canonical-state-v1.json`, `builder-legacy-state-v0.json`, `public-html-sanitizer-fixtures.json`  
**Code owner:** `pdf-plugin` (deferred)

### Canonical state (schema `1`)

| Key | Required | Notes |
|-----|----------|-------|
| `schema_version` | Yes | `"1"`; legacy payloads resolve to `"0"` |
| `template_id` / `product_id` | Yes | WooCommerce product ID in V1 |
| `field` | Yes | UUID → text/image/layer values |
| `page` | Yes | Ordered HTML strings |
| `size` / `format` | Yes | Validated against template |
| `_pages_thumbnails` | Optional | Safe metadata only |

### Adapter methods completed by Prompt 6

| Method | Output |
|--------|--------|
| `validate_state()` | Normalized state or `WP_Error` |
| `migrate_state()` | `0` → `1` supported |
| `render_preview_html()` | Draft HTML (authenticated); filter `bpp/preview_html` |
| `render_public_html()` | Sanitized HTML; filter `bpp/public_html` |
| `save_state()` | Canonical export for OI `ProjectStorage` |

### Sanitization layers

1. **PDF Builder** — `Public_Html_Renderer` builder-specific allowlist (not `wp_kses_post()` alone)
2. **Online Invitations** — `PublishedHtmlSanitizer` before writing `pages/published/` (Prompt 15)

### Responsive note

Public HTML uses fixed-dimension template markup with CSS scale wrapper — not full responsive reflow. Manual mobile QA required.

---

## 5. Product settings (envelope / background / validity)

On `online_invitation` products (WooCommerce product data panel):

| Setting | Meta key | Validation |
|---------|----------|------------|
| Envelope preset | `_pks_oi_envelope_preset` | Allowlist: `classic`, `modern`, `minimal` (exact SVG assets TBD in Prompt 10) |
| Background preset | `_pks_oi_background_preset` | Allowlist: `neutral`, `floral`, `geometric` |
| Builder validity | Derived | `BPP_Hooks::is_customized_product( $id )` AND `_bpp_product.active` |
| Default locale | `_pks_oi_default_locale` | Valid locale string |
| Reminder offset | `_pks_oi_reminder_offset_days` | 1–30, default 5 |

Purchase and publication blocked when builder template missing or inactive.

---

## 6. Pre-purchase flow preservation

**Must not change** for standard/customizable products:

1. Theme calls `content_single_product()`.
2. Theme applies `bpp_wc_attribute_html`.
3. `do_action( 'woocommerce_bpp_options' )` renders field form.
4. JS `acceptCustomOrder()` → `customizerProduct.addToCart()` → `save_cart_pdf` AJAX.
5. Hidden inputs `page[]`, `field[]`, `bpp-pdf-files` submitted with `add_to_cart`.
6. `woocommerce_add_cart_item_data` captures payload.

`online_invitation` products use the **same** pipeline; product type adds quantity=1 enforcement and account requirement at checkout only.

---

## 7. My Account editor differences

| Aspect | Product page | My Account project |
|--------|--------------|-------------------|
| Render | Theme template | `BuilderService` + adapter `render_editor()` |
| Assets | `is_product()` gate | `enqueue_editor_assets( $context )` |
| Save | WC cart POST | Authenticated AJAX/REST → OI `ProjectStorage` |
| Auth | Public customizer | Owner capability + nonce |
| Add to cart | Yes | **No** |

---

## 8. Public HTML pipeline

```text
Publish action
  → entitlement + event validation
  → adapter->validate_state( draft )
  → adapter->render_public_html( draft, [ 'is_public' => true ] )
  → PublishedHtmlSanitizer (OI allowlist)
  → write pages/published/page-NNN.html
  → update published manifest + published_version
```

Fonts: inline `BPP_fonts_css()` output scoped to public view.

**Never:** echo raw `page[]` from editable state on public routes.

---

## 9. Minimum PDF Builder changes (implementation order)

See Prompts 2–6:

1. Regression tests + adapter interface stub — **done (Prompt 2, pdf-plugin — stashed)**
2. AJAX hardening (nonce, rate limits) — **done (Prompt 3, pdf-plugin — stashed)**
3. `Integration_Provider` + `bpp/integration/service` — **Implemented** in `pdf-plugin/src/Integration/` (Prompt 27)
4. Context-aware `enqueue_editor_assets()` + `render_editor()` — **spec (Prompt 5); see `prompt-5-editor-context-spec.md`**
5. `State_Validator`, `Public_Html_Renderer`, schema version — **spec (Prompt 6); see `prompt-6-state-and-public-html-spec.md`**

---

## 10. HPOS note

PDF Builder cron (`class-bpp-cron.php`) queries both `wp_wc_orders` and legacy `wp_posts` for order cleanup. Online Invitations **must** use WooCommerce order/item CRUD only — no direct `wp_posts` order queries.

PDF Builder does not declare HPOS compatibility in plugin header; cron is HPOS-aware.

---

## 11. AJAX endpoint inventory (Prompt 3 — hardened)

See also `security-review.md` §5 for threat controls. All handlers live in `pdf-plugin`.

| Action | PHP handler | JS caller | `nopriv` | Nonce |
|--------|-------------|-----------|----------|-------|
| `save_cart_pdf` | `Bpp_cart_pdf_handler::save_cart_pdf` | `customizer-product.js` | **Yes** (guest checkout) | `bpp_product_ajax` |
| `bpp_get_cart_item` | `Bpp_cart_pdf_handler::bpp_get_cart_item` | `cart-pdf.js` | **Yes** (guest cart) | `bpp_cart_ajax` |
| `create_pdf_html` | `BPP_Hooks::create_pdf_html` | `admin.js` | No | `bpp_admin_ajax` |
| `get_field_data` | `BPP_Hooks::get_field_data` | `event-listener-callbacks.js` | No | `bpp_admin_ajax` |
| `bpp_get_image` | `BPP_Hooks::bpp_get_image` | `functions.js` | No | `bpp_admin_ajax` |
| `bpp_fetch_product_customizer_data` | `BPP_Order_Item_Customizer::bpp_fetch_product_customizer_data` | `admin.js` | No | `bpp_admin_ajax` |
| `bpp_update_product_customizer_data` | `BPP_Order_Item_Customizer::bpp_update_product_customizer_data` | `admin.js` | No | `bpp_admin_ajax` |
| `bpp_fetch_product_customizer_cropper_data` | `BPP_Order_Item_Customizer::bpp_fetch_product_customizer_cropper_data` | `admin.js` | No | `bpp_admin_ajax` |

Nonces are localized via `BPP_PUBLIC_OBJ`, `BPP_CART_PDF_OBJ`, `ajax` (admin), and `window.BPP_AJAX_NONCES` inline script (`BPP_Hooks::localize_script()`).

Webpack bundles rebuilt after JS changes: `npm run build` → `dist/js/public.dist.js`, `dist/js/admin.dist.js`.

**Regression tests:** `pdf-plugin/tests/Unit/BPP_Ajax_SecurityTest.php` + Prompt 2 suite (`composer test` — 41 passing).

---
