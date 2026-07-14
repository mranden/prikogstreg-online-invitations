# Codebase map and bootstrap

**Last verified:** 2026-07-14

---

## Entry point

`prikogstreg-online-invitations.php` defines constants (`PKS_OI_VERSION`, `PKS_OI_PLUGIN_PATH`, etc.), loads Composer autoload, calls `Requirements::boot()`.

---

## Boot sequence

```
prikogstreg-online-invitations.php
  → Requirements::boot()
      → plugins_loaded (priority 5) → Plugin::boot()
```

### Requirements (`src/Bootstrap/Requirements.php`)

| Check | Minimum |
|-------|---------|
| PHP | 8.1 |
| WordPress | 6.5 |
| WooCommerce | 8.0 |
| HPOS | Must be enabled |

Failure shows an admin notice; plugin does not boot.

### Plugin registrars (`src/Plugin.php`)

Registered in order:

1. `Compatibility` — HPOS declaration
2. `ProductTypeRegistrar` — `online_invitation` product type
3. `CartCheckoutRegistrar` — cart markers, validation, checkout rules
4. `ProjectOrderRegistrar` + `ProjectImportRetry` — order → project
5. `ProjectSupportRegistrar` — admin support tools
6. `DatabaseBootstrap` — migrations
7. `StorageRegistry::bootstrap()` — private storage root protection
8. `ProjectPostType` + `ProjectDomainCleanup`
9. `Notices`
10. `TemplateLoader`
11. `MyAccountRegistrar`
12. `RestRegistrar` — authenticated + public REST
13. `PublicRegistrar` — `/invitation/{token}/`
14. `SchedulerRegistrar` — Action Scheduler jobs
15. `EmailRegistry` — WooCommerce emails
16. `DeliveryFailures`
17. `PrivacyRegistrar`
18. `BuilderService` — `bpp/integration/service` filter

**Activation** (`Activation.php`): schema migrate, storage bootstrap, flush My Account + public rewrites, register CPT.

---

## Source layout (`src/`)

| Package | Responsibility |
|---------|----------------|
| `Admin/` | Support screen, delivery failures, project CPT admin |
| `Api/` | Authenticated project REST (`ProjectRestController`) |
| `Bootstrap/` | Activation, requirements |
| `Builder/` | Adapter resolution (`BuilderService`) |
| `Database/` | Schema v2, repositories, migrations |
| `Domain/` | Business logic (project, guest, RSVP, wishlist, photos, delivery) |
| `MyAccount/` | WC endpoint `online-invitations`, section controllers |
| `Privacy/` | Export, erasure, retention |
| `Public/` | Token routes, envelope, poster assets, public REST |
| `Scheduling/` | Welcome, reminder, expiration, retention, delivery |
| `Security/` | `Authorization`, `InvitationToken`, `PublishedHtmlSanitizer` |
| `Storage/` | Private files, manifests, streaming |
| `Support/` | `TemplateLoader`, `UtcDateTime` |
| `WooCommerce/` | Product type, cart, checkout, orders, emails |

**Outside PSR-4:** `src/WooCommerce/ProductType/WC_Product_Online_Invitation.php` (loaded by WooCommerce factory).

---

## Templates (`templates/`)

| Area | Examples |
|------|----------|
| `myaccount/` | Project sections (overview, design, guests, publish, …) |
| `public/` | `invitation.php`, `envelope.php`, `poster.php`, RSVP, wishlist, photos |
| `emails/` | Wrapper + transactional bodies |
| `admin/` | Support UI |

Resolution: child theme → parent theme → plugin (`TemplateLoader`).

---

## Assets

| Path | Build |
|------|-------|
| `assets/src/js/` | `npm run build:js` → `assets/build/js/` (account, public, admin) |
| `assets/src/scss/` | `npm run build:css` → `assets/build/css/` |
| `assets/css/bpp-poster-display-fallback.css` | Static fallback for publish-time poster CSS snapshot |

**Do not edit `assets/build/` directly** — rebuild from source.

---

## Tests (`tests/`)

| Suite | Path | Methods |
|-------|------|---------|
| unit | `tests/Unit/` | 111 |
| integration | `tests/Integration/` (excl. E2E) | 216 |
| e2e | `tests/Integration/E2E/` | 1 |

Bootstrap: `tests/bootstrap.php` (Brain Monkey stubs).  
Fixtures: `tests/Fixtures/`.  
Runtime audit script: `tests/audit/runtime-diagnostics.php` (manual, not PHPUnit).

---

## Composer / npm scripts

```bash
composer test              # all PHPUnit
composer test:unit
composer test:integration
composer test:e2e
npm run build              # css + js
npm run dev                # watch mode
composer run i18n          # POT + Danish PO
```

---

## Configuration constants

| Constant | Purpose |
|----------|---------|
| `PKS_OI_STORAGE_PATH` | Private storage root (recommended outside web root) |
| `PKS_OI_VERSION` | Plugin version |
| `PKS_OI_DB_VERSION` | Schema version (currently `1` in bootstrap; tables at schema v2) |

---

## Agent / Cursor files

| File | Role |
|------|------|
| `.cursor/rules.md` | Strict implementation rules |
| `.cursor/agent.md` | Architecture reference for agents |
| `.cursor/prompt.md` | Prompt conventions |

These reference canonical `docs/01–10` files after consolidation.
