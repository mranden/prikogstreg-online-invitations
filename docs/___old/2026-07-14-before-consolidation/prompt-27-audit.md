# Prompt 27 — Full-system audit (staged fallback verification)

**Date:** 2026-07-14  
**Context:** Staged prompts 2–26 were executed in this workspace. Prompt 27 is the one-shot fallback checklist; this document records compliance against that list rather than rebuilding from scratch.

**Verdict:** V1 implementation is **complete in code**. Staging manual verification (browser E2E, e-mail, hosting storage) remains before customer-facing production cutover.

---

## Execution note

Prompt 27 instructs: *"Use only for a new controlled workspace when staged execution is not practical."* This workspace used staged execution. Prompt 27 therefore served as a **gap audit + final integration closure**.

**Closure action (2026-07-14):** Implemented missing PDF Builder formal adapter (`pdf-plugin/src/Integration/*`) — the prior P0 gap from Prompts 4–6.

---

## Staged requirement checklist

| # | Requirement | Status | Evidence |
|---|-------------|--------|----------|
| 1 | PDF Builder regression baseline | **Done** | pdf-plugin tests; product flow preserved |
| 2 | PDF Builder AJAX security hardening | **Done** | `BPP_Ajax_Security`; 8+ security tests |
| 3 | Formal BPP adapter | **Done** | `src/Integration/Online_Invitation_Builder_Adapter.php`, `Integration_Provider` |
| 4 | Context-aware editor | **Done** | `Editor_Asset_Loader`, `Editor_Renderer`; `project_edit` mode |
| 5 | State validation / migration / public renderer | **Done** | `State_Validator`, `State_Normalizer`, `Public_Html_Renderer` |
| 6 | New plugin scaffold | **Done** | `prikogstreg-online-invitations.php`, `src/Plugin.php` |
| 7 | CPT / tables / migrations / repositories | **Done** | Schema v1, 8 tables, `Migrator` |
| 8 | Private file storage | **Done** | `src/Storage/*`, `docs/storage.md` |
| 9 | `online_invitation` product type | **Done** | `WC_Product_Online_Invitation` |
| 10 | Pre-purchase cart / checkout / account flow | **Done** | Checkout integration; PDF Builder cart hooks preserved |
| 11 | Idempotent project creation | **Done** | `ProjectService`, order-item dedup |
| 12 | My Account application | **Done** | Endpoint `online-invitations`, section controllers |
| 13 | Project edit / preview / publish / demo | **Done** | REST + services + templates |
| 14 | Public animated invitation | **Done** | `/invitation/{token}/`, loader + tracker |
| 15 | Guests and private address book | **Done** | CSV import, address book table |
| 16 | RSVP | **Done** | Public REST + e-mails |
| 17 | E-mail delivery / reminders | **Done** | Action Scheduler + WC e-mail classes |
| 18 | Wishlist | **Done** | My Account + public REST |
| 19 | Guest photo uploads | **Done** | Intent HMAC + validation + moderation |
| 20 | Admin / refunds / expiration | **Done** | Support screen, restriction listeners, schedulers |
| 21 | Privacy / cleanup | **Done** | Retention scheduler, hard delete, `docs/privacy-retention.md` |
| 22 | Accessibility / i18n / assets | **Done** | Scoped SCSS, partial `da_DK.po`, `assets/build/` |
| 23 | Comprehensive tests | **Done** | OI 249 tests; pdf-plugin 14 tests |
| 24 | Hardening / performance review | **Done** | `docs/security-review.md`, `performance-review.md`, `data-integrity-review.md` |
| 25 | Documentation / release review | **Done** | `readme.txt`, `CHANGELOG.md`, `docs/production-review.md` |

---

## Architecture constraints (verified)

| Constraint | Status |
|------------|--------|
| Project-owned custom tables | ✓ |
| Private file-backed HTML/state | ✓ |
| Private CPT as admin shell only | ✓ |
| Action Scheduler for async work | ✓ |
| Token hashes only at rest | ✓ |
| Publish sanitized snapshots only | ✓ |
| Never expose raw draft HTML publicly | ✓ |
| V1 unlimited guests | ✓ |
| No V2 features | ✓ |
| PDF Builder product-page flow preserved | ✓ |
| OI uses `bpp/integration/service` only (no direct `BPP_*`) | ✓ |

---

## Automated verification (2026-07-14)

### Online Invitations

```bash
composer validate --no-check-publish   # OK
composer test                          # 249 tests, 784 assertions — OK (2 deprecations)
npm run build                          # OK
```

### PDF Builder

```bash
composer validate --no-check-publish   # OK (license/mpdf warnings)
composer test                          # 14 tests, 28 assertions — OK
npm run build                          # OK (webpack size warnings)
```

### New in Prompt 27

- `pdf-plugin/src/Integration/` — 11 classes
- `tests/Unit/Integration_AdapterTest.php` — 6 tests
- `Integration_Provider::register()` wired in `index.php`

---

## Release blockers

| Item | Status |
|------|--------|
| OI code/security defects | **None** |
| PDF Builder unauthenticated AJAX | **Fixed** (Prompt 25) |
| Missing `bpp/integration/service` adapter | **Fixed** (Prompt 27) |
| Staging manual E2E (M1–M18) | **Open** — not a code defect |
| Production private storage path | **Open** — infrastructure |
| Checkout Blocks | **Unsupported** — documented |
| Cross-client e-mail rendering | **Open** — manual |

**Not labeled “production ready” for end customers** until staging checklist in `docs/production-review.md` §12 is signed off.

---

## Manual tests not claimed as passed

- Full browser E2E (product → checkout → My Account → publish → guest flows)
- E-mail client rendering (welcome, invitation, RSVP, reminder)
- My Account editor save without page reload (project_edit JS bridge)
- Published HTML pen-test (`iframe`, SVG) on staging
- Production `PKS_OI_STORAGE_PATH` outside web root

---

## Rollback

See `docs/production-review.md` §10. Minimum pdf-plugin build includes `BPP_Ajax_Security` and `Integration/` adapter.

---

## Related documents

| Document | Purpose |
|----------|---------|
| `docs/technical-plan.md` | Authoritative architecture |
| `docs/developer-guide.md` | Hooks, routes, schedulers |
| `docs/production-review.md` | Release packaging |
| `docs/test-plan.md` | Automated + manual matrix |
| `docs/builder-integration.md` | PDF Builder contract |
