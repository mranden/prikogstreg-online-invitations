# Testing, release, operations, and roadmap

**Last verified:** 2026-07-14

---

## Automated tests

```bash
composer test                 # 328 tests
composer test:unit            # 111
composer test:integration     # 216
composer test:e2e             # 1 (PHP chain)
npm run build                 # required after asset changes
```

### High-signal test classes

| Area | Tests |
|------|-------|
| Storefront bridge | `StorefrontBuilderBridgeTest` |
| Cart/checkout | `CartCheckoutTest`, `CartPayloadValidatorTest` |
| Product type | `ProductTypeTest`, `WC_Product_Online_InvitationTest` |
| Import snapshot | `ProjectImportSnapshotTest` |
| Public poster | `PublicPosterExperienceTest`, `PublicInvitationTest` |
| Sanitizer | `PublishedHtmlSanitizerTest` |
| PHP chain E2E | `InvitationFlowChainTest` |
| HPOS | `HposOrderTest` |

Fixtures: `tests/Fixtures/` (including `public-html-sanitizer-fixtures.json`).

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
| Storefront customize → cart | Partial (unit) | **Not verified** |
| Image crop mobile | — | **Not verified** |
| Checkout payment | — | **Not verified** |
| pdf-plugin disabled public | ✓ (unit) | **Not verified** |

**Staging product 284185:** BPP inactive at last check — configure before browser E2E.

### Minimum browser checklist

1. Activate BPP on disposable `online_invitation` product (clone from variable product 263195 if needed)
2. Desktop: customize → cart → classic checkout → My Account → publish
3. Open generic + personal tokens; verify envelope open + poster
4. Mobile 375px / 320px — no horizontal overflow on poster
5. `prefers-reduced-motion` — content visible without animation
6. Deactivate pdf-plugin — public poster still styled
7. Flush permalinks after deploy (`REWRITE_VERSION = 3`)

Archived detailed E2E notes: `docs/___old/2026-07-14-before-consolidation/envelope-poster-e2e-report.md`

---

## Release decision

**CONDITIONAL GO** (2026-07-14)

- **Ready:** Architecture, automated tests, post-purchase + public code paths
- **Pending:** Configured product + browser proof of storefront → checkout

Archived review: `docs/___old/2026-07-14-before-consolidation/envelope-poster-release-review.md`

---

## Deployment checklist

1. `composer install --no-dev` + `composer dump-autoload -o`
2. `npm run build` (commit `assets/build/` or build in CI)
3. Define `PKS_OI_STORAGE_PATH` outside web root
4. Verify HPOS enabled
5. Activate pdf-plugin with integration adapter
6. Flush permalinks
7. Verify Action Scheduler running (WooCommerce)
8. Configure SMTP for delivery emails on staging first
9. Exclude `/invitation/*` from full-page cache

---

## Operations

### Common incidents

| Symptom | Check |
|---------|-------|
| Public 404 | Permalinks; `publication_status`; token revoked |
| Import failed | `last_error_code` on project; order-item file exists |
| No field form | `_bpp_product.active`; `bpp/is_product_customizable` |
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

**Potential V2:**

- Adapter per-page `render_public_html` for multi-page publish
- `ENVELOPE_PREVIEW_REF` media picker in admin
- Dedicated public “Event details” section below envelope
- Deeper CSP / SVG policy review
- Playwright smoke suite in CI

---

## Historical documentation

All pre-consolidation docs (prompts, audits, gap registers, manual test plans) are archived at:

`docs/___old/2026-07-14-before-consolidation/`

Use archive for forensic detail; canonical truth is `docs/01`–`docs/10` plus current code.
