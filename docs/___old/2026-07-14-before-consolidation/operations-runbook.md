# Operations runbook — Prikogstreg Online Invitations

**Version:** 0.1.0  
**Audience:** Hosting operators and support staff.

Procedures assume WP-CLI or admin access, WooCommerce admin, and plugin admin support screen (`pks_oi_project` CPT → Support).

---

## 1. Dependency failure

### Symptoms

- Admin notice: missing WooCommerce, Action Scheduler, or PDF Builder.
- Projects stuck in `import_pending` or customiser unavailable on product page.

### Diagnosis

1. **Plugins → Installed:** Confirm WooCommerce, PDF Builder (`pdf-plugin`), and OI are active.
2. Check `bpp/integration/service` filter returns a service (Builder import requires it).
3. Review `src/Bootstrap/Requirements.php` notices in wp-admin.

### Recovery

1. Activate missing plugins in order: WooCommerce → PDF Builder → OI.
2. If Composer autoload missing: `composer install --no-dev` in OI plugin directory.
3. Reload product page; verify PDF customiser renders (theme integration).
4. Retry failed imports from **Support** screen or `ProjectImportRetry` admin action.

### Prevention

- Staging checklist includes all three plugins before go-live.
- Monitor `pks_oi_project_import_failed` action (custom logging plugin).

---

## 2. Migration failure

### Symptoms

- Activation error or missing tables.
- `pks_oi_db_version` option lower than `PKS_OI_DB_VERSION` (1).
- SQL errors in debug log referencing `pks_oi_*` tables.

### Diagnosis

1. Compare `get_option( 'pks_oi_db_version' )` to constant `1`.
2. Inspect tables: `wp_pks_oi_projects`, `wp_pks_oi_guests`, etc.
3. Check `MigrationLock` — stale lock from interrupted migration.

### Recovery

1. **Backup database** before any repair.
2. Deactivate and reactivate plugin (runs `Migrator` on activation).
3. If lock stuck: delete option `pks_oi_migration_lock` (transient/option per `MigrationLock` implementation) and reactivate.
4. For partial tables: run activation on staging clone first; use `dbDelta` repair via reactivation only (do not hand-edit schema without dev sign-off).

### Rollback

- Deactivate OI (data retained). Restore DB backup from before failed migration attempt.

---

## 3. Failed project import

### Symptoms

- Order completed but no My Account project.
- Project row with `status` import error; admin **Support** shows import failure.
- Action `pks_oi_project_import_failed` with codes `adapter_unavailable`, `creation_failed`.

### Diagnosis

1. WooCommerce order → line item → verify PDF Builder order item files exist (`BPP_Order_Item_Storage`).
2. Support screen: import error code and order item ID.
3. Check private storage writable: `StorageDiagnostic` in admin support view model.
4. Verify `bpp/integration/service` not null.

### Recovery

1. Fix root cause (adapter, permissions, disk space).
2. Admin **Retry import** (`ProjectImportRetry`) for the project/post ID.
3. If duplicate risk: confirm no existing `project_id` for same `order_item_id` in `pks_oi_projects`.
4. Notify customer when project appears under My Account.

### Escalation

- If order item payload corrupt: manual refund or manual project creation requires developer intervention (not automated in V1).

---

## 4. Storage or checksum issue

### Symptoms

- Design save/publish fails.
- Support diagnostic reports missing `manifest.json` or checksum mismatch.
- `SafeFileReader` verification errors in logs (if custom logging added).

### Diagnosis

1. Confirm `PKS_OI_STORAGE_PATH` exists and is writable by PHP user.
2. Path: `{root}/projects/{storage_uuid}/manifest.json` and `state/current.json`.
3. Compare manifest checksums to on-disk files (`StorageDiagnostic`).

### Recovery

1. **Disk full:** Free space; retry save from My Account.
2. **Permission denied:** Fix ownership (`www-data` or equivalent); ensure directory `0750`, files `0640` (site policy).
3. **Checksum mismatch after restore:** Restore project tree from backup; if only `state/current.json` corrupt, restore from `state/previous.json` via support/developer (manual file copy).
4. **Missing tree:** Retry import only if DB row exists without files — may require hard delete and re-import from order.

### Prevention

- Store root **outside** web root.
- Include `pks-oi-private` or `PKS_OI_STORAGE_PATH` in backup jobs (not just DB).

---

## 5. Failed bulk e-mail / delivery queue

### Symptoms

- Guests not receiving invitations.
- Deliveries stuck in `queued` / `failed` in `pks_oi_deliveries`.
- Action Scheduler pending actions for `pks_oi_send_invitation` piling up.

### Diagnosis

1. WooCommerce → Status → Scheduled Actions: filter group `pks-oi` or hooks `pks_oi_send_*`.
2. Admin **Delivery failures** screen (`DeliveryFailures.php`).
3. Verify `wp_mail` / SMTP plugin; check spam suppression.
4. Confirm project not `restricted`, `expired`, or `unpublished`.

### Recovery

1. Fix SMTP/credentials; send test mail from WooCommerce.
2. Retry failed deliveries from admin support or re-queue via delivery service (support UI).
3. Clear stuck Action Scheduler actions only after identifying cause (avoid duplicate sends — check `delivery_id` status).
4. Max attempts: `SchedulerMeta::MAX_SEND_ATTEMPTS` (3) with backoff `60, 300, 900` seconds.

### Filter hook

- Tests/custom SMTP: `pks_oi_delivery_send` filter can short-circuit.

---

## 6. Refund restriction

### Symptoms

- Customer refunded but guests still receive mail or public link works.
- Project should be `restricted` but is not.

### Diagnosis

1. Order status/refund hooks in `src/WooCommerce/OrderListeners/`.
2. Project `status` and restriction source in DB.
3. Action `pks_oi_project_restricted` fired?

### Recovery

1. Manually restrict from admin Support if hook missed (partial refund edge cases).
2. Revoke generic token and rotate guest tokens if link leakage suspected.
3. Unschedule pending deliveries: `DeliveryQueueService` unschedules on restrict.
4. Document case for dev if hook should have fired on HPOS refund event.

---

## 7. Token rotation

### Guest token compromised or guest forwarded link inappropriately

1. My Account → Guests → rotate token for guest **or** admin Support → rotate guest token.
2. Old links return 404/invalid; new e-mail delivery required.
3. Action: `pks_oi_guest_token_rotated`.

### Generic link compromised

1. My Account → Settings → rotate generic link.
2. Action: `pks_oi_generic_token_rotated`.
3. Re-share new URL only through intended channel.

### Post-rotation

- Verify `/invitation/{old_token}/` fails.
- Resend invitation e-mail if guest needs new link.

---

## 8. Expiration override

### Extend event or postpone expiry

1. Admin Support → change expiry datetime (fires `pks_oi_project_expiry_changed`).
2. `ExpirationScheduler` reschedules `pks_oi_expire_project`.
3. Confirm `event_end_utc` and project expiry fields aligned with customer expectation.

### Restore expired project

1. Support → **Restore** (`ProjectRestoreService`) if archived/expired in error.
2. Re-publish if needed; reschedule reminders via `ReminderScheduler`.

---

## 9. Deletion retry

### Customer deletion request or GDPR erasure

1. Verify identity and order ownership.
2. My Account customer delete (`ProjectCustomerDeleteService`) where enabled, or admin **Hard delete** (`ProjectHardDeleteService`).
3. Hard delete: removes CPT, DB rows, storage tree, unschedules actions (`pks_oi_before_project_domain_cleanup`).

### Partial failure

- If files remain after DB delete: run `StorageCleanup` / manual tree removal under `{storage_uuid}`.
- If DB rows remain: do not delete files until referential cleanup confirmed.
- Retry hard delete from Support once storage writable.

### Soft delete / archive

- **Archive** retains data for retention period; **hard delete** is irreversible.

---

## 10. Permalink / 404 on public links

1. Settings → Permalinks → Save (flush rewrite rules).
2. Verify options `pks_oi_public_rewrite_version` and `pks_oi_myaccount_rewrite_version` = `1`.
3. Conflicting plugins affecting `^invitation/` rule — test with default theme.

---

## 11. Action Scheduler backlog

1. Ensure WP-Cron or real cron triggers `action_scheduler_run_queue`.
2. WooCommerce → Status → Scheduled Actions → Past due count.
3. Server time skew affects `event_start_utc` scheduling.

---

## 12. Contacts and references

| Resource | Location |
|----------|----------|
| Support UI | WP Admin → Projects (`pks_oi_project`) |
| Developer guide | `docs/developer-guide.md` |
| Security | `docs/security-review.md` |
| Privacy | `docs/privacy-retention.md` |
| Test matrix | `docs/test-plan.md` §3 (manual) |
| Production review | `docs/production-review.md` |
