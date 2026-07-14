# Online Invitation — Current State Audit

**Audit date:** 2026-07-14  
**Mode:** Read-only (no production behavior changed)  
**Evidence:** Source inspection of `pdf-plugin`, `prikogstreg-online-invitations`, active Prikogstreg theme, local WordPress runtime

---

## 1. Repository inventory

### 1.1 Prikogstreg — PDF modul (`pdf-plugin`)

| Item | Value | Evidence | Confidence |
|------|-------|----------|------------|
| Plugin name | Prikogstreg - PDF modul | `pdf-plugin/index.php:4` | High |
| Version | `1.0` | `index.php:6` | High |
| Main file | `index.php` | `index.php` | High |
| Text domain | `pdf-plugin` | `index.php:9` | High |
| Class prefix | `BPP_` (legacy global classes) | `src/class-*.php` | High |
| Composer PSR-4 | `BPP\` → `src/` (Integration layer) | `composer.json`, `index.php:49-50` | High |
| Integration adapter | `BPP\Integration\Online_Invitation_Builder_Adapter` | `src/Integration/`, `index.php:50` | High |
| Min PHP/WP declared | **Not in plugin header** | `index.php` | High |
| WooCommerce | Implicit hard dependency | `WC_*` usage throughout | High |
| HPOS declared | **No** `FeaturesUtil::declare_compatibility` | searched entire plugin | High |
| HPOS runtime | Cron queries `wp_wc_orders` + legacy `wp_posts` | `class-bpp-cron.php:160-228` | High |
| JS build | Webpack 5 (`admin`, `public`) + raw `cart-pdf.js` | `webpack.config.js`, `package.json` | High |
| PHPUnit | 14 tests, 28 assertions — **OK** | `composer test` run 2026-07-14 | High (runtime) |

### 1.2 Prikogstreg Online Invitations (`prikogstreg-online-invitations`)

| Item | Value | Evidence | Confidence |
|------|-------|----------|------------|
| Plugin name | Prikogstreg Online Invitations | `prikogstreg-online-invitations.php:3` | High |
| Version | `0.1.0` | `prikogstreg-online-invitations.php:6`, `PKS_OI_VERSION` | High |
| Namespace | `PrikOgStreg\OnlineInvitations\` | `composer.json` PSR-4 | High |
| Text domain | `prikogstreg-online-invitations` | `prikogstreg-online-invitations.php:10` | High |
| Hook prefix | `pks_oi_` | `.cursor/rules.md`, code | High |
| Min PHP | 8.1 | `Requirements::MIN_PHP_VERSION` `src/Bootstrap/Requirements.php:15` | High |
| Min WordPress | 6.5 | `Requirements.php:16` | High |
| Min WooCommerce | 8.0 | `Requirements.php:17` | High |
| HPOS | **Required** — boot aborts if disabled | `Requirements.php:69-75`, `Compatibility.php:19-37` | High |
| HPOS declared | Yes — `custom_order_tables` | `Compatibility::declare_hpos_compatibility()` | High |
| PHPUnit | 269 tests, 832 assertions — **OK** (2 deprecations) | `composer test` run 2026-07-14 | High (runtime) |
| DB schema version | `1` (`PKS_OI_DB_VERSION`) | `prikogstreg-online-invitations.php:23` | High |

### 1.3 Active theme (Prikogstreg)

| Item | Value | Evidence | Confidence |
|------|-------|----------|------------|
| Theme path | `wp-content/themes/prikogstreg` | workspace | High |
| PDF left column | `BPP_PDF_Plugin::content_single_product()` | `woocommerce/content-single-product.php:60-64` | High |
| Attribute UI (variable) | `apply_filters('bpp_wc_attribute_html', …)` | `woocommerce/single-product/add-to-cart/variable.php:35-63` | High |
| Simple add-to-cart | Standard `simple.php` — **no** `bpp_wc_attribute_html` | `woocommerce/single-product/add-to-cart/simple.php` | High |
| Theme helpers | `ks_render_custom_field_meta()`, `get_product_min_order_quantity()` | `functions.php:97-123`, `core/woocommerce.php:790-806` | High |
| `online_invitation` theme branches | **None** on product page | theme grep | High |

### 1.4 Local runtime (verified 2026-07-14)

Diagnostic script: `tests/audit/runtime-diagnostics.php`

| Item | Value | Confidence |
|------|-------|------------|
| WordPress | 7.0.1 | High (runtime) |
| WooCommerce | 10.9.4 | High (runtime) |
| PHP | 8.4.22 | High (runtime) |
| HPOS | enabled | High (runtime) |
| Checkout page | ID 62, template `page-checkout.php`, classic `[woocommerce_checkout]` | High (runtime) |
| Checkout Blocks | **not** used on checkout page | High (runtime) |
| `online_invitation` products | 1 published (ID 284185), **no** `_bpp_product` meta | High (runtime) |
| Active `_bpp_product` products | 20+ sampled — **all `variable`** type | High (runtime) |

---

## 2. Phase 1 answers — repository state

### 2.1 Plugins and modules interacting with PDF module

| Actor | Interaction | Evidence |
|-------|-------------|----------|
| **Theme** | Renders canvas via `content_single_product()`; variable products pipe attributes through `bpp_wc_attribute_html`; cart meta via `ks_render_custom_field_meta()` | theme files above |
| **Online Invitations** | `bpp/is_product_customizable` filter; cart validation; order→project import via adapter `load_state(mode=import)` | `BuilderIntegration.php:13-28`, `ProjectService.php:272-296` |
| **WooCommerce** | Cart, checkout, order item meta, product types | both plugins |
| **ACF** | Font CPT fields, product `minimum_order` meta | `class-bpp-pdf-plugin.php:17-121`, theme |

### 2.2 PDF Builder classes actually loaded

Bootstrap chain (`pdf-plugin/index.php:12-50`):

- Legacy: `BPP_PDF_Plugin`, `BPP_Hooks`, `BPP_Product`, `BPP_Order_Item_Storage`, `BPP_Woo_Cart_Functions`, `Bpp_cart_pdf_handler`, `BPP_Order_Item_Customizer`, `BPP_Cron`
- Namespaced: `BPP\Integration\Integration_Provider` (registers `bpp/integration/service`)

Side-effect constructors at file load: cart handler, woo cart functions, order item customizer, cron (`class-bpp-cart-pdf-handler.php:258`, etc.)

### 2.3 Online Invitations features that exist (code-level)

| Feature | Status | Primary evidence |
|---------|--------|------------------|
| `online_invitation` product type | Implemented | `ProductTypeRegistrar.php:12-25` |
| Quantity forced to 1 | Implemented | `QuantityGuard.php`, `WC_Product_Online_Invitation.php:26-36` |
| Mixed cart support | Implemented | `InvitationCart.php`, integration tests |
| Builder adapter discovery | Implemented | `BuilderService.php:33-50` |
| Cart payload validation | Implemented | `CartPayloadValidator.php` |
| Classic checkout account requirement | Implemented | `AccountRequirement.php` |
| Checkout Block guard | Implemented (blocks unsupported path) | `CheckoutBlockGuard.php` |
| Order→project creation (idempotent) | Implemented | `ProjectOrderListener.php`, `ProjectService.php` |
| Project file storage + manifest | Implemented | `ProjectStorage.php`, `StoragePath.php` |
| Published snapshot + public loader | Implemented | `ProjectPublishService.php`, `PublicInvitationLoader.php` |
| My Account sections (11) | Implemented (UI) | `ProjectSections.php`, templates |
| Guest management (unlimited) | Implemented | `GuestService.php` |
| RSVP, wishlist, photos, delivery | Implemented | respective domain modules |
| Guest-capacity packages | **Not implemented** (unlimited only) | `GuestService.php` docblock, no product meta |
| Post-purchase PDF editor (V1 audit scope) | Partially wired, **not required for stated V1** | `ProjectController::render_design()` |

### 2.4 `online_invitation` registration

**Yes — registered.**

- Type slug: `online_invitation` (`ProductMeta::TYPE`)
- Class: `WC_Product_Online_Invitation extends WC_Product_Simple` (`WC_Product_Online_Invitation.php:8`)
- Add-to-cart: `woocommerce_online_invitation_add_to_cart` → `woocommerce_simple_add_to_cart` (`ProductTypeRegistrar.php:17`)

**Confidence:** High (source + runtime: 1 product in DB with this type)

### 2.5 Theme recognition of `online_invitation`

**No product-page branching.** Theme uses the same BPP left-column path for all products when `BPP_PDF_Plugin` exists. `online_invitation` uses `simple.php` add-to-cart, not `variable.php`.

**Confidence:** High (source)

### 2.6 PDF Builder product-type check vs `_bpp_product.active`

Customization gate checks **`_bpp_product` active flag**, not WooCommerce product type:

```911:916:pdf-plugin/src/class-bpp-hooks.php
public function is_customized_product( $product_id ) {
	$product = new BPP_Product( $product_id );
	$active  = $product->active;
	return (bool) apply_filters( 'bpp/is_product_customizable', $active, (int) $product_id );
}
```

Online Invitations extends this for `online_invitation` via `BuilderIntegration::filter_product_customizable()` (`BuilderIntegration.php:17-28`).

**Confidence:** High

### 2.7 One product = one PDF design?

**Yes in V1.** Template ID defaults to product ID (`Online_Invitation_Builder_Adapter::get_template_id_for_product()` lines 24-44). Filter `bpp/template_for_product` exists but OI does not register a separate template ID.

**Confidence:** High

### 2.8 Separate design/template ID

**Filter exists, not used by OI.** `bpp/template_for_product` in adapter line 29. No OI registration found.

**Confidence:** High

### 2.9 Quantity

| Layer | Behavior | Evidence |
|-------|----------|----------|
| OI `online_invitation` | min=max=1, sold individually | `WC_Product_Online_Invitation.php`, `QuantityGuard.php` |
| BPP legacy | ACF `minimum_order_qty`, theme `get_product_min_order_quantity()` | `class-bpp-hooks.php:142-154`, theme |
| BPP JS min-qty | Selector mismatch `.bpp-pdf-plugin-add-to-cart` vs `.bpp-pdf-add-to-cart` | template vs `customizer-public.js:271` |

OI server-side qty=1 enforcement is solid. BPP client min-order check may be ineffective on mismatch.

**Confidence:** High (source); BPP JS mismatch — Medium for runtime impact on `online_invitation`

### 2.10 Mixed carts

**Supported.** OI annotates only invitation lines; `InvitationCart` + tests in `CartCheckoutTest.php`. BPP cart handler applies per active `_bpp_product` line.

**Confidence:** High (tests); runtime browser test still required

### 2.11 Checkout implementation

| Path | Status | Evidence |
|------|--------|----------|
| Classic checkout | **Production path** | runtime: `page-checkout.php`, no checkout block |
| Checkout Blocks | **Blocked** for invitation carts | `CheckoutBlockGuard.php:19-38` |

### 2.12 WooCommerce product blocks

Not audited on storefront product templates. Product pages use theme PHP overrides, not block templates.

**Confidence:** Medium — runtime verification optional

### 2.13 HPOS

| Plugin | Declared | Runtime |
|--------|----------|---------|
| Online Invitations | Yes, required | Works via `wc_get_order()` |
| PDF module | No formal declaration | Cron + order screens HPOS-aware |

**Confidence:** High

---

## 3. Stale documentation warnings

These artifacts predate current code and must not be trusted without re-verification:

| Document | Stale claim | Current source truth |
|----------|-------------|---------------------|
| `pdf-plugin/docs/online-invitation-integration-audit.md` | No formal API | `BPP\Integration\` adapter now exists |
| `online-invitation-integration-contract.json` | `wp_ajax_nopriv_create_pdf_html` | Admin-only AJAX in `class-bpp-hooks.php:15-19` |
| `prikogstreg-online-invitations/readme.txt:43` | Guest cap on product | No implementation — unlimited only |
| `.cursor/rules.md` | V1 requires post-purchase editor | **User audit scope excludes this for V1 go/no-go** |

---

## 4. Commands actually run

```bash
# Online Invitations
cd prikogstreg-online-invitations && composer test
# Result: OK — 269 tests, 832 assertions

# PDF plugin
cd pdf-plugin && composer test
# Result: OK — 14 tests, 28 assertions

# Runtime diagnostics
php prikogstreg-online-invitations/tests/audit/runtime-diagnostics.php
# Result: JSON report (WP 7.0.1, WC 10.9.4, HPOS yes, classic checkout)

# WordPress load smoke test
php -r "require wp-load.php; ..."
# Result: WP + WC versions confirmed
```

### Commands not run

- Full browser E2E (product customize → checkout → public invitation)
- Checkout with real payment gateway
- Mobile device matrix
- Load test with maximum-size `page[]` payloads

---

## 5. Uncertainties requiring runtime verification

1. **`online_invitation` simple product page** — field form (`woocommerce_bpp_options`) does not fire on theme `simple.php` (see `pdf-builder-v1-feasibility.md`).
2. Visual fidelity of published poster HTML without editor JS on public invitation.
3. Mixed cart + session restore after login during checkout.
4. Order-item payload size limits on production PHP/hosting.
5. Whether product ID 284185 can be configured end-to-end without code changes.

---

*Next documents: `pdf-builder-v1-feasibility.md`, `online-invitation-integration-gaps.md`, `online-invitation-data-flow.md`, `online-invitation-runtime-test-plan.md`, `online-invitation-go-no-go.md`.*
