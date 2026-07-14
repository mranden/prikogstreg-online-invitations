# Security review — Prompt 25 audit (implementation verified)

**Status:** V1 release audit  
**Date:** 2026-07-14  
**Scope:** Online Invitations + PDF Builder

Legend: **Verified** | **Manual test** | **Release blocker** | **Recommendation**

---

## 1. Online Invitations — Verified

| Control | Status | Location |
|---------|--------|----------|
| Prepared SQL / allowlisted columns | Verified | `src/Database/Repositories/*` |
| HPOS `wc_get_order()` only | Verified | `ProjectService`, refund listeners |
| No `unserialize` on untrusted data | Verified | Grep `src/` |
| No raw tokens in logs | Verified | No `error_log` in `src/` |
| No `$_REQUEST` mass assignment | Verified | Per-service allowlists |
| Path traversal blocked | Verified | `StoragePath` |
| Photo MIME/size/dimension validation | Verified | `PhotoImageValidator`, `PhotoTest` |
| Upload intent HMAC | Verified | `PhotoUploadIntentService` |
| Token hashing + rotation | Verified | `InvitationToken`, `GenericTokenRotationTest` |
| Published HTML sanitizer | Verified | `PublishedHtmlSanitizer` at publish + load |
| My Account nonce + authorization | Verified | `ProjectController::verify_nonce()`, `Authorization` |
| REST project routes protected | Verified | `ProjectRestController::can_edit` |
| Public REST token routes | Verified | `PublicEntitlement` in RSVP/wishlist/photo callbacks |
| CSV injection neutralized | Verified | `GuestCsv`, fixture test |
| Refund/expiration on public actions | Verified | `PublicEntitlement`, `RsvpService::assert_submission_allowed` |
| Production build assets only | Verified | `assets/build/` enqueues |
| IDOR / nonce negatives | Verified | `IdorTest`, `NonceTest`, `SqlInjectionRepositoryTest` |

---

## 2. PDF Builder — Verified (Prompt 25 hardening)

**Central class:** `pdf-plugin/src/class-bpp-ajax-security.php` (`BPP_Ajax_Security`)

| Endpoint | Auth model | Status |
|----------|------------|--------|
| `save_cart_pdf` | `bpp_product_ajax` nonce + rate limit + canvas validation | Verified |
| `bpp_get_cart_item` | `bpp_cart_ajax` nonce + rate limit + WC cart key | Verified |
| `create_pdf_html` | Admin nonce + capability; **nopriv removed** | Verified |
| `get_field_data` | Admin nonce + capability; **nopriv removed** | Verified |
| `bpp_get_image` | Admin nonce + capability | Verified |
| Order item customizer AJAX | Admin nonce + `edit_shop_orders`/`manage_woocommerce` + order/item match | Verified |

Nonces localized: `BPP_PUBLIC_OBJ.bpp_nonce`, `BPP_CART_PDF_OBJ.bpp_nonce`, `ajax.bpp_nonce`.

Regression: `pdf-plugin/tests/Unit/CartPdfHandlerSecurityTest.php`, `BPP_Ajax_SecurityTest.php`.

---

## 3. Manual test required

| Item | Why |
|------|-----|
| Published HTML `<iframe>` / SVG in builder output | OI sanitizer blocks script/on*/javascript: only; PDF Builder renderer is separate layer |
| Owner preview XSS (`project-preview.php`) | Trusted owner context; pen-test malicious builder state |
| Guest token transients (`GuestSendTokenStore`) | Raw token in transient for email link delivery — verify backup/export policy |
| E-mail header injection | WC mailer + `AbstractOiEmail` — test with crafted organiser contact fields |
| Checkout Blocks | Theme uses classic checkout; not evidenced for blocks |
| Production storage outside web root | Infrastructure verification |

---

## 4. Release blockers

| Plugin | Item | Status |
|--------|------|--------|
| Online Invitations | — | **None** |
| PDF Builder | Unauthenticated AJAX | **Fixed** in Prompt 25 (`BPP_Ajax_Security`) |

---

## 5. Non-blocking recommendations

| Item | Plugin | Notes |
|------|--------|-------|
| Plain SHA-256 token hash | OI | Adequate with 256-bit tokens; pepper optional |
| `PublishedHtmlSanitizer` depth | OI | Second layer only; full allowlist in future PDF adapter |
| pdf-plugin PNG upload validation | pdf | `move_uploaded_file` in generator — add MIME check when typst path re-enabled |
| pdf-plugin `shell_exec` in dead `pdf_typst()` | pdf | Not called; remove if re-enabling |
| Rate limit by IP only | pdf | Consider logged-in user bucket for admin |

---

## 6. Static search summary (Prompt 25)

| Pattern | OI `src/` | pdf-plugin `src/` |
|---------|-----------|-------------------|
| SQL concat with user input | None | Cron `IN (...)` with `intval()` only |
| `unserialize` untrusted | None | None |
| Raw token logs | None | Cron logs order IDs only |
| `assets/src` enqueued | None | `cart-pdf.js` (small, intentional) |
| `wp_ajax_nopriv` without protection | None | **Removed** from admin-only actions |
| REST missing `permission_callback` | Public routes intentional | N/A |

---

## 7. Test commands (2026-07-14)

```bash
# Online Invitations
cd prikogstreg-online-invitations
composer test    # 249 tests — OK

# PDF Builder
cd pdf-plugin
composer test    # 7 tests — OK
npm run build    # dist rebuilt — OK
```

See also `docs/test-plan.md` §0 and `docs/performance-review.md`, `docs/data-integrity-review.md`.
