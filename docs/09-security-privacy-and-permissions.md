# Security, privacy, and permissions

**Last verified:** 2026-07-14

---

## Authentication and authorization

| Context | Mechanism |
|---------|-----------|
| My Account | Logged-in customer; `Authorization::can_access_project()` |
| Admin support | `pks_oi_manage_all_projects` capability |
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
| Photo uploads | MIME/size/dimension validation |
| Admin meta | `ProductMeta` allowlists for presets |

**Public HTML:** Never serves `pages/editable/`, raw `page[]` POST data, or order-item files.

---

## Output escaping

- Templates use `esc_html`, `esc_attr`, `esc_url` for dynamic text
- Published HTML is sanitised then emitted intentionally unescaped in poster partial (trusted snapshot only)
- Envelope addressee label escaped

---

## Capabilities

| Capability | Purpose |
|------------|---------|
| `pks_oi_manage_all_projects` | Admin support screen actions |

Registered in `Admin\Capabilities` on activation.

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
- No direct public URLs to `pages/editable/` or order payloads

---

## Rate limiting

| Endpoint | Limit |
|----------|-------|
| Invalid public tokens | Per client key |
| RSVP POST | Per token / generic creation per IP |
| Photo upload | Intent + size limits |
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
| Upload photos | — | ✓ | ✓ (name may be required) | — |
