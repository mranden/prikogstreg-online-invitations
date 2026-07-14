# Envelope + Poster — Final Release Review

**Date:** 2026-07-14  
**Decision:** **CONDITIONAL GO**  
**Reviewer:** Final integration engineer (automated + limited runtime evidence)

---

## Executive summary

The Online Invitations + PDF Builder integration is **architecturally complete** for V1. Automated test coverage is strong (328 OI + 14 PDF tests). Post-purchase project import, envelope snapshot, publish pipeline, and public invitation rendering (including pdf-plugin-independent poster CSS) are **proven in code and tests**.

Launch is **conditional** because the **storefront customize → checkout** path is **not yet proven in browser** on a fully configured `online_invitation` product. The sole staging product (284185) has **inactive BPP**, **no canvas**, **no field form**, and **incomplete field configuration**.

This is a **configuration + runtime QA gap**, not a requirement to rebuild the PDF Designer or move platform logic into pdf-plugin.

---

## Decision: CONDITIONAL GO

| Criteria | Status |
|----------|--------|
| Architecture matches contract | **Yes** |
| Automated regression suite green | **Yes** (328 + 14) |
| Post-purchase → public path proven | **Yes** (tests + source) |
| Storefront customize → cart proven | **No** (runtime HTML fails on 284185) |
| Full browser E2E | **No** |
| Production-ready test product | **No** |

**GO when:** Disposable product is fully configured + browser E2E checklist passes.  
**NO-GO if:** After configuration, canvas/form/cart/checkout still fail.

---

## Proven working behavior

### Commerce & product type (source + tests)

- `online_invitation` WooCommerce product type registered
- Quantity forced to 1 (product class, cart guard, store API limits)
- Mixed cart annotation without breaking other lines
- Classic checkout + HPOS compatibility
- `StorefrontBuilderBridge` hooks `woocommerce_before_add_to_cart_button` → `woocommerce_bpp_options` + hidden size/format defaults
- Cart payload validation (`field`, `page`, size, format, checksum)
- Order item reference meta persistence

### Post-purchase (tests)

- Idempotent project creation from qualifying orders
- Envelope snapshot to `envelope/manifest.json` (+ optional image copy)
- BPP state/HTML import via adapter (no scattered `BPP_Order_Item_Storage` in OI)
- Import guards, retry on failed import, checksum validation
- Envelope immune to later product meta changes

### Publication & public (tests + source)

- Publish writes verified `pages/published/` + manifest
- `PublishedHtmlSanitizer` blocks script, iframe, form, expression, @import
- Poster asset snapshot at publish (`published/poster-display.css`, `poster-manifest.json`)
- Public route: token → entitlement → envelope → open → published HTML
- Responsive poster viewport (`.pks-oi-poster-viewport`) without editor JS
- Multi-page navigation when multiple published pages exist
- Generic + personal token resolution unchanged
- RSVP / wishlist / photos chain in `InvitationFlowChainTest`

### PDF module (tests + production data)

- 384 variable products with active BPP in database
- Cart/order payload pipeline unchanged for existing products
- Integration adapter registered (`bpp/integration/service`)

---

## Unverified behavior (requires browser)

1. BPP canvas visible on `online_invitation` simple product template
2. Field form renders once (no duplicate with theme)
3. Text editing updates canvas live
4. Image upload + crop on desktop and mobile (320/375px)
5. Optional layer toggle
6. Preview modal fidelity
7. Add-to-cart with customized payload
8. Mixed cart session restore after login
9. Payment gateway completion on staging
10. My Account project UI with real imported design
11. Publish button with real event data
12. Envelope animation fidelity (Safari, reduced motion)
13. Poster visual fidelity vs BPP editor (fonts, images, layers)
14. Public page at 200%/400% zoom
15. Keyboard envelope open + poster page navigation
16. Public invitation after pdf-plugin deactivated (runtime confirm)
17. Page-cache / CDN interference on `/invitation/{token}/`

---

## Genuine launch blockers

| ID | Blocker | Owner | Severity |
|----|---------|-------|----------|
| B-01 | Test product 284185: `_bpp_product.active = false` | Admin/ops | **High** |
| B-02 | Product page renders **empty** left column (no BPP canvas) | Admin + verify after B-01 | **High** |
| B-03 | BPP template: `a5` unavailable; only `a6` permitted — may conflict with OI defaults | Admin or OI filter | **Medium** |
| B-04 | Test product missing image field (crop/upload untestable) | Admin | **Medium** |
| B-05 | Full browser E2E not executed | QA | **High** |
| B-06 | `EnvelopeImageResolver.php` syntax defect found during QA | **Fixed** | Was critical |

**Not launch blockers:**
- Rebuilding PDF Designer
- Moving envelope/RSVP/project logic to pdf-plugin
- Theme changes (OI bridge covers simple add-to-cart gap)

---

## pdf-plugin changes

### Shipped / committed in repo

- `Online_Invitation_Builder_Adapter` and integration provider (existing)
- AJAX security hardening (existing)

### Uncommitted local workspace diff (review before release)

| File | Purpose | Unavoidable? |
|------|---------|--------------|
| `src/class-bpp-hooks.php` | Apply `bpp/is_product_customizable` filter in `is_customized_product()` | **No** for public poster; **Yes** for clean OI bridge on simple products — external OI filter would not run without this hook point |
| `src/Integration/Editor_Renderer.php` | Show message when `_bpp_product.active` is false | **No** — improves UX; prevents silent empty editor |
| `src/class-bpp-pdf-plugin.php` | 1-line change | Trivial — verify intent before commit |

### pdf-plugin changes made for public poster (this integration cycle)

**None.** OI snapshots BPP display CSS/fonts at publish and serves from project storage.

---

## Remaining manual QA

Use `docs/envelope-poster-e2e-report.md` and `docs/online-invitation-runtime-test-plan.md`.

**Minimum pre-launch checklist:**

- [ ] Configure disposable `online_invitation` product with **active** BPP, ≥2 text fields, 1 image field, valid size/format
- [ ] Desktop Chrome: customize → cart → checkout → My Account → publish → public URL
- [ ] Mobile 375px and 320px: no horizontal overflow on poster viewport
- [ ] Generic token + personal token after RSVP
- [ ] `prefers-reduced-motion`: envelope content visible without animation
- [ ] Deactivate pdf-plugin → reload public invitation → poster still styled
- [ ] Flush rewrites (`pks_oi_public_rewrite_version = 3`) on deploy
- [ ] Confirm hosting `post_max_size` / `upload_max_filesize` / cache exclusions on production

---

## Rollback plan

| Layer | Rollback |
|-------|----------|
| **OI plugin** | Deactivate `prikogstreg-online-invitations` — existing variable BPP products unaffected |
| **Published projects** | Unpublish via My Account; public URLs return 404 unavailable page |
| **Rewrite rules** | Previous version still serves if not flushed; re-flush after downgrade |
| **pdf-plugin uncommitted diff** | `git checkout --` in pdf-plugin if bridge filter causes regression on variable products (low risk — filter defaults to prior `$active` behavior) |
| **Test product 284185** | Revert meta in admin if disposable configuration applied |

**Data safety:** Project storage is private under `pks-oi-private/projects/{uuid}/`. Rollback does not delete customer projects unless explicitly purged.

---

## Sign-off matrix

| Area | Automated | Runtime browser | Release ready |
|------|-----------|-----------------|---------------|
| Product type + qty | ✅ | ✅ (qty only) | ✅ |
| Storefront BPP UI | ✅ (unit) | ❌ | ⏳ |
| Cart / checkout | ✅ | ❌ | ⏳ |
| Project import | ✅ | ❌ | ✅ |
| Envelope snapshot | ✅ | ❌ | ✅ |
| Publish + sanitizer | ✅ | ❌ | ✅ |
| Public invitation | ✅ | ❌ | ⏳ |
| pdf-plugin independence | ✅ (test) | ❌ | ⏳ |

**Final verdict:** **CONDITIONAL GO** — ship post-purchase and public stack; complete storefront browser proof on a configured disposable product before customer-facing launch.
