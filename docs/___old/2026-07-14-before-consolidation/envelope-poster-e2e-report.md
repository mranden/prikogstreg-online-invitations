# Envelope + Poster — End-to-End QA Report

**Date:** 2026-07-14  
**Environment:** Local staging `https://prikogstreg.test`  
**Auditor role:** Final integration engineer / QA reviewer  
**Scope:** Prove the 25-step V1 flow from admin configuration through public invitation  
**Production:** No destructive tests on production.

---

## Evidence types used

| Type | Meaning |
|------|---------|
| **Source** | Static code inspection |
| **Runtime (CLI)** | `composer test`, `wp`, `runtime-diagnostics.php`, `curl` |
| **Runtime (browser)** | Not executed in this session — marked **UNVERIFIED** |

---

## Test product configuration

| Field | Value | Status |
|-------|-------|--------|
| Product ID | **284185** | Exists, published |
| Slug / URL | `/vare/online-invitation/` | Loads HTTP 200 |
| Type | `online_invitation` | Confirmed (runtime) |
| Price | 50 DKK | Confirmed (runtime HTML) |
| Envelope preset | `classic` | Confirmed (`_pks_oi_envelope_preset`) |
| Background preset | `neutral` | Confirmed (`_pks_oi_background_preset`) |
| `_bpp_product` present | Yes | Confirmed (runtime) |
| `_bpp_product.active` | **false** | **BLOCKER** |
| BPP default size | `a5` | **Unavailable** in template (`available: false`) |
| Permitted size | `a6` only | Mismatch with OI default resolver (`a5`) |
| Editable text fields | 1 (`Navn`) | Below test spec (needs ≥2) |
| Editable image field | **None** | **BLOCKER** for image/crop E2E |
| Layer field | 1 (optional) | Present |
| Envelope design image | Not configured on product | Uses preset CSS only |

**Recommendation before browser E2E:** Clone active BPP settings from variable invitation product **263195** (Bryllupsinvitationer | Simple Green), set `active: true`, enable `a5`, add second text field + image field, assign envelope image if required.

---

## 25-step flow matrix

| # | Step | Result | Evidence |
|---|------|--------|----------|
| 1 | Admin configures `online_invitation` product | **PARTIAL** | Product 284185 exists; BPP inactive |
| 2 | Admin selects/configures envelope | **PASS (source)** | `ProductDataPanel`, `ProductMeta`, presets on 284185 |
| 3 | Admin activates/assigns BPP design | **FAIL (runtime)** | `_bpp_product.active = false` on 284185 |
| 4 | Customer opens product | **PASS (runtime)** | `curl` → 200, title "Online invitation" |
| 5 | Customer sees envelope/product presentation | **UNVERIFIED** | No envelope shell on product page (expected — envelope is public-only) |
| 6 | Customer sees BPP canvas | **FAIL (runtime)** | Left column empty; no `#customizer-area` in HTML |
| 7 | Customer sees BPP text/image/layer form | **FAIL (runtime)** | No `woocommerce_bpp_options` output; bridge guard blocks inactive BPP |
| 8 | Customer changes text | **UNVERIFIED** | Blocked by steps 6–7 |
| 9 | Customer uploads/crops image | **UNVERIFIED** | No image field configured |
| 10 | Customer previews inner invitation | **UNVERIFIED** | Blocked by steps 6–7 |
| 11 | Customer adds to cart | **UNVERIFIED** | Add-to-cart button present; validator would reject uncustomized cart |
| 12 | Quantity remains one | **PASS (runtime)** | `quantity` input `type=hidden`, `max=1`, `value=1` |
| 13 | Mixed cart supported | **PASS (source+test)** | `CartCheckoutTest::test_mixed_cart_only_annotates_invitation_line` |
| 14 | Classic checkout completes | **PASS (source+test)** | HPOS on, classic checkout template; chain test covers order path |
| 15 | BPP saves order-item payload | **PASS (source)** | `BPP_Order_Item_Storage`; variable products proven in production |
| 16 | OI creates one project | **PASS (test)** | `ProjectImportSnapshotTest`, idempotent import guards |
| 17 | Envelope configuration snapshotted | **PASS (test)** | `envelope/manifest.json`, image copy, manifest-first public VM |
| 18 | BPP HTML/state imported | **PASS (test)** | `ProjectImportSnapshotTest` multi-page fixture |
| 19 | My Account displays project | **PASS (source+test)** | `MyAccountRegistrar`, entitlement tests |
| 20 | Customer publishes project | **PASS (test)** | `ProjectPublishService`, `PublishEntitlementTest`, poster asset snapshot |
| 21 | Public invitation displays envelope | **PASS (source+test)** | `templates/public/envelope.php`, `PublicPosterExperienceTest` |
| 22 | Envelope opens | **PASS (source)** | `public.js` open button, `aria-expanded`, reduced-motion bypass |
| 23 | Customer-designed HTML inside | **PASS (test)** | Published pages only; sanitizer; poster viewport |
| 24 | RSVP/wishlist/photos functional | **PASS (test)** | `InvitationFlowChainTest`, RSVP/wishlist/photo integration tests |
| 25 | Public renders after pdf-plugin disabled | **PASS (test)** | `PublicPosterExperienceTest::test_poster_assets_snapshot_without_pdf_plugin` |

**Summary:** Steps **1–11** blocked on staging by incomplete BPP product configuration. Steps **12–25** proven by automated tests and source inspection.

---

## Automated tests executed

| Suite | Command | Result |
|-------|---------|--------|
| OI full | `composer test` | **328/328 PASS** |
| OI E2E PHP chain | `composer test:e2e` | **1/1 PASS** (15 assertions) |
| OI targeted integration | `--filter StorefrontBuilderBridge\|PublicPosterExperience\|ProjectImportSnapshot\|InvitationFlowChain\|PublishedHtmlSanitizer\|ProductTypeTest` | **60/60 PASS** |
| PDF plugin | `composer test` in `pdf-plugin` | **14/14 PASS** |
| PHP syntax | `php -l` on `src/`, `tests/` | **PASS** (after fixing `EnvelopeImageResolver.php` parse error) |
| Frontend build | `npm run build` | **PASS** |
| WordPress coding standards | Not configured in repo | **SKIPPED** |

### Regression coverage map

| Requirement | Test file |
|-------------|-----------|
| Field form on `online_invitation` | `StorefrontBuilderBridgeTest` |
| No duplicate form | `StorefrontBuilderBridgeTest::test_renders_hidden_size_and_format_defaults_and_field_form_once` |
| Hidden size/format defaults | `StorefrontBuilderBridgeTest` |
| Quantity one | `ProductTypeTest`, `WC_Product_Online_InvitationTest` |
| Cart state / markers | `CartCheckoutTest`, `CartPayloadValidatorTest` |
| Order payload persistence | `CartCheckoutTest::test_order_item_payload_persists_reference_meta` |
| Envelope snapshot | `ProjectImportSnapshotTest` |
| Idempotent project creation | `ProjectImportSnapshotTest::test_repeated_import_is_idempotent` |
| Publish sanitizer | `PublishedHtmlSanitizerTest`, `PublicPosterExperienceTest` |
| Public rendering without pdf-plugin | `PublicPosterExperienceTest::test_poster_assets_snapshot_without_pdf_plugin` |
| Product change does not alter project | `ProjectImportSnapshotTest::test_envelope_snapshot_is_independent_of_later_product_changes`, `PublicPosterExperienceTest::test_product_meta_changes_do_not_override_envelope_snapshot` |

---

## Runtime diagnostics

**Command:** `php tests/audit/runtime-diagnostics.php`

| Check | Value |
|-------|-------|
| WordPress | 7.0.1 |
| WooCommerce | 10.9.4 |
| PHP | 8.4.22 |
| HPOS | Enabled |
| PDF plugin | Active |
| OI plugin | 0.1.0 |
| Checkout | Classic `page-checkout.php`, shortcode present |
| `online_invitation` products | 1 (ID 284185) |
| Active BPP variable products | 20 sampled, all active |

---

## Hosting / PHP limits (local CLI)

| Setting | Value |
|---------|-------|
| `post_max_size` | 512M |
| `upload_max_filesize` | 512M |
| `max_input_vars` | 1000 |
| `memory_limit` | 512M |

**Notes:**
- Web-server body limit, CDN/WAF, page-cache exclusions: **not measured** in this session.
- Realistic customer image upload: **UNVERIFIED** — local limits appear sufficient; production hosting must be confirmed separately.
- `max_input_vars=1000` may be tight for very large `page[]` payloads — monitor during browser E2E.

---

## Browser E2E (not executed)

| Device | Status |
|--------|--------|
| Desktop Chrome | **NOT RUN** |
| Mobile 375px | **NOT RUN** |
| Mobile 320px | **NOT RUN** |
| Safari | **NOT RUN** |

### Partial runtime HTML probe (product page)

**URL:** `https://prikogstreg.test/vare/online-invitation/`  
**HTTP:** 200  
**Observed:**
- Price and add-to-cart render correctly
- Quantity fixed at 1 (hidden input)
- **Left product column empty** — no BPP canvas
- **No** `attribute_pa_bpp_size`, **no** field form markup
- BPP cart JS enqueued (global), but product customizer scripts absent

**Screenshots:** Not captured in this session.

---

## Defect found and fixed during QA

| ID | Severity | Issue | Resolution |
|----|----------|-------|------------|
| QA-01 | **Critical** | `EnvelopeImageResolver.php` parse error — `resolve_url()` missing return/close brace | Fixed during this review; tests re-run 328/328 |

---

## pdf-plugin changes (workspace state)

**Committed in pdf-plugin repo:** Integration adapter + AJAX security (existing).  
**Uncommitted local diff (3 files):**

| File | Change | Acceptable under minimal policy? |
|------|--------|----------------------------------|
| `class-bpp-hooks.php` | `is_customized_product()` now applies `bpp/is_product_customizable` filter | **Yes** — enables OI bridge without duplicating logic |
| `Integration/Editor_Renderer.php` | Inactive `_bpp_product.active` message | **Yes** — builder-owned guard, no OI logic |
| `class-bpp-pdf-plugin.php` | Minor (1 line removed) | Review before commit |

**No pdf-plugin changes were required to complete public poster work** — OI snapshots display CSS at publish.

These uncommitted pdf-plugin changes are **recommended** but **not yet proven in browser** and **not shipped**.

---

## Recorded IDs (staging)

| Entity | ID |
|--------|-----|
| Test product | 284185 |
| Suggested BPP clone source | 263195 |
| Order ID | *Not created — checkout E2E not run* |
| Order item ID | *Not created* |
| Project ID | *Not created in browser* |

---

## Next actions for complete E2E proof

1. Activate BPP on product 284185 (or create disposable clone).
2. Enable `a5` (or align OI `BppAttributeDefaults` with `a6` for this product).
3. Add second text field + image field in BPP customizer.
4. Run manual checklist in `docs/online-invitation-runtime-test-plan.md` §3.
5. Complete checkout with test payment gateway.
6. Publish project → open generic and personal tokens.
7. Deactivate pdf-plugin → confirm public invitation still renders.
8. Capture screenshots at 320px, 375px, desktop; record order/project IDs.
