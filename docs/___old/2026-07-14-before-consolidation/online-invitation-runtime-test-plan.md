# Online Invitation — Runtime Test Plan

**Audit date:** 2026-07-14  
**Environment:** Local/staging WordPress at `/Users/andreaspedersen/www/prikogstreg`  
**Production:** Do not run destructive tests against production.

---

## 1. Tests executed during this audit

| # | Test | Command / method | Result | Evidence type |
|---|------|------------------|--------|---------------|
| R-01 | OI unit/integration suite | `composer test` in `prikogstreg-online-invitations` | **PASS** 269/269 | Runtime |
| R-02 | PDF unit suite | `composer test` in `pdf-plugin` | **PASS** 14/14 | Runtime |
| R-03 | WP/WC/HPOS versions | `tests/audit/runtime-diagnostics.php` | WP 7.0.1, WC 10.9.4, HPOS on | Runtime |
| R-04 | Checkout type | diagnostic script | Classic `page-checkout.php`, no blocks | Runtime |
| R-05 | Product type counts | diagnostic SQL | 384 variable, 275 simple, 1 online_invitation | Runtime |
| R-06 | BPP product sample | diagnostic | All sampled BPP products are `variable` | Runtime |
| R-07 | online_invitation config | diagnostic | ID 284185 — no `_bpp_product` | Runtime |
| R-08 | Static code trace | source review | Simple product missing `woocommerce_bpp_options` | Static |

### 1.1 Diagnostic artifact created

| File | Purpose |
|------|---------|
| `tests/audit/runtime-diagnostics.php` | Read-only JSON report of WP/WC/products/checkout |

**Does not modify production data.**

---

## 2. Tests NOT executed (blocked or out of scope)

| # | Test | Reason |
|---|------|--------|
| X-01 | Full browser customize → checkout → public | No configured `online_invitation` + BPP product; field-form gap |
| X-02 | Image upload/crop on mobile | Requires browser |
| X-03 | Mixed cart session restore after login | Requires browser session |
| X-04 | Payment gateway completion | Requires test payment |
| X-05 | Disable PDF plugin after import — public still renders | Requires completed import |
| X-06 | Load test 15MB page payload | Requires crafted POST |
| X-07 | Safari/iOS visual fidelity | Requires devices |

---

## 3. Manual E2E test case (Phase 13)

Use a **disposable** customer, product, and order.

### 3.1 Prerequisites

- [ ] PDF Builder active
- [ ] Online Invitations active, HPOS on
- [ ] Classic checkout page (verified on this site)
- [ ] **Fix G-01:** field form available on `online_invitation` product page
- [ ] `online_invitation` product with **active** `_bpp_product` (clone settings from variable invitation product e.g. ID 263195)
- [ ] Envelope + background presets on OI product tab
- [ ] Logged-out browser + logged-in browser tabs

### 3.2 Product page (logged out)

| Step | Action | Expected | Actual | Pass |
|------|--------|----------|--------|------|
| 1 | Open invitation product | Page loads | | ☐ |
| 2 | Confirm price | Matches product admin | | ☐ |
| 3 | Confirm qty fixed at 1 | No qty >1 | | ☐ |
| 4 | Confirm canvas `#customizer-area` | Visible | | ☐ |
| 5 | Confirm field form (text/image/layer) | Visible in summary column | | ☐ |
| 6 | Edit ≥2 text fields | Canvas updates | | ☐ |
| 7 | Upload + crop image | Image appears in canvas | | ☐ |
| 8 | Toggle optional layer (if configured) | Canvas updates | | ☐ |
| 9 | Select size/format (if shown) | Hidden defaults OK for simple | | ☐ |
| 10 | Generate preview | Modal/preview loads | | ☐ |
| 11 | Add to cart without customize | Error from OI validator | | ☐ |
| 12 | Add customized design | Success notice | | ☐ |

### 3.3 Mixed cart

| Step | Action | Expected | Pass |
|------|--------|----------|------|
| 13 | Add physical/simple product | Both lines in cart | ☐ |
| 14 | Invitation qty stays 1 | Cannot increase | ☐ |
| 15 | Cart shows custom meta | Theme/BPP labels | ☐ |
| 16 | Remove/re-add invitation | State preserved if supported | ☐ |

### 3.4 Checkout

| Step | Action | Expected | Pass |
|------|--------|----------|------|
| 17 | Guest checkout with invitation | Account required notice | ☐ |
| 18 | Create account at checkout | No plaintext password email | ☐ |
| 19 | Complete classic checkout | Order created | ☐ |
| 20 | Existing customer checkout | Order tied to user | ☐ |

### 3.5 Order + import

| Step | Action | Expected | Pass |
|------|--------|----------|------|
| 21 | Set order to processing/completed | Project created once | ☐ |
| 22 | Re-run status transition | No duplicate project | ☐ |
| 23 | `_bpp_custom_data_file` exists | Under uploads | ☐ |
| 24 | Payload file contains `page[]` + `field` | Non-empty HTML | ☐ |
| 25 | `_pks_oi_project_id` on line item | Matches DB | ☐ |
| 26 | Project storage `pages/editable/` | HTML files present | ☐ |
| 27 | `state/current.json` | Canonical state | ☐ |

### 3.6 My Account

| Step | Action | Expected | Pass |
|------|--------|----------|------|
| 28 | Project appears in online-invitations | Listed | ☐ |
| 29 | Overview checklist | Renders | ☐ |
| 30 | Preview section | Shows poster (draft) | ☐ |
| 31 | Event details save | Persists | ☐ |
| 32 | Add guest + RSVP flow | Works | ☐ |

### 3.7 Public invitation

| Step | Action | Expected | Pass |
|------|--------|----------|------|
| 33 | Publish project | `publication_status=published` | ☐ |
| 34 | Open generic token URL | Envelope + poster | ☐ |
| 35 | Open personal guest URL | Named envelope | ☐ |
| 36 | Poster visual fidelity | Matches pre-purchase design | ☐ |
| 37 | RSVP submit | Recorded | ☐ |
| 38 | Wishlist reserve | Atomic reservation | ☐ |
| 39 | Guest photo upload | Pending moderation | ☐ |
| 40 | No editor JS errors on public | Network tab clean | ☐ |

### 3.8 Dependency removal

| Step | Action | Expected | Pass |
|------|--------|----------|------|
| 41 | Deactivate PDF plugin | Public invitation still renders published poster | ☐ |
| 42 | Re-activate PDF plugin | Admin order tools work | ☐ |

### 3.9 Entitlement

| Step | Action | Expected | Pass |
|------|--------|----------|------|
| 43 | Full refund invitation line | Project restricted | ☐ |
| 44 | Public URL after refund | Unavailable message | ☐ |

### 3.10 Technical hygiene

| Step | Action | Expected | Pass |
|------|--------|----------|------|
| 45 | No PHP warnings in debug.log | Clean | ☐ |
| 46 | No JS console errors | Clean | ☐ |
| 47 | `composer test` after test data | Still green | ☐ |

---

## 4. Targeted regression tests (automated — recommended)

| Area | Suggested test | Owner |
|------|----------------|-------|
| Adapter import from real payload fixture | Integration test with JSON fixture from real order | OI |
| Simple product field-form hook | WP browser test or hook unit test | OI |
| `PublicInvitationLoader` without PDF plugin active | Integration test mock | OI |
| Cart payload 5MB boundary | Validator rejects oversize | OI |
| `BPP_Order_Item_Storage` malformed file | Adapter returns `WP_Error` | PDF |

---

## 5. Hosting / browser matrix (Phase 14 — manual)

| Check | Type | Priority |
|-------|------|----------|
| `post_max_size` ≥ 32M | php.ini | High |
| `upload_max_filesize` ≥ 10M | php.ini | High |
| `max_input_vars` ≥ 3000 | php.ini | Medium |
| Nginx `client_max_body_size` | server | High |
| Cloudflare WAF on large POST | CDN | Medium |
| Mobile 320/375px product page | browser | High |
| Safari canvas/html2canvas | browser | Medium |
| `prefers-reduced-motion` on envelope | a11y | Medium |
| Page cache excluding cart/checkout | cache plugin | High |

---

## 6. Test evidence log template

Record each manual run:

```text
Date:
Tester:
Product ID:
Order ID:
Project ID:
Browser/device:
PHP post_max_size:
Result summary:
Failures:
Screenshots/logs path:
```

---

*After G-01 fix and product configuration, execute §3 and update `online-invitation-go-no-go.md` test evidence.*
