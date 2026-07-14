# Admin support and project lifecycle (V1)

## Admin support UI

Support staff with `pks_oi_support_projects` can open **WooCommerce → Invitation projects** (private CPT shell, title-only — no content editor).

The support meta box shows owner, order/item, product, statuses, event/expiry, builder versions, storage health, counts, last error, delivery failures, and recent audit events.

### Support actions

All mutating actions require nonce + `pks_oi_support_projects`: restrict, restore, publish, unpublish, set/clear expiry override, rotate generic token (version only — never shown), resend welcome, retry import, hard delete.

## Refund and cancellation

Full invitation line refund → `restricted`, unpublished, pending deliveries cancelled, data retained, `project.restricted` event. Unrelated partial refunds have no effect. Repeated hooks are idempotent. Cancelled/failed orders restrict existing projects.

Restore requires support capability and re-checks refund state. Does not auto-resend invitations.

## Expiration

`effective_expiry = expiry_override_utc ?? (event_end_utc ?? event_start_utc) + 90 days`

Per-project and daily scan jobs mark `expired` without hard delete. Owner/admin My Account access retained.

## Tests

`tests/Integration/Lifecycle/LifecycleTest.php`
