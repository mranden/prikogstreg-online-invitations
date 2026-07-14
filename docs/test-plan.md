# Test plan — Prikogstreg Online Invitations

**Status:** V1 test matrix (Prompt 24 completion pass)  
**Date:** 2026-07-14  
**Rule:** Do not claim a test passed unless it ran successfully.

---

## 0. Prompt 24 — automated run results (2026-07-14)

### Environment

| Requirement | Version / note |
|-------------|----------------|
| PHP | 8.4.22 (minimum 8.1) |
| PHPUnit | 10.5.64 |
| Composer | project `vendor/` |
| Node | `npm run build` (esbuild + sass) |
| WordPress / WooCommerce runtime | **Not required** for PHPUnit (Brain Monkey stubs) |
| Browser E2E (Playwright/Cypress) | **Not configured** — manual matrix §3 |

### Online Invitations — commands and results

```bash
cd prikogstreg-online-invitations
composer install
composer test              # 249 tests, 784 assertions — OK (2 deprecations)
composer test:unit         # 69 tests, 151 assertions — OK
composer test:integration  # 179 tests, 618 assertions — OK
composer test:e2e          # 1 test, 15 assertions — OK
npm run build              # OK
```

**New in Prompt 24:** `tests/Fixtures/`, security negatives (`IdorTest`, `NonceTest`, `SqlInjectionRepositoryTest`, `PublishedHtmlSanitizerTest`), `GenericTokenRotationTest`, `PublishEntitlementTest`, `PublicControllerTest`, `PublicInvitationLoaderTest`, `PhotoImageValidatorTest`, `ActivationTest`, `HposOrderTest`, `InvitationFlowChainTest` (PHP chain).

**Prompt 27 audit:** `docs/prompt-27-audit.md` — full-system checklist; BPP adapter implemented.

### PDF Builder — commands and results

```bash
cd pdf-plugin
composer test                        # 14 tests, 28 assertions — OK
npm run build                        # dist rebuilt — OK
```

**Scaffold added:** `phpunit.xml.dist`, `tests/bootstrap.php`, `OrderItemStorageTest`, `CartPdfHandlerSecurityTest`.

**Prompt 25:** `BPP_Ajax_Security` hardening.

**Prompt 27:** `pdf-plugin/src/Integration/*` — formal `bpp/integration/service` adapter + 6 integration tests.

**Prompt 25 audits:** `docs/security-review.md`, `docs/performance-review.md`, `docs/data-integrity-review.md`.

### Prompt 26 — release verification (2026-07-14)

```bash
composer validate --no-check-publish   # OK
composer dump-autoload -o --no-dev     # OK — 185 classes
php -l src/**/*.php                    # OK — 185 files
composer test                          # 249 tests, 784 assertions — OK (2 deprecations)
npm run build                          # OK — no source maps in assets/build/
```

**Docs added:** `readme.txt` (full V1), `CHANGELOG.md`, `docs/developer-guide.md`, `docs/operations-runbook.md`, `docs/production-review.md`.

**Production package:** `vendor/` = 12 files with `--no-dev`; exclude `node_modules/`, `tests/`, dev vendor.

### External / manual blockers (honest)

| Item | Blocker |
|------|---------|
| Full browser E2E (M1–M18) | No Playwright/Cypress harness; staging + browsers required |
| Product-page customize → cart UI | Theme + WooCommerce runtime |
| E-mail client rendering | Real SMTP / mail catcher |
| Checkout Blocks | Theme uses classic checkout |
| PDF adapter integration tests | Adapter lives in future pdf-plugin `Integration/` package |
| `invalid-uploads/` binary fixtures | Covered by `tests/Support/PhotoFixtures.php` instead |

---

## 1. Test infrastructure

### Online Invitations (`prikogstreg-online-invitations`)

| Tool | Purpose |
|------|---------|
| PHPUnit + `wp-phpunit` or `brain/monkey` | Unit tests |
| WooCommerce test suite patterns | Integration |
| Playwright or Cypress (optional) | E2E — manual matrix if not automated |

Suggested commands (Prompt 7 scaffold):

```bash
composer install
composer test              # all suites
composer test:unit
composer test:integration
composer test:e2e          # PHP chain only
npm install
npm run build
```

### PDF Builder (`pdf-plugin`)

| Tool | Purpose |
|------|---------|
| PHPUnit | Adapter, storage, validator |
| Existing Webpack | `npm run build` — verify dist unchanged for BC |

Suggested commands (Prompt 2 + Prompt 24 scaffold):

```bash
composer install
composer test
npm run build
```

---

## 2. Automated test matrix

### 2.1 Product type (Prompt 10)

| Test | Type |
|------|------|
| `online_invitation` registers and instantiates `WC_Product_Online_Invitation` | Unit |
| Product is virtual, sold individually | Unit |
| Quantity forced to 1 on add-to-cart | Integration |
| Quantity update rejected in cart | Integration |
| Mixed cart with simple + invitation products | Integration |
| Missing builder config blocks purchase | Integration |

### 2.2 Cart and checkout (Prompt 11)

| Test | Type |
|------|------|
| `field`/`page`/`pa_bpp_*` preserved on invitation line item | Integration |
| Non-invitation lines unaffected | Integration |
| Account required when cart has invitation | Integration |
| Classic checkout creates order with payload file pointer | Integration |
| HPOS order read via `wc_get_order()` | Integration |

### 2.3 Project lifecycle (Prompt 12)

| Test | Type |
|------|------|
| Project created on `processing` status | Integration |
| Idempotent on duplicate hook fire | Integration |
| `order_item_id` UNIQUE prevents duplicate | Integration |
| `_pks_oi_project_id` on order item | Integration |
| Welcome e-mail sent once | Integration |
| Import copies payload to project files | Integration |
| Failed import rolls back safely | Integration |

### 2.4 Database (Prompt 8)

| Test | Type |
|------|------|
| Activation creates all 8 tables | Integration |
| Migration idempotent | Integration |
| Repository CRUD per table | Unit |
| UTC timestamps stored | Unit |

### 2.5 File storage (Prompt 9)

| Test | Type |
|------|------|
| Atomic write + checksum | Unit |
| Path traversal rejected | Unit |
| Stale `state_version` conflict | Integration |
| Publish writes separate published files | Integration |
| Manifest roundtrip | Unit |

### 2.6 Builder adapter (Prompts 4–6, 14)

| Test | Type |
|------|------|
| `bpp/integration/service` discovery | Unit |
| `load_state` from order item fixture | Integration |
| `save_state` returns canonical; OI persists | Integration |
| `validate_state` rejects missing required fields | Unit |
| `validate_state` rejects unknown field UUID | Unit |
| `migrate_state` legacy v0 → v1 | Unit |
| Canonical state checksum deterministic | Unit |
| `render_public_html` strips script fixture | Unit |
| `render_public_html` preserves legitimate builder markup | Unit |
| `Public_Html_Renderer` strips onerror/javascript URL/iframe | Unit |
| `render_public_html` does not enqueue editor bundle | Static |
| `enqueue_editor_assets` outside `is_product()` | Integration |
| `render_editor` scopes instance ID; no duplicate DOM IDs in project mode | Unit |
| `BPP_EDITOR_CONTEXT` excludes user/order/project IDs | Static |
| `project_edit` dispatches `bpp:save-requested` not `save_cart_pdf` | Manual |
| Legacy product customizer regression | E2E/manual |

### 2.7 Authorization

| Test | Type |
|------|------|
| User A cannot load User B project | Integration |
| Admin support requires capability | Integration |
| Invalid token returns generic 404 | Integration |
| Revoked token after rotation | Integration |

### 2.8 Tokens and public routes (Prompt 15)

| Test | Type |
|------|------|
| Token generation entropy | Unit |
| Hash storage; raw not in DB | Unit |
| Personal link resolves guest | Integration |
| Generic link does not impersonate named guest | Integration |
| Generic RSVP creates `is_generic_response` guest | Integration |
| Generic RSVP does not overwrite named guest | Integration |

### 2.9 Guests and address book (Prompt 16)

| Test | Type |
|------|------|
| Unlimited guest insert | Integration |
| Address book scoped by `user_id` | Integration |
| CSV export neutralizes `=cmd` | Unit |
| CSV import validation + row limit | Integration |
| Address-book delete does not corrupt guest snapshot | Integration |

### 2.10 RSVP (Prompt 17)

| Test | Type |
|------|------|
| Response until deadline | Integration |
| Reject after deadline | Integration |
| Response change logged in events | Integration |
| Open tracking not on owner preview | Integration |

### 2.11 E-mail and scheduler (Prompt 18)

| Test | Type |
|------|------|
| Delivery idempotency_key prevents duplicate | Integration |
| Reminder skips responded guests | Integration |
| Reminder reschedules on deadline change | Integration |
| Action Scheduler callback safe to run twice | Integration |

### 2.12 Wishlist (Prompt 19)

| Test | Type |
|------|------|
| Atomic reservation — no over-booking | Integration |
| Concurrent reservation race | Integration |
| Release restores quantity | Integration |
| Reserver identity hidden by default | Unit |

### 2.13 Photos (Prompt 20)

| Test | Type |
|------|------|
| Reject SVG/exe MIME | Integration |
| Oversize rejected | Integration |
| Token + intent required | Integration |
| Moderation state transitions | Integration |
| No public gallery without explicit future setting | Unit |

### 2.14 Refund and expiry (Prompt 21)

| Test | Type |
|------|------|
| Full line refund → restricted | Integration |
| Public route unavailable when restricted | Integration |
| Expiry 90 days after event | Unit |
| No event date blocks publish | Integration |
| Admin override expiry | Integration |

### 2.15 Privacy (Prompt 22)

| Test | Type |
|------|------|
| Exporter includes address book | Integration |
| Eraser removes project files | Integration |
| Eraser idempotent | Integration |

### 2.16 Security negatives (Prompt 24)

| Test | Type | File |
|------|------|------|
| IDOR project/guest/order/photo | Integration | `tests/Integration/Security/IdorTest.php` |
| My Account nonce failures | Integration | `tests/Integration/Security/NonceTest.php` |
| Generic token rotation/revoke | Integration | `tests/Integration/Security/GenericTokenRotationTest.php` |
| XSS fixture in publish pipeline | Unit | `tests/Unit/Security/PublishedHtmlSanitizerTest.php` |
| SQL injection fuzz on repository inputs | Unit | `tests/Unit/Security/SqlInjectionRepositoryTest.php` |
| `save_cart_pdf` without nonce (known gap) | Unit (pdf-plugin) | `pdf-plugin/tests/Unit/CartPdfHandlerSecurityTest.php` |

### 2.17 PDF Builder regression (Prompt 2 + 24)

| Test | Type | File |
|------|------|------|
| `BPP_Order_Item_Storage` meta keys + empty payload | Unit | `pdf-plugin/tests/Unit/OrderItemStorageTest.php` |
| `save_cart_pdf` nonce gap documented | Unit | `pdf-plugin/tests/Unit/CartPdfHandlerSecurityTest.php` |
| Cart item data shape unchanged | Integration | Manual / staging |
| `is_customized_product` behavior | Unit | Manual until scaffolded |
| HPOS cron order query (smoke) | Integration | `tests/Integration/WooCommerce/HposOrderTest.php` |

### 2.18 E2E chain (Prompt 24)

| Test | Type | File |
|------|------|------|
| Checkout → project → event → publish → RSVP → wishlist → photo → refund | Integration (PHP) | `tests/Integration/E2E/InvitationFlowChainTest.php` |

---

## 3. Manual / E2E matrix

**Environment:** Staging with `prikogstreg` theme, PDF Builder, WooCommerce classic checkout (`page-checkout.php`).

| # | Scenario | Browsers |
|---|----------|----------|
| M1 | Customize invitation on product page → add to cart | Chrome, Safari |
| M2 | Mixed cart checkout → account created/linked | Chrome |
| M3 | Project appears in My Account | Chrome |
| M4 | Edit design → save → reload | Chrome, Firefox |
| M5 | Preview + demo-to-self e-mail | Chrome |
| M6 | Publish → generic link share | Chrome, iOS Safari |
| M7 | Personal link → envelope animation | Chrome, Android Chrome |
| M8 | Reduced motion — skip animation, content readable | Safari (prefers-reduced-motion) |
| M9 | JS disabled — public content still readable | Firefox |
| M10 | Guest RSVP + confirmation e-mail | Chrome |
| M11 | Reminder 5 days before deadline | Staging (time override) |
| M12 | Wishlist reserve/release | Chrome |
| M13 | Photo upload → organiser approve | Chrome mobile |
| M14 | Full refund → publish blocked | Admin + Chrome |
| M15 | Expiration after event + 90 days | Staging (date override) |
| M16 | Keyboard-only My Account navigation | Chrome |
| M17 | 200% zoom readability | Chrome |
| M18 | Slow 3G — editor load acceptable | Chrome DevTools |

### 3.1 Prompt 5 — context-aware editor (pdf-plugin; manual until implemented)

| # | Scenario | Pass criteria |
|---|----------|---------------|
| P5-M1 | Product-page customizer unchanged | Preview → add to cart works |
| P5-M2 | Adapter shell outside `is_product()` | Editor renders; assets load |
| P5-M3 | `project_edit` save | `bpp:save-requested` fires; no `save_cart_pdf` |
| P5-M4 | No cart button click in project mode | `.single_add_to_cart_button` never triggered |
| P5-M5 | Two editor instances | Isolated `data-bpp-instance-id` roots |
| P5-M6 | `bpp:editor-ready` | One event per instance in console |
| P5-M7 | Folded product regression | turn.js still works on product page |

See `docs/prompt-5-editor-context-spec.md` §9 for full checklist.

### 3.2 Prompt 6 — state validation and public HTML (pdf-plugin; manual until implemented)

| # | Scenario | Pass criteria |
|---|----------|---------------|
| P6-M1 | Legacy order-item import | `migrate_state` yields `schema_version: "1"` |
| P6-M2 | Publish pipeline dry-run | `render_public_html` output has no `<script` |
| P6-M3 | Legitimate invitation HTML | Text, fonts, images visible after sanitize |
| P6-M4 | Mobile 320px | Scaled wrapper readable without editor JS |
| P6-M5 | Malicious draft injection | `validate_state` rejects or sanitizer strips |
| P6-M6 | Preview mode | Owner preview renders without add-to-cart |

Fixtures: `docs/fixtures/public-html-sanitizer-fixtures.json`. See `docs/prompt-6-state-and-public-html-spec.md` §6–§7.

---

## 4. Build verification (every release)

```bash
# PDF Builder
cd pdf-plugin && npm run build && composer validate --no-check-publish

# Online Invitations
cd prikogstreg-online-invitations && npm run build && composer validate --no-check-publish
```

Inspect committed `dist/` and `assets/build/` artifacts match sources.

---

## 5. Pre-release checklist

- [ ] All automated tests green (report actual command output)
- [ ] Manual M1–M18 spot-checked on staging
- [ ] HPOS enabled smoke test on order with invitation
- [ ] Private storage path verified outside web root (or fallback hardened)
- [ ] No raw tokens in debug logs
- [ ] Checkout Block limitation documented if production still classic only
- [ ] `prikogstreg` theme `content_single_product` + `bpp_wc_attribute_html` intact

---

## 6. Known manual-only items

| Item | Why manual |
|------|------------|
| Public HTML mobile fidelity | CSS scale + font rendering |
| E-mail client rendering | WC e-mail templates |
| Production hosting storage path | Infrastructure |
| Checkout Block compatibility | Not evidenced in theme |
| html2canvas quality on various GPUs | Browser hardware |

---

## 7. Test data fixtures

Location: `tests/Fixtures/`

| Fixture | Contents |
|---------|----------|
| `builder-order-payload.json` | Sample field/page from audit |
| `malicious-page.html` | XSS vectors for sanitizer |
| `csv-injection-rows.csv` | Formula injection cases |
| `invalid-uploads/` | SVG, oversize PNG — use `tests/Support/PhotoFixtures.php` |

---

## 8. Regression ownership

| Area | Owner prompt |
|------|--------------|
| PDF Builder product flow | 2, 3, 25 |
| Adapter contract | 4, 5, 6 |
| Full E2E | 24, 26 |
