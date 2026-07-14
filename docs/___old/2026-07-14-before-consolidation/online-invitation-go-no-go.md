# Online Invitation — GO / NO-GO Decision

**Audit date:** 2026-07-14  
**Auditor role:** Senior WordPress / WooCommerce / PHP / frontend integration architect  
**Method:** Evidence-based static analysis + automated tests + limited runtime diagnostics. **No production code changed.**

---

## Executive decision

## **CONDITIONAL GO**

The current **PDF module can serve as the complete poster designer** for Online Invitations V1 **after a small, defined set of changes**. The core designer, cart pipeline, order payload storage, adapter import, and project-owned public runtime are in place. Launch is blocked only by the **`online_invitation` storefront wiring gap** (field form on simple add-to-cart) and **missing end-to-end runtime proof** on a fully configured product.

This is **not** a NO-GO: rebuilding the PDF Designer is unnecessary. This is **not** a full GO: the new product type path is not yet proven in browser.

---

## Recommended architecture

| Layer | Owns |
|-------|------|
| **PDF module** | Admin poster design, `_bpp_product` template, product-page editor JS, cart/order payload, optional print PDF |
| **Online Invitations** | `online_invitation` product type, pricing, project lifecycle, My Account, guests/RSVP/wishlist/photos, public routes, published snapshot storage |
| **Theme** | Layout, WooCommerce templates, calling `BPP_PDF_Plugin::content_single_product()`, styling — **no invitation business logic** |

**V1 integration pattern:**

```text
Pre-purchase:  Theme canvas + PDF field form + PDF JS → WC cart → BPP order file
Post-purchase: OI adapter import (once) → OI project files → OI public shell + sanitized HTML
```

---

## Proven working flow (source and/or runtime evidence)

1. Administrator creates multi-page/folded poster designs with text, image, and layer fields in PDF customizer (`_bpp_product`).
2. **384 variable products** with active `_bpp_product` in production database (runtime diagnostic).
3. Theme renders poster canvas via `BPP_PDF_Plugin::content_single_product()` on product pages.
4. Variable products render size/format + field form via `bpp_wc_attribute_html` → `woocommerce_bpp_options`.
5. Cart captures `field[]`, `page[]`, size/format, optional base64 uploads (`BPP_Woo_Cart_Functions`).
6. Checkout persists order-item JSON file via `BPP_Order_Item_Storage` (HPOS-safe item IDs).
7. Integration adapter `bpp/integration/service` registered (`Online_Invitation_Builder_Adapter`).
8. OI imports order payload idempotently into project-owned storage (`ProjectService::import_for_project`).
9. OI enforces qty=1, mixed cart, classic checkout account rules (269 passing tests).
10. Public loader serves **published** HTML only — not editable state (`PublicInvitationLoader`).
11. Site uses **classic checkout** with HPOS enabled (runtime diagnostic).
12. `online_invitation` product type registered; `bpp/is_product_customizable` bridge implemented.

---

## Unverified flow (requires browser / checkout testing)

1. Complete customer journey on **`online_invitation`** product (not variable).
2. Field form visibility and add-to-cart on simple product template.
3. Size/format defaults when attribute UI absent.
4. Image upload/crop on mobile widths (320/375/768).
5. Preview HTML fidelity vs saved `page[]` on public invitation.
6. Font rendering on public page without PDF editor JS.
7. Mixed cart session survival through login at checkout.
8. Maximum payload size on hosting PHP/nginx limits.
9. Public invitation with PDF plugin deactivated post-import.
10. Visual comparison: Safari vs Chrome poster scaling.

---

## V1 blockers (genuine launch blockers only)

| Blocker | Owner | Fix |
|---------|-------|-----|
| **B-01** Field form + size/format insertion missing on `online_invitation` simple add-to-cart | OI (preferred) or theme or PDF | Hook `woocommerce_bpp_options` (+ hidden size/format defaults) when `bpp/is_product_customizable` |
| **B-02** No staging product with active `_bpp_product` on `online_invitation` | Operations | Configure product 284185 or clone from variable invitation |
| **B-03** E2E manual QA not executed | QA | Run `online-invitation-runtime-test-plan.md` §3 |

**Not V1 blockers** (per stated audit scope):

- Post-purchase PDF editor reopen
- Guest-capacity packages (25/50/100) — code is unlimited; packages are future
- Checkout Blocks support
- Adapter `generate_pdf()`
- Formal PDF HPOS declaration

---

## Minimal required changes

| # | Change | Plugin | Complexity | Why |
|---|--------|--------|------------|-----|
| 1 | Render PDF field form on `online_invitation` product pages | **OI** (hook `woocommerce_before_add_to_cart_button` → `do_action('woocommerce_bpp_options')`) | Very small | `simple.php` never fires `woocommerce_bpp_options` |
| 2 | Output hidden `attribute_pa_bpp_size` / `attribute_pa_bpp_format` from `BPP_Product` defaults when no variable UI | **OI** or **PDF** | Small | Cart expects `$_POST` keys (`class-bpp-woo-cart-functions.php:27-28`) |
| 3 | Configure `online_invitation` WC product with active PDF design + OI presets | **Operations** | Small | Runtime: ID 284185 has no `_bpp_product` |
| 4 | Execute E2E manual test plan | **QA** | Medium | No browser proof yet |
| 5 | Fix BPP JS min-qty selector `.bpp-pdf-plugin-add-to-cart` → `.bpp-pdf-add-to-cart` | **PDF** | Very small | `customizer-public.js:271` vs template class |
| 6 | Ensure `verify_customizable_product()` checks `_bpp_product.active` | **PDF** | Small | Security hardening on product AJAX |

**Online Invitations can avoid PDF changes for #1–2** if OI owns the WooCommerce hooks.

---

## Non-blocking improvements

| Improvement | Owner | When |
|-------------|-------|------|
| Checkout Blocks bridge | OI | After V1 classic path stable |
| Guest-capacity product options | OI | V2 pricing |
| `wrap_public_html` / `enqueue_public_assets` on adapter interface | PDF + OI | Public font fidelity |
| Extract base64 images to files on import | OI | Storage optimization |
| HPOS `FeaturesUtil` on PDF plugin | PDF | WooCommerce marketplace hygiene |
| Stale contract JSON + audit doc refresh | PDF | Documentation |
| OI admin customize link `prdid` param | OI | Admin UX |
| Stronger `PublishedHtmlSanitizer` allowlist | OI | Security depth |

---

## Post-purchase editing decision

**Intentionally excluded from V1** for this go/no-go (per audit brief).

- OI has a Design section and adapter `render_editor()`, but `Editor_Renderer` outputs **canvas only** without `render_product_customizer_form()`.
- Reopening full PDF Designer after purchase is **not required** if customers finalize before checkout.
- Document as: **partially supported in code, intentionally excluded from V1 launch criteria.**

---

## Public rendering decision

**Recommended V1 approach:**

1. **Source:** OI `pages/published/page-*.html` (sanitized at publish time).
2. **Shell:** OI `templates/public/invitation.php` — envelope animation + `.pks-oi` layout.
3. **Poster:** Inline HTML inside a **scoped container** (`.bpp-public-invitation` / `.pks-oi-poster-viewport`), not raw `echo` of draft state.
4. **CSS:** Enqueue BPP `public.css` + inline/output `BPP_fonts_css()` for custom fonts — **without** `public.dist.js` editor bundle.
5. **Security:** Publish via adapter `render_public_html()` + OI `PublishedHtmlSanitizer` + checksum manifest.
6. **Responsive:** CSS transform / aspect-ratio wrapper around fixed-dimension builder HTML; test mobile overflow.

**Not recommended for V1:** iframe (font/CSS inheritance pain), Shadow DOM (limited WP tooling), unsanitized `page[]` echo.

---

## Data ownership decision

| Data | Role |
|------|------|
| Order-item `.text` payload | **Purchase source** and audit trail |
| OI project `state/` + `pages/` | **Long-term editable source** after import |
| OI `pages/published/` | **Public runtime source** |
| Public traffic | **Must not** depend permanently on order payload or PDF plugin |

Import must complete **before** PDF cron removes order meta pointers (~3 months).

---

## Security decision

| Endpoint / surface | V1 usage | Action |
|--------------------|----------|--------|
| Product-page add-to-cart POST | **Continue** | OI validates via adapter at cart |
| `save_cart_pdf` / `bpp_get_cart_item` (guest AJAX) | **Continue** with nonces + rate limits | PDF-owned |
| `create_pdf_html`, order customizer AJAX | **Admin only** — OI must never call | PDF-owned |
| `BPP_Order_Item_Storage::get_payload()` | **Import only** via adapter after order ownership check | OI-owned authorization |
| Raw `page[]` on public routes | **Never** | OI published snapshot only |
| My Account REST save | **Continue** | OI auth + nonces |

---

## Capability matrix

| Capability | Status |
|------------|--------|
| Admin poster creation | Works today and verified |
| Text editing (customer) | Appears to work from source (variable path) |
| Image upload | Appears to work from source (variable path) |
| Image crop | Appears to work from source |
| Font selection | Works with limitation |
| Layer editing | Appears to work from source |
| Multi-page designs | Works today and verified |
| Product-page rendering (variable BPP) | Works today and verified |
| Product-page rendering (`online_invitation`) | **Runtime test required** |
| `online_invitation` product compatibility | Works with limitation |
| Cart persistence | Appears to work from source |
| Mixed carts | Appears to work from source (+ tests) |
| Checkout (classic) | Works today and verified (runtime) |
| Checkout Blocks | Missing (guarded) |
| HPOS (OI) | Works today and verified |
| HPOS (PDF declared) | Not needed for V1 function |
| Order-item file persistence | Appears to work from source |
| Payload import | Appears to work from source (+ tests) |
| Project creation | Appears to work from source (+ tests) |
| Static public rendering | Runtime test required |
| Mobile poster rendering | Runtime test required |
| Font rendering (public) | Runtime test required |
| Security (publish sanitization) | Works with limitation |
| My Account preview | Appears to work from source (+ tests) |
| Post-purchase poster editing | Not needed for V1 |
| Guest capacity packages | Not implemented (unlimited OK for V1) |
| Refunds / restriction | Appears to work from source (+ tests) |
| Expiration | Appears to work from source (+ tests) |
| GDPR cleanup | Implemented but unverified |
| Dependency removal after import | Runtime test required |

---

## Test evidence

### Commands run

```bash
cd prikogstreg-online-invitations && composer test
# OK — 269 tests, 832 assertions

cd pdf-plugin && composer test
# OK — 14 tests, 28 assertions

php prikogstreg-online-invitations/tests/audit/runtime-diagnostics.php
# WP 7.0.1, WC 10.9.4, HPOS yes, classic checkout, 1 online_invitation (no BPP meta)

php -r "require 'wp-load.php'; ..."  # version smoke test
```

### Artifacts created (non-production)

| File | Purpose |
|------|---------|
| `tests/audit/runtime-diagnostics.php` | Read-only environment report |
| `docs/online-invitation-current-state-audit.md` | Phase 1 state |
| `docs/pdf-builder-v1-feasibility.md` | Phases 2–3 |
| `docs/online-invitation-integration-gaps.md` | Gap register |
| `docs/online-invitation-data-flow.md` | Phases 4–9 data flow |
| `docs/online-invitation-runtime-test-plan.md` | Phase 13 plan |
| `docs/online-invitation-go-no-go.md` | This decision |

### Commands not run

- Browser E2E, payment gateway, mobile device lab, load testing

---

## Remaining manual QA

- [ ] Apply B-01 fix (field form on `online_invitation`)
- [ ] Configure product with active `_bpp_product`
- [ ] Complete runtime test plan §3 (47 steps)
- [ ] Verify public poster with PDF plugin deactivated
- [ ] Verify refund restricts public access
- [ ] Check PHP `post_max_size` on staging/production
- [ ] Mobile visual pass (320px, 375px)
- [ ] Keyboard + reduced-motion on envelope

---

## Implementation sequence (shortest safe path to V1)

```text
1. OI: hook woocommerce_bpp_options (+ hidden size/format defaults) for customizable online_invitation
2. Ops: activate _bpp_product on online_invitation product (clone existing variable invitation design)
3. QA: run manual E2E plan → fix any cart/checkout issues
4. OI: verify import + publish + public token renders poster
5. PDF: optional JS selector + AJAX active-template hardening
6. Launch classic checkout only; document Blocks limitation
```

**Estimated effort to clear blockers:** 1–2 days engineering + 1 day QA (excluding guest-capacity packages).

---

## Answer to the core audit question

> Can the current Prikogstreg PDF module already serve as the **complete poster designer** for Online Invitations V1?

**Yes — the PDF module already is the poster designer** for the shop's proven variable-product flow. The gap is not designer capability but **WooCommerce product-type presentation**: `online_invitation` must inherit the same field-form insertion that variable products receive through `bpp_wc_attribute_html`. Once that bridge exists and one product is configured, the existing cart → order → adapter import → project copy → public snapshot pipeline is sufficient for V1 **without a PDF module rewrite**.

---

*Audit complete. No production behavior was modified.*
