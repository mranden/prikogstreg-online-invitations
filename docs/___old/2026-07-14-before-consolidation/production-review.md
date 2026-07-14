# Production review — Prikogstreg Online Invitations

**Date:** 2026-07-14 (Prompt 26)  
**Plugin version:** `0.1.0` (`PKS_OI_VERSION`)  
**DB schema version:** `1` (`PKS_OI_DB_VERSION`, `Schema::CURRENT_VERSION`)  
**Verdict:** **No known code release blockers.** Staging manual verification (browser E2E, e-mail, storage path) remains before customer-facing production cutover.

---

## 1. Release contents

### Included in production ZIP

| Component | Path | Notes |
|-----------|------|-------|
| Bootstrap | `prikogstreg-online-invitations.php`, `readme.txt`, `CHANGELOG.md` | |
| Application code | `src/` (185 PHP files) | PSR-4 autoload |
| Templates | `templates/` (43 files) | Theme-overridable |
| Compiled assets | `assets/build/css/`, `assets/build/js/` (6 files) | No source maps |
| Source assets (optional) | `assets/src/` | Include for maintainability; not required at runtime |
| Languages | `languages/prikogstreg-online-invitations-da_DK.po`, `.gitkeep` | Compile `.mo` on deploy |
| Composer autoload | `vendor/autoload.php`, `vendor/composer/` (12 files with `--no-dev`) | **Required** — no runtime Composer packages |
| Documentation | `docs/` (excluding `docs/prompts-for-project/` internal prompts) | Operator + developer |
| Lock file | `composer.lock` | Reproducible autoload |

### Excluded from production ZIP

| Item | Reason |
|------|--------|
| `node_modules/` | Build-time only |
| `tests/`, `phpunit.xml.dist` | Dev/CI |
| `.phpunit.cache/` | Dev |
| Dev `vendor/` packages (PHPUnit, Brain Monkey, etc.) | `composer install --no-dev` |
| `.cursor/`, `docs/prompts-for-project/` | Internal agent prompts |
| Secrets, `.env`, customer project data | Security |
| Source maps | None present in `assets/build/` (verified) |

### Build commands (verified 2026-07-14)

```bash
cd prikogstreg-online-invitations
composer validate --no-check-publish          # OK
composer install --no-dev
composer dump-autoload -o                       # 185 classes; WC_Product_Online_Invitation skipped (expected)
npm run build                                   # OK — esbuild + sass
```

**PDF Builder (deploy alongside):**

```bash
cd pdf-plugin
composer validate --no-check-publish          # OK (license/mpdf warnings only)
composer test                                   # 8 tests, 17 assertions — OK
npm run build                                   # OK (webpack size warnings)
```

---

## 2. Final file tree (summary)

```text
prikogstreg-online-invitations/
├── prikogstreg-online-invitations.php
├── readme.txt
├── CHANGELOG.md
├── composer.json
├── composer.lock
├── package.json
├── assets/
│   ├── build/css/          account.css, admin.css, public.css
│   ├── build/js/           account.js, admin.js, public.js
│   └── src/                scss + js sources
├── languages/
│   └── prikogstreg-online-invitations-da_DK.po
├── src/                    185 PHP — Admin, Api, Bootstrap, Builder, Database,
│                           Domain, MyAccount, Public, Scheduling, Security,
│                           Storage, WooCommerce, templates helpers
├── templates/
│   ├── emails/
│   ├── my-account/
│   └── public/
├── vendor/                 production: autoload.php + composer/ only (12 files)
└── docs/                   architecture, schema, runbooks, reviews, test-plan
```

---

## 3. Schema and migrations

| Item | Value |
|------|-------|
| Schema version option | `pks_oi_db_version` |
| Current version | `1` |
| Migrator | `src/Database/Migrator.php` |
| Lock | `src/Database/MigrationLock.php` |
| Tables | 8 (`pks_oi_*`) — see `docs/database-schema.md` |
| CPT | `pks_oi_project` |

Migration order on activation/bootstrap: projects → guests → address_book → wishlist_items → wishlist_reservations → photos → deliveries → events.

No v2 migration scripts yet.

---

## 4. Public API and hooks

### REST

- **Authenticated:** `prikogstreg-online-invitations/v1/projects/{id}/state|event|publish|unpublish|demo`
- **Public (token):** `.../public/{token}/rsvp`, `.../wishlist`, `.../wishlist/{item_id}/reserve|release`, `.../photos/intent|upload`

### Rewrites

- `/invitation/{token}/` — `pks_oi_invitation_token`
- `/my-account/online-invitations/` — WooCommerce endpoint

### Extension hooks

- **Filters:** `bpp/integration/service`, `pks_oi_user_project_count`, `pks_oi_delivery_send`
- **Actions:** 30+ lifecycle hooks — see `docs/developer-guide.md`

### Action Scheduler (group `pks-oi`)

`pks_oi_send_invitation`, `pks_oi_send_reminder`, `pks_oi_send_welcome`, `pks_oi_process_delivery_batch`, `pks_oi_reschedule_reminders`, `pks_oi_expire_project`, `pks_oi_expire_projects`, `pks_oi_cleanup_temp`, `pks_oi_prune_event_logs`, `pks_oi_prune_delivery_logs`

---

## 5. Verification results (Prompt 26 run)

| Check | Result |
|-------|--------|
| `composer validate` (OI) | **OK** |
| `composer validate` (pdf-plugin) | **OK** (warnings: license, mpdf exact version) |
| `composer dump-autoload -o --no-dev` | **OK** — 185 classes |
| PHP syntax `php -l` on `src/**/*.php` | **OK** — 185 files, no errors |
| `composer test` (OI) | **OK** — 249 tests, 784 assertions, 2 deprecations |
| `composer test` (pdf-plugin) | **OK** — 14 tests, 28 assertions |
| `npm run build` (OI) | **OK** |
| `npm run build` (pdf-plugin) | **OK** (3 webpack performance warnings) |
| Source maps in `assets/build/` | **None** |
| Static security grep (`eval`, `shell_exec`, `unserialize` in `src/`) | **No issues** — `base64_decode`/`fpassthru` only in documented safe contexts |

### Package inspection

| Check | Result |
|-------|--------|
| `node_modules/` in release | **Must exclude** — present in dev tree only |
| Production `vendor/` file count | **12** after `composer install --no-dev` |
| Test secrets in repo | **None** — fixtures use synthetic data |
| Customer project data in repo | **None** |

---

## 6. Manual tests remaining

From `docs/test-plan.md` §3 (M1–M18) — **not automated:**

| Area | Items |
|------|-------|
| Browser E2E | Product customise → checkout → My Account → publish → guest RSVP/wishlist/photo |
| E-mail | Welcome, invitation, RSVP, reminder, photo notification — real SMTP + client rendering |
| Public invitation | Animation, mobile, token invalidation after rotation |
| Admin support | Import retry, expiry override, hard delete on staging copy |
| Checkout Blocks | Not supported — classic checkout only |
| Published HTML pen-test | Malicious builder output (`iframe`, SVG) |
| Production storage | `PKS_OI_STORAGE_PATH` outside web root, backup coverage |

---

## 7. Hosting requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.1+ |
| WordPress | 6.5+ |
| MySQL/MariaDB | WordPress-supported version |
| WooCommerce | 8.0+ with HPOS |
| Action Scheduler | Via WooCommerce; real cron recommended |
| Disk | Private storage for projects (photos, state, published HTML) — plan per active project |
| Memory | Standard WordPress + WooCommerce; photo processing may need 256M+ `memory_limit` |
| Outbound mail | SMTP or transactional provider |
| SSL | Required for My Account and token links |

---

## 8. Privacy / legal confirmations remaining

| Item | Status |
|------|--------|
| Privacy policy mentions invitation guest data, open tracking, photo uploads | **Operator** — confirm site policy text |
| Retention periods match `docs/privacy-retention.md` | **Operator** — confirm cron runs |
| DPIA for guest e-mail and photos | **Operator** |
| Guest token in transients (`GuestSendTokenStore`) — backup policy | **Operator** |
| Right-to-erasure procedure | Documented in `docs/operations-runbook.md` §9 |

---

## 9. Release blockers

| Plugin | Blocker | Status |
|--------|---------|--------|
| Online Invitations | Code/security | **None** |
| PDF Builder | Unauthenticated AJAX | **Fixed** (Prompt 25, `BPP_Ajax_Security`) |
| PDF Builder | Missing `bpp/integration/service` adapter | **Fixed** (Prompt 27, `pdf-plugin/src/Integration/`) |
| Deployment | Staging manual E2E + e-mail | **Open** — not a code defect |
| Deployment | Private storage path on production host | **Open** — infrastructure |
| i18n | Partial Danish `.po` | **Non-blocking** |

**Do not label “production ready” for end customers until staging manual matrix and hosting checklist complete.**

---

## 10. Rollback plan

### Plugin rollback

1. Deactivate **Prikogstreg Online Invitations** (data preserved in DB + storage).
2. Deploy previous plugin ZIP if reverting code; run `composer install --no-dev` on rolled-back version.
3. Flush permalinks if rewrite behaviour changes.

### Database rollback

- Schema v1 only — no downgrade migrator. Rollback = restore DB backup taken before upgrade.
- Options to note: `pks_oi_db_version`, `pks_oi_public_rewrite_version`, `pks_oi_myaccount_rewrite_version`.

### Storage rollback

- Project files are forward-only; restore `{PKS_OI_STORAGE_PATH}/projects/` from backup if corruption occurred during release window.

### PDF Builder rollback

- If rolling back pdf-plugin AJAX security: **do not** — prior versions lack `BPP_Ajax_Security`. Minimum pdf-plugin build from Prompt 25 required alongside OI.

### Communication

- If rollback during active events: restrict projects via admin Support to stop deliveries; notify affected organisers.

---

## 11. Related documents

| Document | Purpose |
|----------|---------|
| `readme.txt` | Operator + merchant overview |
| `CHANGELOG.md` | Version history |
| `docs/developer-guide.md` | Architecture, hooks, routes |
| `docs/operations-runbook.md` | Incident response |
| `docs/security-review.md` | Security audit |
| `docs/performance-review.md` | Performance audit |
| `docs/data-integrity-review.md` | Data integrity audit |
| `docs/test-plan.md` | Automated + manual matrix |

---

## 12. Sign-off checklist

- [x] Automated tests pass (OI + pdf-plugin)
- [x] Production build assets present, no source maps
- [x] `readme.txt`, changelog, developer + operations docs
- [x] Release packaging documented
- [x] Rollback plan documented
- [ ] Staging manual E2E (M1–M18)
- [ ] Production `PKS_OI_STORAGE_PATH` configured and backed up
- [ ] Privacy policy / DPIA updated for guest data
- [ ] `.mo` compiled from Danish `.po` on deploy
