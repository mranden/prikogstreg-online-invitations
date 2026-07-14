# Security, privacy, and permissions

**Last verified:** 2026-07-14

---

## Authentication and authorization

| Context | Mechanism |
|---------|-----------|
| My Account | Logged-in customer; `Authorization::can_access_project()` |
| Admin support | Plugin capabilities (see below) |
| Authenticated REST | Cookie session + `wp_rest` nonce |
| Public invitation | Opaque bearer token only — no user IDs in URL |
| Public REST | Token in path + `wp_rest` nonce + rate limits |

**IDOR prevention:** Project IDs in My Account require ownership; public routes never expose `project_id`, `order_id`, or `user_id`.

---

## Token security

`InvitationToken`:

- 256-bit entropy
- SHA-256 hash stored; raw token never persisted
- Revocation: guest token service / generic token rotation
- Invalid token rate limiting (`InvalidTokenRateLimiter`)

Personal tokens checked before generic tokens (`TokenResolver`).

---

## Input sanitization

| Layer | Location |
|-------|----------|
| Published HTML | `PublishedHtmlSanitizer` at publish + public load |
| Cart payload | `CartPayloadValidator` structural checks |
| Guest CSV | Normalisation in import service |
| Photo uploads | MIME/size/dimension validation; share-page session + HMAC upload intent |
| Photo access code | Stored as hash only (`wp_hash_password`); never in URL, QR, or e-mail |
| Admin meta | `ProductMeta` allowlists for presets |

**Public HTML:** Never serves `pages/editable/`, raw `page[]` POST data, or order-item files.

---

## Output escaping

- Templates use `esc_html`, `esc_attr`, `esc_url` for dynamic text
- Published HTML is sanitised then emitted intentionally unescaped in poster partial (trusted snapshot only)
- Envelope addressee label escaped

---

## Capabilities

Registered idempotently in `Admin\Capabilities::register_for_roles()` on every plugin boot (activation also calls this).

| Capability | Purpose | administrator | shop_manager | customer |
|------------|---------|:-------------:|:------------:|:--------:|
| `manage_online_invitations` | Full plugin admin | ✓ | — | — |
| `view_online_invitation_projects` | Menu + list/detail read | ✓ | ✓ | — |
| `edit_online_invitation_projects` | Safe support field edits | ✓ | ✓ | — |
| `moderate_online_invitation_photos` | Photos submenu + moderation | ✓ | ✓ | — |
| `manage_online_invitation_settings` | Settings submenu | ✓ | — | — |
| `run_online_invitation_tools` | Tools tab (publish, restrict, delete, …) | ✓ | ✓ | — |
| `pks_oi_support_projects` | Legacy alias; lifecycle tools | ✓ | ✓ | — |
| `pks_oi_manage_own_projects` | My Account owner access | ✓ | — | ✓ |

Every admin screen and `admin-post.php` handler calls `current_user_can()` — hidden menus are not sufficient authorization.

---

## Admin preview authorization

| Mode | Route | Rules |
|------|-------|-------|
| Draft | `admin-post.php?action=pks_oi_admin_preview&mode=draft` | `view_online_invitation_projects` + per-project nonce; uses `ProjectPreviewService` (no open tracking) |
| Published | same with `mode=published` | Requires `publication_status = published`; loads verified snapshot via `PublicInvitationLoader` |

Responses set `X-Robots-Tag: noindex, nofollow`. Raw generic/personal tokens are never embedded in admin list markup; public links use published preview or customer-owned My Account.

---

## Safe edit policy (admin)

| Editable (domain service) | View-only |
|---------------------------|-----------|
| Event fields in `ProjectEventService::ALLOWED_FIELDS` | Order, product, storage UUID, token hashes |
| Guest display name, email, RSVP status, attendee count | Builder raw state, imported HTML |
| Photo moderation status | Manifest/checksum values, access-code hashes |

Dangerous actions (restrict, hard delete, token rotate, import retry) live on the **Tools** tab: POST + nonce + `pks_oi_support_projects` / `run_online_invitation_tools`.

---

## Admin audit logging

`ProjectLifecycleAudit::record_admin()` writes to `pks_oi_events` for:

- `admin.event_details_changed`
- `admin.guest_updated`
- `admin.photo_moderated`
- Existing lifecycle types (`project.restricted`, `project.restored`, …)

Metadata is scalar-truncated; tokens, HTML blobs, and filesystem paths are never logged.

---

## Privacy

### Data collected

- Project event details, guest names/emails, RSVP responses
- Open tracking (personal links): first/last opened, count — not “email read” proof
- Guest photos (moderated)
- Delivery and audit logs

### Retention (`Privacy\RetentionPolicy`)

Scheduled pruning via `RetentionScheduler`:

- Expired delivery logs
- Old audit events (per policy)
- Abandoned photo temp files

### GDPR tooling

- `PersonalDataExporter` / `PersonalDataEraser` registered via `PrivacyRegistrar`
- Hard delete path for eligible projects (`ProjectDeleteService`)
- Guest anonymization support (`GuestAnonymizer`)

### Public privacy headers

`X-Robots-Tag: noindex, nofollow` on all invitation routes.

---

## File access

Private storage outside web root (recommended). When served:

- `FileStreamResponse` with token entitlement check
- Envelope image, poster CSS — token-scoped URLs only
- Guest photo streams — photo share session or owner auth; `/photos/{token}/stream/{id}/`
- No direct public URLs to `pages/editable/`, order payloads, or `photos/pending|approved/`

---

## Rate limiting

| Endpoint | Limit |
|----------|-------|
| Invalid public tokens | Per client key |
| RSVP POST | Per token / generic creation per IP |
| Photo share verify | 8 failures / 15 min per token + IP |
| Photo upload | Session cookie + intent + size limits |
| Wishlist reserve | Idempotency keys |

---

## Checkout security

- Account required for invitation purchase (prevents orphan projects)
- Checkout Blocks rejected for invitation carts
- HPOS-compatible order meta — no raw SQL on orders

---

## Known security limitations (V1)

| Item | Notes |
|------|-------|
| Sanitizer depth | Blocks dangerous tags/vectors; not full HTML allowlist like BPP `Public_Html_Renderer` |
| SVG in published HTML | Blocked by OI sanitizer |
| Admin envelope upload | Attachment validator; no virus scanning |
| CSP | Site-level concern — not set by plugin |

Pen-test published HTML with real BPP output before production launch.

---

## Permissions matrix (summary)

| Action | Owner | Guest (personal token) | Guest (generic) | Admin |
|--------|-------|------------------------|-----------------|-------|
| Edit project | ✓ | — | — | ✓ |
| Publish | ✓ | — | — | ✓ |
| View public invitation | — | ✓ | ✓ | — |
| RSVP | — | ✓ | ✓ (creates guest) | — |
| Wishlist reserve | — | ✓ | ✓ (name may be required) | — |
| Upload photos | — | — | — | — |
| Upload photos (photo share page) | — | ✓ (access code + session) | ✓ (access code + session) | — |
