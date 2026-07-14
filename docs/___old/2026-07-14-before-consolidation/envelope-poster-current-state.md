# Envelope + Poster — Current State

**Date:** 2026-07-14  
**Mode:** Analysis only — no production code changed  
**Evidence:** Source inspection + `tests/audit/runtime-diagnostics.php` (runtime) + `composer test` (269/269 OI, 14/14 PDF)

---

## 1. Purpose of this document

Records the **current** implementation of:

- Envelope configuration (product + project)
- PDF Builder inner poster (pre-purchase)
- Published poster inside the public envelope (post-purchase)
- Gaps between intended V1 experience and what the code does today

Distinguishes **source evidence** from **runtime/browser verification**.

---

## 2. Twenty audit questions (with evidence)

### Q1. Does Online Invitations already have envelope product fields?

**Yes.**

| Symbol | Location |
|--------|----------|
| `ProductMeta::ENVELOPE_PRESET` | `src/WooCommerce/ProductType/ProductMeta.php:14` |
| `ProductMeta::ENVELOPE_PREVIEW_REF` | `ProductMeta.php:15` |
| `ProductMeta::BACKGROUND_PRESET` | `ProductMeta.php:16` |
| Admin UI | `ProductDataPanel::render_panel()` lines 72–100 |
| Validation | `BuilderValidity::validate()` lines 22–29 |

**Confidence:** High (source)

---

### Q2. Which envelope-related settings already exist?

| Setting | Meta key | Admin control | Used on public |
|---------|----------|---------------|----------------|
| Envelope preset | `_pks_oi_envelope_preset` | Select: `classic`, `modern`, `minimal` | CSS class on envelope |
| Background preset | `_pks_oi_background_preset` | Select: `neutral`, `floral`, `geometric` | CSS class on envelope |
| Envelope preview ref | `_pks_oi_envelope_preview_ref` | Optional text input | **Not used** on storefront/public |
| Default locale | `_pks_oi_default_locale` | Text | Copied to project `locale` |
| Reminder offset | `_pks_oi_reminder_offset_days` | Number | Copied to project |
| Guest photos default | `_pks_oi_guest_photos_default` | Checkbox | Copied to `guest_photos_enabled` |
| Wishlist default | `_pks_oi_wishlist_default` | Checkbox | Copied to `internal_wishlist_enabled` |
| Builder optional (test) | `_pks_oi_builder_optional` | Checkbox | Disables BPP requirement |

Allowlists: `ProductMeta::envelope_presets()`, `ProductMeta::background_presets()` — lines 33–49.

**Confidence:** High (source)

---

### Q3. How are envelope images stored?

**Not as attachment IDs or product gallery images for the envelope shell.**

| Storage type | What it holds | Evidence |
|--------------|---------------|----------|
| **Preset slug strings** | `classic`, `modern`, `minimal` | `ProductMeta::ENVELOPE_PRESET` |
| **CSS-driven visuals** | Envelope/background appearance | `assets/src/scss/public.scss` — `.pks-oi-envelope--classic`, `--bg-neutral`, etc. |
| **Optional admin text** | `ENVELOPE_PREVIEW_REF` — “slug, note, or internal asset key” | `ProductDataPanel.php:83–90` — **no runtime consumer found** |
| **Product gallery** | Standard WC gallery | Theme left column uses **BPP canvas** when `BPP_PDF_Plugin` exists, not gallery for envelope |

Envelope is **preset-ID + SCSS**, not WooCommerce product media.

**Confidence:** High (source)

---

### Q4. Is there already an envelope/background preset system?

**Yes — fully implemented for V1.**

- Product meta + allowlists (`ProductMeta`)
- Project DB columns `envelope_preset`, `background_preset` (`src/Database/Schema.php:69–70`)
- Snapshot at project creation (`ProjectFactory::build_initial_row()` lines 102–103)
- Public CSS modifiers (`public.scss` + `templates/public/envelope.php:20`)
- View model resolution with fallbacks (`EnvelopeViewModel::from_resolution()` lines 40–46)

**Confidence:** High (source)

---

### Q5. Does the public template already render an envelope?

**Yes.**

| File | Role |
|------|------|
| `templates/public/invitation.php` | Full HTML document; includes envelope partial |
| `templates/public/envelope.php` | Animated envelope shell, open button, content region |
| `assets/src/js/public.js` | Open animation + `prefers-reduced-motion` bypass |
| `src/Public/PublicController.php` | Builds `EnvelopeViewModel`, renders template |

Envelope structure:

```48:50:templates/public/envelope.php
		<div class="pks-oi-envelope__invitation bpp-public-invitation">
			<?php echo $view->invitation_html; ?>
```

RSVP / wishlist / photos render **below** the poster inside `.pks-oi-envelope__content` (lines 52–77).

**Confidence:** High (source); animation fidelity — runtime browser test required

---

### Q6. Does the project snapshot envelope configuration at purchase/import?

**Yes — at project row creation (before import completes).**

`ProjectFactory::build_initial_row()` copies from WooCommerce product:

```102:103:src/Domain/Project/ProjectFactory.php
			'envelope_preset'          => is_object( $product ) ? ProductMeta::read_envelope_preset( $product ) : '',
			'background_preset'        => is_object( $product ) ? ProductMeta::read_background_preset( $product ) : '',
```

Also copies: `locale`, `reminder_offset_days`, `guest_photos_enabled`, `internal_wishlist_enabled` (lines 97–101).

Poster HTML is imported separately via `ProjectService::import_for_project()` → `ProjectStorage::import_from_builder_state()`.

**Confidence:** High (source + integration tests)

---

### Q7. Does changing the WooCommerce product later affect existing projects?

**Envelope/background: No — project owns snapshotted values.**

- Project row stores `envelope_preset` / `background_preset` at creation
- `EnvelopeViewModel` reads **project** row, not live product meta (lines 40–46)
- Poster HTML lives in project storage files, not product meta

Changing product `_bpp_product` or envelope presets does **not** retroactively change published projects.

**Confidence:** High (source)

---

### Q8. Does the customer-designed BPP HTML already appear inside the envelope?

**Yes — when published and loaded successfully.**

Flow:

1. `PublicController::maybe_render_invitation()` → `PublicInvitationLoader::load_published_content()`
2. Published pages read from `pages/published/page-*.html` (not order file)
3. HTML passed to `EnvelopeViewModel::from_resolution()` as `invitation_html`
4. Rendered inside `.pks-oi-envelope__invitation` (`envelope.php:48–49`)

**Prerequisite:** Project must be published with valid `published_manifest_path`. Unpublished projects return unavailable (observed in prior runtime debugging).

**Confidence:** High (source); visual fidelity — runtime required

---

### Q9. Does the public renderer already support several BPP pages?

**Partially — depends on publish path.**

| Stage | Multi-page behavior | Evidence |
|-------|---------------------|----------|
| **Import** | Each `page[]` → separate `pages/editable/page-N.html` | `ProjectStorage::import_from_builder_state()` lines 276–283 |
| **Publish (adapter path)** | `render_public_html()` returns **one combined HTML** string; stored as **single** published page index `1` | `ProjectPublishService::render_public_pages()` lines 108–121 |
| **Publish (fallback)** | Iterates each raw `page[]` → multiple published files | lines 125–133 |
| **Public load** | Iterates **all** entries in published manifest, concatenates | `PublicInvitationLoader` lines 42–68 |

`BPP\Integration\Public_Html_Renderer` wraps each page in `.bpp-public-page` but outputs one combined string (adapter).

**V1 result:** Multiple BPP pages appear as **sequential blocks in one HTML blob**, not as a turn.js page-flip UI on the public route.

**Classification:** Works with limitation  
**Confidence:** High (source)

---

### Q10. Does the public renderer load the required BPP CSS?

**No — not today.**

`PublicController::enqueue_assets()` loads:

- `pks-oi-public` CSS/JS only (`PublicController.php:110–123`)
- Optional `adapter->enqueue_public_assets()` — **method does not exist** on `Online_Invitation_Builder_Adapter` (grep `pdf-plugin/src` — no matches)

**BPP `dist/css/public.css` is not enqueued** on `/invitation/{token}/`.

**Confidence:** High (source)  
**Impact:** Poster layout/fonts may break on public page — **runtime verification required**

---

### Q11. Does it load BPP font CSS?

**No.**

- `BPP_fonts_css()` exists in `pdf-plugin/functions.php:3–39`
- Called on product canvas via `BPP_PDF_Plugin::content_single_product()` line 359
- **Not called** from `PublicController` or `PublicInvitationLoader`

**Confidence:** High (source)

---

### Q12. Does it avoid loading the editor JavaScript?

**Yes.**

Public route enqueues only `pks-oi-public` JS (`public.js` — envelope + RSVP/wishlist/photos).  
No `public.dist.js`, cropper, textFit, or jQuery UI on public invitation.

BPP product-page editor JS gated by `is_product()` in `BPP_PDF_Plugin::wp_enqueue_scripts()` line 208.

**Confidence:** High (source)

---

### Q13. Does the `online_invitation` product already display the BPP canvas?

**Conditionally yes — when `_bpp_product` is active.**

- Theme always calls `BPP_PDF_Plugin::content_single_product($postID)` when class exists (`content-single-product.php:60–61`)
- Canvas renders only if `$product->active` (`class-bpp-pdf-plugin.php:351–353`)
- OI marks product customizable via `BuilderIntegration::filter_product_customizable()` when template active

**Runtime (local):** Product 284185 (`online_invitation`) has **no** `_bpp_product` → **no canvas today**.

**Confidence:** High (source + runtime diagnostic)

---

### Q14. Does it display the BPP field form?

**No — not on the `online_invitation` simple add-to-cart path.**

Field form chain for **variable** products:

`variable.php` → `bpp_wc_attribute_html` → `customized-product-attribute-html.php:152` → `woocommerce_bpp_options` → `BPP_Hooks::customizer_view_right()` → `BPP_Product::render_product_customizer_form()`

`online_invitation` uses:

`ProductTypeRegistrar.php:17` → `woocommerce_simple_add_to_cart` → theme `simple.php` — **no** `woocommerce_bpp_options`

**No OI hook** registers field form output (grep `prikogstreg-online-invitations/src` — only `ProductPagePlaceholder`).

**Confidence:** High (source)

---

### Q15. Does it submit required hidden `page[]` and `field[]`?

**Only if BPP public JS + field form are present.**

- BPP JS (`public.dist.js`) generates hidden inputs on customize flow
- Without field form, customer cannot complete canonical customize flow
- `CartPayloadValidator::build_state_from_request()` expects `$_POST['field']` and `$_POST['page']` (lines 74–94)
- `InvitationCart::validate_builder_payload()` rejects empty/invalid payload (lines 24–44)

**Confidence:** High (source) — **blocked without Q14 fix**

---

### Q16. Does it supply size and format defaults?

**Not automatically on simple products.**

- Variable path: customer selects `pa_bpp_size` / `pa_bpp_format` via attribute UI
- Cart capture: `$_POST['attribute_pa_bpp_format']`, `attribute_pa_bpp_size` (`class-bpp-woo-cart-functions.php:27–28`)
- `BPP_Product` model has `default_size`, `foldable` (`class-bpp-product.php` save/load) — **no OI hook outputs hidden defaults** on simple template

Empty size/format may still import if adapter normalizes from template — **runtime test required**.

**Confidence:** Medium (source)

---

### Q17. Does it use exactly one add-to-cart form and button?

**Mostly yes — with BPP active.**

- One `<form class="cart">` in `simple.php:37`
- Standard `single_add_to_cart_button` in `simple.php:58–65`
- BPP adds **second** button via `woocommerce_after_add_to_cart_quantity` → `add_custom_add_to_cart_button()` (`class-bpp-hooks.php:845–854`)
- BPP inline CSS hides default button when `body.customize-product` (`class-bpp-pdf-plugin.php:225–228`)

Effective UX: **one visible BPP button** (`.bpp-pdf-add-to-cart`) when customized.

**Confidence:** High (source); verify no duplicate click handlers — runtime

---

### Q18. Can the entire flow be implemented without changing pdf-plugin?

**Yes — for V1 storefront + public display.**

OI can:

1. Hook `woocommerce_before_add_to_cart_button` → `do_action('woocommerce_bpp_options')` when customizable
2. Output hidden size/format from `BPP_Product` defaults (read-only `new BPP_Product($id)`)
3. On public route, enqueue `BPP_PLUGIN_URLS . 'dist/css/public.css'` and inline `BPP_fonts_css()` if function exists
4. Fix admin customize link query param in `ProductDataPanel` (`prdid` vs `product_id`)

All reuse existing PDF hooks and globals — **no pdf-plugin file edits required**.

Optional pdf-plugin improvements (JS selector fix, `enqueue_public_assets` on adapter) are **not unavoidable**.

**Confidence:** High (source)

---

### Q19. Which findings from previous audits are now outdated?

| Prior claim | Current truth |
|-------------|---------------|
| “No formal PDF integration API” | **Outdated** — `BPP\Integration\Online_Invitation_Builder_Adapter` + `bpp/integration/service` (`pdf-plugin/index.php:50`) |
| “`wp_ajax_nopriv_create_pdf_html` unauthenticated” | **Outdated** — admin-only in `class-bpp-hooks.php:15–19` |
| “ProjectService `ProductMeta` import bug for order item meta” | **Outdated** — `ProjectMeta` resolves in same namespace `Domain\Project` |
| “Post-purchase editor required for V1” | **Out of scope** per current architecture brief |
| “Guest-capacity packages exist” | **Outdated** — unlimited only (`GuestService` docblock); readme line 43 is doc drift |
| “Adapter proposed only / not implemented” | **Outdated** — shipped in pdf-plugin `src/Integration/` |
| “Field form gap on simple products” | **Still valid** — confirmed again in this inspection |
| “Public BPP CSS not loaded” | **Still valid** — `enqueue_public_assets` not implemented |
| “No configured online_invitation + BPP product” | **Still valid** — runtime: product 284185 |

---

### Q20. What is the smallest remaining implementation?

See `docs/envelope-poster-integration-plan.md` § Smallest remaining work.

Summary:

1. **OI `StorefrontBuilderBridge`** — field form + hidden size/format on `online_invitation` (no theme/pdf change)
2. **OI `PosterDisplayAssets`** — BPP `public.css` + `BPP_fonts_css()` on public invitation route
3. **Admin ops** — activate `_bpp_product` on invitation product; set envelope/background presets
4. **Fix** `ProductDataPanel` customize URL (`prdid` parameter)
5. **Manual E2E QA** — customize → purchase → publish → public envelope

**No theme changes required** if OI owns storefront hooks.  
**No pdf-plugin changes required** for V1.

---

## 3. Component inventory

### 3.1 Already implemented (high confidence)

| Component | Key symbols |
|-----------|-------------|
| Product envelope fields | `ProductMeta`, `ProductDataPanel` |
| Product validation | `BuilderValidity` |
| BPP bridge (eligibility) | `BuilderIntegration` |
| Cart validation | `CartPayloadValidator`, `InvitationCart` |
| Order markers | `OrderItemPayload` |
| Project creation + import | `ProjectOrderListener`, `ProjectService`, `ProjectStorage` |
| Envelope snapshot on project | `ProjectFactory::build_initial_row` |
| Publish snapshot | `ProjectPublishService`, `ProjectStorage::publish_snapshot` |
| Public envelope + poster slot | `EnvelopeViewModel`, `envelope.php`, `PublicController` |
| Published HTML only (no draft) | `PublicInvitationLoader`, `PublishedHtmlSanitizer` |
| Theme BPP canvas (left column) | `content-single-product.php:60–61` |

### 3.2 Missing or incomplete (high confidence)

| Gap | Impact |
|-----|--------|
| Storefront field form for `online_invitation` | Blocks pre-purchase customize |
| Public BPP CSS/fonts | May break poster fidelity |
| Configured product (BPP + presets) | Blocks E2E testing |
| Admin customize link param | May break admin workflow |
| Multi-page public navigation UI | Optional; combined HTML acceptable for V1 |

---

## 4. Runtime evidence (this session)

```bash
php tests/audit/runtime-diagnostics.php
# online_invitation product 284185: has_bpp_meta=false, bpp_active=false
# checkout: classic page-checkout.php, no blocks
# all sampled _bpp_product products: variable type

composer test  # prikogstreg-online-invitations: 269 OK
composer test  # pdf-plugin: 14 OK
```

**Not run:** Browser product-page customize, checkout, public visual comparison.

---

## 5. Conclusion (current state)

The **envelope system and public shell are largely built** inside Online Invitations. The **PDF inner poster pipeline is built** for variable products and partially wired for `online_invitation` (canvas + cart validation + import). The **remaining gaps are narrow**: storefront field-form insertion, public poster asset loading, and product/configuration + QA — all achievable **inside Online Invitations without pdf-plugin edits**.
