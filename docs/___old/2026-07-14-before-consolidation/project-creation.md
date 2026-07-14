# Project creation from orders

**Status:** Implemented in Prompt 12 (`src/Domain/Project/`, `src/WooCommerce/Orders/`)

---

## Trigger

Qualifying WooCommerce order statuses:

- `on-hold`
- `processing`
- `completed`

`ProjectOrderListener` hooks `woocommerce_order_status_{status}` (priority 20). **Not** `woocommerce_thankyou`.

---

## Idempotency

Per invitation order line:

1. `_pks_oi_project_id` order-item meta (fast path)
2. Unique `pks_oi_projects.order_item_id` (DB)
3. `ProjectCreationLock` per `order_item_id` (120s TTL)

Repeated status transitions and concurrent hooks do not duplicate projects or welcome e-mails.

---

## Creation flow

```text
ProjectOrderListener
  → ProjectService::process_order()
  → foreach line_item (WC CRUD)
      → skip non-online_invitation
      → resolve existing project / relink meta
      → acquire lock
      → ProjectFactory: CPT shell + DB row
      → adapter load_state / validate_state / migrate_state
      → ProjectStorage::import_from_builder_state()
      → update project row (active, state_version, manifest path)
      → link _pks_oi_project_id on order item
      → WelcomeScheduler::queue_once()
      → audit event + do_action hooks
```

---

## Failure handling

- Import failure leaves a **retryable** project row with `last_error_code` (status `draft`)
- Partial storage is rolled back; order-item builder payload preserved in PDF Builder
- Admin retry: `admin-post.php?action=pks_oi_retry_project_import` (`ProjectImportRetry`)
- No invisible orphan: CPT + DB row always visible when creation started

---

## Hooks

| Hook | When |
|------|------|
| `pks_oi_project_creation_started` | Before CPT/DB create |
| `pks_oi_project_created` | After successful link |
| `pks_oi_project_import_succeeded` | After file import |
| `pks_oi_project_import_failed` | On validation/storage failure |
| `pks_oi_project_welcome_ready` | Welcome scaffold (My Account URL) |
| `pks_oi_send_welcome` | Action Scheduler callback (when AS available) |

---

## Welcome e-mail

`WelcomeScheduler` queues/sends once per project when state is usable (`state_version >= 1`, no `last_error_code`). Scaffold fires `pks_oi_project_welcome_ready` with My Account project URL until `ProjectWelcomeEmail` WC class ships (Prompt 18).

---

## Tests

```bash
composer test
```

Coverage: qualifying statuses, idempotency, lock, mixed cart, adapter missing, malformed payload, file failure, relink, welcome once, admin retry path, storage import.
