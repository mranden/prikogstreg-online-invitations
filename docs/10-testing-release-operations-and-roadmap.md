# Testing, release, operations, and roadmap

**Last verified:** 2026-07-14 (production public invitation landing page)

---

## Automated tests

```bash
composer test                 # 393 tests
composer test:unit            # unit suite
composer test:integration     # integration suite
composer test:e2e             # 1 (PHP chain)
npm run build                 # required after asset changes
cd ../pdf-plugin && composer test   # 14 tests (sibling plugin)
```

### High-signal test classes

| Area | Tests |
|------|-------|
| Storefront ProductFrontend | `OnlineInvitationProductFrontendTest`, `BuilderFrontendBridgeTest`, `EnvelopeFrontendTest`, `ProductReadinessTest`, `ProductFrontendAssetsTest`, `ProductBodyClassTest` |
| Cart/checkout | `CartCheckoutTest`, `CartPayloadValidatorTest` |
| Product type | `ProductTypeTest`, `WC_Product_Online_InvitationTest` |
| Import snapshot | `ProjectImportSnapshotTest` |
| Public poster / shell | `PublicPosterExperienceTest`, `PublicInvitationTest`, `PublicInvitationLoaderTest`, `PublicAssetManagerTest` |
| Publish pipeline | `ProjectLifecycleTest`, `ProjectStateServicePublishTest`, `PublishedHtmlValidatorTest` |
| Sanitizer | `PublishedHtmlSanitizerTest` |
| Accessibility markup | `AccessibilityTest` |
| Photo share | `PhotoShareServicesTest`, `PhotoTest::test_share_upload_via_photo_share_session` |
| PHP chain E2E | `InvitationFlowChainTest` |
| HPOS | `HposOrderTest` |

Fixtures: `tests/Fixtures/` (including `public-html-sanitizer-fixtures.json`).

---

## Storefront tests (shipped)

| Test | Class | Asserts |
|------|-------|---------|
| Custom add-to-cart handler | `ProductTypeTest`, `OnlineInvitationProductFrontendTest` | OI callback, not `woocommerce_simple_add_to_cart` |
| Template hook order | `OnlineInvitationProductFrontendTest` | Six `woocommerce_*add_to_cart*` actions in WC order |
| Configurator markup | `OnlineInvitationProductFrontendTest` | `.pks-oi-product-configurator`, single form, fixed qty |
| Builder bridge | `BuilderFrontendBridgeTest` | Hidden size/format + single `woocommerce_bpp_options` |
| Envelope preview | `EnvelopeFrontendTest` | Renders from `EnvelopeDesign`; invalid attachment rejected |
| Readiness gating | `ProductReadinessTest` | Missing envelope → failure; customer text has no codes; admin needs `view_online_invitation_projects` |
| Asset scoping | `ProductFrontendAssetsTest` | `product.css`/`product.js` only on OI products |
| Gallery dedupe | `ProductBodyClassTest` | `pks-oi-has-builder-canvas` body class + CSS rule |
| Cart payload unchanged | `CartPayloadValidatorTest`, `CartCheckoutTest` | Existing cases still pass |

**Static vs runtime:** PHPUnit covers hook registration, template markup, bridge guards, envelope/readiness rendering, and asset scoping. Browser QA (below) is still required for BPP JS interaction, image crop, and checkout POST shape.

### Automated evidence (2026-07-14)

| Command | Result |
|---------|--------|
| `composer test` (OI) | **393 passed**, 2 deprecations |
| `composer test` (pdf-plugin) | **14 passed** |
| `npm run build` | OK (`public.js` modular bundle, SCSS partials) |

**pdf-plugin changes:** none (empty-poster fix in OI publish merge + validation).

### Public landing page tests added

| Test | Asserts |
|------|---------|
| `PublishedHtmlValidatorTest` | Empty BPP wrapper rejected; image/text accepted |
| `ProjectStateServicePublishTest` | `is_public` in publish context; editable page merge |
| `ProjectLifecycleTest` | Adapter empty HTML falls back to files; all-empty rejected |
| `PublicInvitationLoaderTest` | Empty published snapshot → `empty_published_html` |
| `PublicAssetManagerTest` | Shop handles dequeued on invitation route only |
| `AccessibilityTest` | `data-envelope-state`, `inert`, noscript poster visible |

---

## Browser matrix (manual — required before production sign-off)

| Viewport | Status |
|----------|--------|
| 320×568 | **NOT RUN** |
| 375×667 | **NOT RUN** |
| 390×844 | **NOT RUN** |
| 768×1024 | **NOT RUN** |
| 1024×768 | **NOT RUN** |
| 1440×900 | **NOT RUN** |
| 1920×1080 | **NOT RUN** |

| Scenario | Status |
|----------|--------|
| Envelope open (mouse/keyboard) | **NOT RUN** |
| Poster matches design / fonts / images | **NOT RUN** |
| Multi-page poster nav | **NOT RUN** |
| RSVP / wishlist / photos | **NOT RUN** |
| Photo share page (code, upload, gallery) | **NOT RUN** |
| Reduced motion | **NOT RUN** |
| JavaScript disabled | **NOT RUN** |
| pdf-plugin deactivated after publish | **NOT RUN** |
| No minicart on public route | **NOT RUN** |
| Logged in as owner (empty poster diagnostic) | **NOT RUN** |

Use a disposable published invitation with real envelope, non-empty BPP HTML, RSVP, wishlist, and photos enabled.

---

## Runtime diagnostics

```bash
php tests/audit/runtime-diagnostics.php
```

Read-only JSON: WP/WC versions, HPOS, product type counts, `online_invitation` products, sample BPP products. Run from WordPress root with bootstrapped WP.

---

## Manual QA status (2026-07-14)

| Area | Automated | Browser |
|------|-----------|---------|
| Post-purchase import | ✓ | — |
| Publish + public poster | ✓ | — |
| Storefront customize → cart | Unit + template tests | **Not verified in browser** |
| Image crop mobile | — | **Not verified** |
| Checkout payment | — | **Not verified** |
| pdf-plugin disabled public | ✓ (unit) | **Not verified** |

**Staging product 284185:** BPP inactive at last check — configure before browser E2E.

### Admin QA checklist (2026-07-14)

| Check | Automated | Browser |
|-------|-----------|---------|
| Capabilities registered for administrator / shop_manager | ✓ `CapabilitiesTest` | — |
| Admin list filters, search, publication filter | ✓ `ProjectAdminListTest` | **Not verified** |
| Batch guest/photo counts in list | ✓ integration | — |
| Admin support event/guest edit + audit | ✓ `AdminSupportServiceTest` | **Not verified** |
| Preview URL nonce shape | ✓ unit | **Not verified** |
| Legacy slug redirect `pks-oi-invitations` | — | **Not verified** |
| Top-level menu + tabs + narrow viewport | — | **Not verified** |
| No token/path leakage in HTML | ✓ redaction in view model | **Not verified** |

**Release steps:** deploy plugin; visit wp-admin once (caps re-applied on boot); confirm **Online Invitations** menu for shop manager; spot-check one published project preview.

**Rollback:** deactivate plugin (caps remain on roles — harmless); revert code; no schema migration was added for admin V1.

**Remaining admin limitations:** no bulk publish/delete; no global guest search; wishlist reservation release not exposed in admin; expiry/token rotate audit incomplete for some legacy tool actions; product-title search not in list filter (product ID only).

### Minimum browser checklist

**Precondition:** disposable `online_invitation` product with active `_bpp_product` (clone settings from variable product 263195 if needed). Staging product 284185 remains **unsuitable** until BPP is activated.

**After Option B (shipped — `add-to-cart-online-invitation.php`):**

1. View source: `.pks-oi-product-configurator` present; plugin template loaded
2. `body.pks-oi-product-workspace` + `pks-oi-has-builder-canvas` when BPP active
3. Envelope preview section visible; readiness shows customer message (not error codes)
4. Hidden `attribute_pa_bpp_size` / `attribute_pa_bpp_format` in form
5. `field[]` and `page[]` present in POST on add-to-cart (network tab)
6. Quantity input fixed at 1 (not editable)
7. No duplicate WC product gallery when canvas active
8. Variable BPP product (e.g. 263195) unchanged
9. Ordinary simple product unchanged — no OI `product.css` enqueued

**Post-purchase / public (unchanged):**

9. Desktop: customize → cart → classic checkout → My Account → publish
10. Open generic + personal tokens; verify envelope open + poster
11. Mobile 375px / 320px — no horizontal overflow on poster; image crop usable
12. `prefers-reduced-motion` — content visible without animation
13. Deactivate pdf-plugin — public poster still styled; product page shows OI readiness/placeholder (no BPP form)
14. Flush permalinks after deploy (`REWRITE_VERSION = 3`)

Archived detailed E2E notes: `docs/___old/2026-07-14-before-consolidation/envelope-poster-e2e-report.md`

---

## Release decision

**CONDITIONAL GO** (2026-07-14, production public landing page shipped in code)

- **Ready:** 393 automated tests, publish merge + empty-poster guards, envelope state model, asset isolation, modular public JS/SCSS, event-details section, dedicated photo share page
- **Pending:** Browser QA matrix above on staging with real BPP content + configured product

### Remaining production risks

- Browser envelope 3D fidelity (especially Safari) unverified
- Theme-specific asset handles beyond Prikogstreg defaults may need `pks_oi/public/dequeue_handles` filter tuning
- Staging product 284185 may still lack active BPP for end-to-end storefront proof

### Rollback

- Deactivate plugin — variable BPP products unaffected
- Unpublish projects — public URLs show unavailable page
- Revert plugin version — preserve `pks-oi-private` storage backup
- If public shell dequeue causes regressions: remove `PublicAssetManager` registration in `PublicRegistrar` (single line)

---

## Deployment checklist

1. `composer install --no-dev` + `composer dump-autoload -o`
2. `npm run build` (commit `assets/build/` or build in CI)
3. Define `PKS_OI_STORAGE_PATH` outside web root
4. Verify HPOS enabled
5. Activate pdf-plugin with integration adapter
6. Flush permalinks (required for `/photos/{token}/` rewrites; schema v3 migration backfills share tokens)
7. Verify Action Scheduler running (WooCommerce)
8. Configure SMTP for delivery emails on staging first
9. Exclude `/invitation/*` and `/photos/*` from full-page cache

---

## Operations

### Common incidents

| Symptom | Check |
|---------|-------|
| Public 404 | Permalinks; `publication_status`; token revoked; photo share rewrite flush |
| Import failed | `last_error_code` on project; order-item file exists |
| No field form | `_bpp_product.active`; `bpp/is_product_customizable`; plugin template loaded (Option B) |
| Two add-to-cart buttons | `wc_bpp_cart_style` missing on native button; BPP `customize-product` CSS not applied |
| Checksum error on load | Manifest tampering; restore from backup |
| E-mails not sent | Action Scheduler queue; `DeliveryFailures` admin |

### Admin tools

- **Projects support screen** — import retry, diagnostics
- **Delivery failures** — failed queue inspection

### Rollback

- Deactivate plugin — variable BPP products unaffected
- Unpublish projects — public URLs show unavailable page
- Revert plugin version — preserve `pks-oi-private` storage backup

---

## Hosting requirements

| Setting | Recommended |
|---------|-------------|
| `post_max_size` / `upload_max_filesize` | ≥ customer image size (e.g. 8–16M+) |
| `max_input_vars` | Monitor large `page[]` payloads |
| `memory_limit` | ≥ 256M for image processing |
| Private storage | Outside `public_html` |
| PHP | 8.1+ |

Local dev reference (2026-07-14): 512M limits, `max_input_vars=1000`.

---

## Roadmap / V2 exclusions

**Explicitly not in V1:**

- Post-purchase full PDF storefront editor reopen
- Guest capacity packages / tiered pricing
- Checkout Blocks support
- Browser E2E automation (Playwright/Cypress) in repo
- Full HTML allowlist parity with BPP `Public_Html_Renderer`
- Rebuild PDF Designer in OI
- Theme edits for `online_invitation` add-to-cart (Option C rejected)

**In progress:**

- Browser QA for public invitation landing page (matrix above)
- Browser QA for storefront customize → checkout on configured product

**Potential V2:**

- Adapter per-page `render_public_html` for multi-page publish
- `ENVELOPE_PREVIEW_REF` media picker in admin
- Deeper CSP / SVG policy review
- Playwright smoke suite in CI

---

## Historical documentation

All pre-consolidation docs (prompts, audits, gap registers, manual test plans) are archived at:

`docs/___old/2026-07-14-before-consolidation/`

Use archive for forensic detail; canonical truth is `docs/01`–`docs/10` plus current code.
