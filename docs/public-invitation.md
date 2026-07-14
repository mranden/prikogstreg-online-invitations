# Public invitation routes

**Status:** Implemented in Prompt 15 (`src/Public/`, `Security/InvitationToken.php`)

---

## URL

```
/invitation/{token}/
```

- Single opaque route for personal and generic links
- No project, user, order, or guest IDs in the URL
- Invalid, revoked, unpublished, restricted, and expired invitations return the same unavailable page

---

## Token model

| Type | Lookup | Envelope label |
|------|--------|----------------|
| Personal | `pks_oi_guests.token_hash` (checked first) | Guest display name |
| Generic | `pks_oi_projects.generic_token_hash` | Neutral “You are invited” |

Generation (`Security\InvitationToken`):

- `random_bytes(32)` → URL-safe base64 (43 chars)
- Store `hash('sha256', $raw)` only
- Rotate/revoke via `GuestTokenService` and `GenericTokenService`

---

## Public eligibility

`Domain\Project\PublicEntitlement`:

- Project `active` + `published`
- Not deleted, restricted, or expired
- Valid `published_manifest_path`
- Guest not archived/cancelled (personal links)

Published body loaded from verified snapshot files only (`PublicInvitationLoader` + `PublishedHtmlSanitizer`).

---

## Open tracking

- Personal links only (`OpenTracker`)
- Records `first_opened_at_utc`, `last_opened_at_utc`, `open_count`
- Skips owner/staff preview and detected prefetch requests
- **Caveat:** some mail clients prefetch links — document as “invitation link opened”, not “e-mail read”

---

## Privacy headers

- `X-Robots-Tag: noindex, nofollow`
- `Cache-Control: private, no-store`

---

## Sections

| Section | Status |
|---------|--------|
| RSVP | **Implemented (Prompt 17)** — personal + generic forms via REST |
| Wishlist | Active items, reserve/release via REST (`docs/wishlist.md`) |
| Photos | Signed intent + multipart upload; moderation only (`docs/photo-uploads.md`) |
| Photos | Placeholder |

### RSVP (Prompt 17)

- Personal link: prefilled prior response, change until deadline
- Generic link: name + optional e-mail, creates `is_generic_response=1` guest with new personal token
- POST ` /wp-json/prikogstreg-online-invitations/v1/public/{token}/rsvp`
- Idempotency via `X-PKS-OI-Idempotency-Key` header or `idempotency_key` body field
- Rate limit: 10 POST/min per token hash; generic creation 10/hour per IP per project
- Queues `rsvp_confirmation` and `organizer_rsvp` deliveries (sent in Prompt 18)

---

## Manual QA

See [manual-test-public-invitation.md](./manual-test-public-invitation.md).

---

## Tests

```bash
composer test
```

Coverage: personal/generic resolve, invalid/revoked/unpublished/restricted/expired, checksum failure, XSS block, no ID leakage, open tracking.
