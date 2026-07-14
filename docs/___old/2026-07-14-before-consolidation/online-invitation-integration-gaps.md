# Online Invitation — Integration Gaps

**Audit date:** 2026-07-14  
**Purpose:** Consolidated gap register across PDF module, Online Invitations, and theme.

---

## 1. Critical gaps (V1 launch blockers)

| ID | Gap | Owner | Evidence | Fix complexity |
|----|-----|-------|----------|----------------|
| G-01 | **`online_invitation` simple product page lacks PDF field form** — `woocommerce_bpp_options` only fires inside `bpp_wc_attribute_html` (variable template) | OI or theme or PDF | theme `simple.php` vs `variable.php:63`; `customized-product-attribute-html.php:152` | **Small** |
| G-02 | **No configured `online_invitation` product with active `_bpp_product`** in staging | Operations | runtime diagnostic: product 284185 `bpp_active: false` | **Small** (admin config) |
| G-03 | **End-to-end browser flow unverified** — customize → cart → order → project → publish → public | QA | no E2E run in this audit | **Medium** (testing) |

---

## 2. PDF module gaps

| ID | Gap | Severity | Classification | Evidence |
|----|-----|----------|----------------|----------|
| P-01 | No formal HPOS compatibility declaration | Low | Optional | no `FeaturesUtil` in pdf-plugin |
| P-02 | `is_product()` coupling for page-scoped UI | Medium | Working with limitation | `is_customized_product_page()` `class-bpp-hooks.php:891-902` |
| P-03 | `Editor_Renderer` renders canvas only — no `render_product_customizer_form()` | Medium | Not required for V1 if post-purchase edit excluded | `Editor_Renderer.php:16-42` |
| P-04 | `Editor_Asset_Loader` missing `BPP_CUSTOMISER_DATA` / `BPP_PRODUCT` localization | Medium | Not required for V1 storefront | `Editor_Asset_Loader.php` vs `class-bpp-hooks.php:112-157` |
| P-05 | `verify_customizable_product()` does not check `_bpp_product.active` | Medium | Security hardening | `class-bpp-ajax-security.php:135-160` |
| P-06 | Order payload cron deletion after ~3 months | Medium | Mitigated by OI import — **must import before cleanup** | `class-bpp-cron.php` |
| P-07 | `page[]` HTML trusted from browser — minimal server sanitization at capture | High | Existing PDF risk; OI must sanitize on publish | `class-bpp-woo-cart-functions.php:29-38` |
| P-08 | BPP add-to-cart JS min-qty selector mismatch | Low | Working with limitation | template vs `customizer-public.js:271` |
| P-09 | `generate_pdf()` adapter method returns not-ready | Low | Not required for V1 | `Online_Invitation_Builder_Adapter.php:178-182` |
| P-10 | Integration contract JSON stale (nopriv admin AJAX) | Low | Documentation | contract vs `class-bpp-hooks.php:15-19` |
| P-11 | OI admin link may use wrong query param (`product_id` vs `prdid`) | Low | Runtime verify | `ProductDataPanel.php` vs `BPP_Controller` |

---

## 3. Online Invitations gaps

| ID | Gap | Severity | Classification | Evidence |
|----|-----|----------|----------------|----------|
| O-01 | **Guest-capacity packages not implemented** (audit flow mentions optional packages; code is unlimited only) | Medium | Not implemented for packages; **not a blocker** if V1 stays unlimited | `GuestService.php`, `ProductMeta.php` |
| O-02 | Checkout Blocks unsupported (hard guard) | Medium | Working with limitation | `CheckoutBlockGuard.php` |
| O-03 | HPOS required — site must have HPOS on | Low | Working now on this site | `Requirements.php:69-75` |
| O-04 | Adapter integration tests use stubs only — no tests against real `Online_Invitation_Builder_Adapter` | Medium | Runtime test required | `FakeBuilderAdapter.php`, readme |
| O-05 | `wrap_public_html` / `enqueue_public_assets` called via `method_exists` but **not** on `Builder_Adapter_Interface` | Low | Optional enhancement | `PublicInvitationLoader.php:70`, interface |
| O-06 | `PublishedHtmlSanitizer` is second-layer only — not full allowlist | Medium | Working with limitation | `PublishedHtmlSanitizer.php:20-26` |
| O-07 | Public poster depends on published snapshot existing — unpublished projects show 404 | Expected | Working now | public route tests |
| O-08 | `readme.txt` mentions guest cap on product — doc drift | Low | Documentation | readme vs code |
| O-09 | Post-purchase design editor wired but **incomplete** without field form | Low | **Not required for stated V1** | `project-design.php`, `Editor_Renderer` |

---

## 4. Theme gaps

| ID | Gap | Severity | Classification | Evidence |
|----|-----|----------|----------------|----------|
| T-01 | `simple.php` missing `woocommerce_bpp_options` / `bpp_wc_attribute_html` | **High** | V1 blocker for `online_invitation` | theme `simple.php` |
| T-02 | `ProductPagePlaceholder` hooks `woocommerce_before_single_product_summary` but theme **skips** that hook when BPP active | Low | Dead hook for builder-optional mode | `ProductPagePlaceholder.php:15`, `content-single-product.php:60-64` |
| T-03 | `get_product_min_order_quantity()` not aware of `online_invitation` qty=1 | Low | OI `QuantityGuard` compensates server-side | theme `core/woocommerce.php:790` |
| T-04 | Legacy `_not_in_use_variable-bpp-pdf-plugin.php` still in repo | Low | Cleanup only | filename |

---

## 5. Security gaps (cross-cutting)

| ID | Gap | Blocks V1? | Owner | Evidence |
|----|-----|------------|-------|----------|
| S-01 | Stored XSS via `page[]` HTML | **Yes** if publish sanitizer bypassed | OI | `Public_Html_Renderer`, `PublishedHtmlSanitizer` |
| S-02 | Guest-allowed cart PDF AJAX (`save_cart_pdf`, `bpp_get_cart_item`) | No if rate-limited | PDF | `class-bpp-cart-pdf-handler.php:48-50` |
| S-03 | Large POST body / session size from base64 images | **Probable** on shared hosting | Both | cart handler |
| S-04 | Order-item payload file path not validated on read (relative path trust) | Medium | OI must validate order ownership before import | `BPP_Order_Item_Storage::get_payload()` |
| S-05 | IDOR on `bpp_get_image` attachment lookup | Low | PDF | `class-bpp-hooks.php:576-593` |

---

## 6. Data / storage gaps

| ID | Gap | Classification | Evidence |
|----|-----|----------------|----------|
| D-01 | Base64 customer images inside JSON/HTML — large project files | Working with limitation | cart + storage import |
| D-02 | Absolute URLs in saved HTML may break after migration | Runtime test required | HTML capture model |
| D-03 | Font URLs reference live `bpp_font` attachments | Working with limitation — public needs font CSS | `BPP_fonts_css()` |
| D-04 | No automatic extraction of base64 images to managed files on import | Optional improvement | `ProjectStorage::import_from_builder_state()` |

---

## 7. Gaps by responsibility (intended split)

| Intended owner | Should fix for V1 | Can defer |
|----------------|-------------------|-----------|
| **PDF module** | Optional: simple-product field-form hook; JS selector fix; AJAX active-template check | HPOS declaration; `generate_pdf` adapter |
| **Online Invitations** | Field-form hook for `online_invitation`; configure product; E2E QA; publish sanitization | Guest-capacity packages; Blocks checkout bridge |
| **Theme** | Optional: `simple.php` BPP options insertion | Presentation polish |

---

## 8. What is NOT a gap (correctly implemented)

- Adapter discovery `bpp/integration/service` — **implemented**
- `bpp/is_product_customizable` bridge for `online_invitation` — **implemented**
- Idempotent project creation on qualifying order statuses — **implemented + tested**
- Project-owned storage import from order payload — **implemented + tested**
- Public invitation reads published snapshot only — **implemented**
- Classic checkout path on this site — **verified runtime**
- Mixed cart annotation and validation — **implemented + tested**
- Quantity-one enforcement for `online_invitation` — **implemented + tested**

---

*Remediation sequence: `online-invitation-go-no-go.md` § Implementation sequence.*
