# Developer guide — Prikogstreg Online Invitations

**Version:** 0.1.0  
**Schema:** `pks_oi_db_version` = `1`  
**Audience:** Plugin developers and operators extending or deploying OI.

This guide consolidates entry points. Detailed feature specs live in linked `docs/` files.

---

## Quick start

```bash
composer install
composer test
npm install && npm run build
```

Bootstrap: `prikogstreg-online-invitations.php` → `vendor/autoload.php` → `Bootstrap\Requirements::boot()` → `Plugin`.

Production autoload: `composer install --no-dev && composer dump-autoload -o`.

---

## Architecture

| Layer | Path | Notes |
|-------|------|-------|
| Bootstrap | `src/Bootstrap/` | Requirements, activation, deactivation |
| WooCommerce | `src/WooCommerce/` | Product type, checkout, orders, e-mails, HPOS |
| Domain | `src/Domain/` | Business logic (projects, guests, delivery, RSVP, photos, wishlist) |
| Database | `src/Database/` | Schema, migrator, repositories |
| Storage | `src/Storage/` | Private filesystem, manifests, atomic I/O |
| Builder | `src/Builder/` | Adapter bridge to PDF Builder |
| My Account | `src/MyAccount/` | Endpoints, controllers, templates |
| Public | `src/Public/` | Rewrite loader, REST, open tracker |
| API | `src/Api/` | Authenticated REST for logged-in editors |
| Admin | `src/Admin/` | CPT, support screen, import retry |
| Scheduling | `src/Scheduling/` | Action Scheduler bridge and handlers |
| Security | `src/Security/` | Authorization, nonces, entitlements |

**References:** `docs/technical-plan.md`, `docs/architecture-decisions.md`, `docs/lifecycle.md`.

---

## Database

- **Option:** `pks_oi_db_version` (constant `PKS_OI_DB_VERSION`, currently `1`).
- **Migrator:** `src/Database/Migrator.php` via `DatabaseBootstrap` on activation and `plugins_loaded`.
- **Tables:** `pks_oi_projects`, `pks_oi_guests`, `pks_oi_address_book`, `pks_oi_wishlist_items`, `pks_oi_wishlist_reservations`, `pks_oi_photos`, `pks_oi_deliveries`, `pks_oi_events`.
- **CPT:** `pks_oi_project` (private); `project_id` = post ID.

**Reference:** `docs/database-schema.md`.

---

## File storage

- **Root:** `PKS_OI_STORAGE_PATH` constant, else `wp-content/uploads/pks-oi-private/`.
- **Layout:** `{root}/projects/{storage_uuid}/` with `state/`, `pages/`, `published/`, `photos/`, `wishlist/`, `tmp/`.
- **Safety:** UUID-only roots; `StoragePath` rejects traversal; `AtomicFileWriter` + SHA-256.

**Reference:** `docs/storage.md`.

---

## Builder adapter

OI resolves the PDF Builder integration service via WordPress filter:

```php
$service = apply_filters( 'bpp/integration/service', null );
```

Expected interface: import canonical builder state from order item storage into project files (`BuilderService`). When the filter returns `null`, project import fails gracefully and admin retry is available.

**References:** `docs/builder-integration.md`, `docs/project-creation.md`.

---

## Hooks and filters

### Filters

| Hook | Args | Purpose |
|------|------|---------|
| `bpp/integration/service` | `$service` | PDF Builder adapter (pdf-plugin) |
| `pks_oi_user_project_count` | `$count`, `$user_id` | Override displayed project count |
| `pks_oi_delivery_send` | `$sent`, `$delivery`, `$context` | Short-circuit delivery send (testing/custom transport) |

### Actions (extension points)

| Hook | Args | When |
|------|------|------|
| `pks_oi_project_creation_started` | `$order_id`, `$order_item_id` | Before import |
| `pks_oi_project_created` | `$project_id`, `$order_id`, `$order_item_id` | Row + CPT created |
| `pks_oi_project_import_succeeded` | `$project_id` | Builder import OK |
| `pks_oi_project_import_failed` | `$project_id`, `$error_code`, `$order_item_id` | Import failed |
| `pks_oi_project_state_saved` | `$project_id`, `$state_version` | Design state saved |
| `pks_oi_project_event_saved` | `$project_id` | Event fields saved |
| `pks_oi_project_published` | `$project_id`, `$published_version` | Published |
| `pks_oi_project_unpublished` | `$project_id` | Unpublished |
| `pks_oi_project_preview_rendered` | `$project_id`, `$is_public` | Preview rendered |
| `pks_oi_demo_invitation_ready` | `$project_id`, `$owner_user_id`, `$preview_url` | Demo e-mail queued |
| `pks_oi_project_welcome_ready` | `$project_id`, `$url` | Welcome e-mail queued |
| `pks_oi_delivery_sent` | `$delivery_id`, `$type` | Single delivery sent |
| `pks_oi_guest_rsvp_saved` | `$guest_id`, `$project_id` | Guest RSVP updated |
| `pks_oi_generic_rsvp_created` | `$guest_id`, `$project_id` | Generic-link RSVP |
| `pks_oi_invitation_opened` | `$guest_id`, `$project_id` | Open tracker fired |
| `pks_oi_guest_token_rotated` | `$guest_id`, `$project_id`, `$version` | Guest token rotated |
| `pks_oi_guest_token_revoked` | `$guest_id`, `$project_id` | Guest token revoked |
| `pks_oi_guest_token_restored` | `$guest_id`, `$project_id`, `$version` | Token restored |
| `pks_oi_generic_token_rotated` | `$project_id`, `$version` | Generic token rotated |
| `pks_oi_generic_token_revoked` | `$project_id` | Generic token revoked |
| `pks_oi_project_expired` | `$project_id` | Expiration applied |
| `pks_oi_project_archived` | `$project_id` | Archived |
| `pks_oi_project_restored` | `$project_id` | Restored from archive |
| `pks_oi_project_restricted` | `$project_id`, `$source` | Restricted (refund etc.) |
| `pks_oi_project_expiry_changed` | `$project_id` | Admin expiry override |
| `pks_oi_before_project_domain_cleanup` | `$project_id` | Before DB/file purge |
| `pks_oi_cleanup_temp_completed` | `$removed` | Temp cleanup batch done |

---

## Routes and endpoints

### My Account (WooCommerce)

| Route | Handler |
|-------|---------|
| `/my-account/online-invitations/` | Project list |
| `/my-account/online-invitations/{id}/` | Project overview |
| `/my-account/online-invitations/{id}/{section}/` | Section controllers |

Rewrite option: `pks_oi_myaccount_rewrite_version` = `1`.

### Public invitation

| Route | Query var |
|-------|-----------|
| `/invitation/{token}/` | `pks_oi_invitation_token` |

Rewrite option: `pks_oi_public_rewrite_version` = `1`.

### Authenticated REST (`prikogstreg-online-invitations/v1`)

| Method | Route | Purpose |
|--------|-------|---------|
| POST | `/projects/{id}/state` | Save builder state |
| POST | `/projects/{id}/event` | Save event |
| POST | `/projects/{id}/publish` | Publish |
| POST | `/projects/{id}/unpublish` | Unpublish |
| POST | `/projects/{id}/demo` | Send demo invitation |

Permission: `Authorization::can_edit_project()` (owner or cap).

### Public REST (`prikogstreg-online-invitations/v1`)

Namespace constant on each controller (`PhotoController`, `RsvpController`, `WishlistController`):

| Method | Route pattern | Purpose |
|--------|---------------|---------|
| POST | `/public/{token}/rsvp` | Guest RSVP |
| GET/POST | `/public/{token}/wishlist` | List wishlist |
| POST | `/public/{token}/wishlist/{item_id}/reserve` | Reserve item |
| POST | `/public/{token}/wishlist/{item_id}/release` | Release item |
| POST | `/public/{token}/photos/intent` | Upload intent |
| POST | `/public/{token}/photos/upload` | Upload binary |

All public routes validate token via `PublicEntitlement`.

**References:** `docs/my-account.md`, `docs/public-invitation.md`, `docs/checkout-integration.md`.

---

## E-mail classes

Registered in `src/WooCommerce/Emails/EmailRegistry.php`:

| Class | ID (typical) | Trigger |
|-------|--------------|---------|
| `ProjectWelcomeEmail` | `pks_oi_project_welcome` | Welcome scheduler |
| `GuestInvitationEmail` | `pks_oi_guest_invitation` | Invitation delivery |
| `DemoInvitationEmail` | `pks_oi_demo_invitation` | Demo send |
| `RsvpConfirmationEmail` | `pks_oi_rsvp_confirmation` | Guest RSVP |
| `OrganizerRsvpEmail` | `pks_oi_organizer_rsvp` | Organiser notified |
| `RsvpReminderEmail` | `pks_oi_rsvp_reminder` | Reminder scheduler |
| `PhotoNotificationEmail` | `pks_oi_photo_notification` | New guest photo |

Base: `AbstractOiEmail`. Templates in `templates/emails/`.

**Reference:** `docs/email-delivery.md`.

---

## Action Scheduler

**Group:** `pks-oi` (`SchedulerMeta::GROUP`).

| Hook | Handler | Purpose |
|------|---------|---------|
| `pks_oi_send_invitation` | `DeliveryActionHandler` | Send invitation e-mail |
| `pks_oi_send_reminder` | `DeliveryActionHandler` | RSVP reminder |
| `pks_oi_send_welcome` | `WelcomeScheduler` | Project welcome |
| `pks_oi_process_delivery_batch` | `DeliveryActionHandler` | Batch processing |
| `pks_oi_reschedule_reminders` | `ReminderScheduler` | Recompute reminders |
| `pks_oi_expire_project` | `ExpirationScheduler` | Single project expiry |
| `pks_oi_expire_projects` | `ExpirationScheduler` | Scan due projects |
| `pks_oi_cleanup_temp` | `RetentionScheduler` | Temp file cleanup |
| `pks_oi_prune_event_logs` | `RetentionScheduler` | Event log retention |
| `pks_oi_prune_delivery_logs` | `RetentionScheduler` | Delivery log retention |

Bridge: `src/Scheduling/ActionSchedulerBridge.php` (schedules only when `as_schedule_single_action` exists).

---

## Templates and theme overrides

PHP templates: `templates/my-account/`, `templates/public/`, `templates/emails/`.

Override in theme:

```text
your-theme/prikogstreg-online-invitations/{template-path}.php
```

Scoped CSS: `.pks-oi*` under `assets/build/css/`. JS localized via `wp_localize_script` (`pksOiAccount`, `pksOiPublic`).

**References:** `docs/theme-overrides.md`, `docs/frontend-accessibility.md`, `docs/i18n.md`.

---

## Security model

- **My Account / REST:** WordPress auth + `Authorization` + nonces on form POST.
- **Public:** Opaque tokens only; hashed in DB; entitlement checks for publish/refund/expire state.
- **Storage:** No direct URLs; streaming after auth.
- **Input:** Repository allowlists; HTML sanitizer on published output.
- **Uploads:** MIME/dimension validation; signed intents; rate limits.

**Reference:** `docs/security-review.md`.

---

## Privacy and retention

- Hard delete, archive, anonymisation flows in `src/Domain/Project/` and `ProjectHardDeleteService`.
- Scheduled pruning via `RetentionScheduler`.
- Open tracking and guest data documented for DPIA.

**References:** `docs/privacy-retention.md`, `docs/lifecycle.md`.

---

## Testing

```bash
composer test              # 249 tests
composer test:unit
composer test:integration
composer test:e2e          # PHP invitation flow chain
```

**Reference:** `docs/test-plan.md`.

---

## Related plugins

**PDF Builder (`pdf-plugin`):** Required for product-page customisation and order item payloads. Deploy alongside OI; run `composer install` and `npm run build` in pdf-plugin. Security: `BPP_Ajax_Security`.

---

## Document index

| Topic | File |
|-------|------|
| Product type | `docs/product-type.md` |
| Checkout | `docs/checkout-integration.md` |
| Project creation | `docs/project-creation.md` |
| Builder | `docs/builder-integration.md` |
| My Account | `docs/my-account.md` |
| Public invitation | `docs/public-invitation.md` |
| Guests | `docs/guest-management.md` |
| RSVP | `docs/rsvp.md` |
| Wishlist | `docs/wishlist.md` |
| Photos | `docs/photo-uploads.md` |
| E-mail | `docs/email-delivery.md` |
| Admin support | `docs/support.md` |
| Operations | `docs/operations-runbook.md` |
| Production review | `docs/production-review.md` |
