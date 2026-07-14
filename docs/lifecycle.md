# Project lifecycle states

`draft → active → (restricted | expired | archived) → deleted`

Publication is independent. Public access requires active + published + valid expiry + manifest.

| Trigger | Result |
|---------|--------|
| Full line refund | restricted, unpublished |
| Order cancelled/failed | restricted |
| Effective expiry | expired, unpublished |
| Admin restore | active (if not refunded) |
| Admin hard delete | full domain cleanup |

See `docs/support.md` and `docs/privacy-retention.md` for policies.
