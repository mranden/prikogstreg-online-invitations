# RSVP and responses

**Status:** Implemented in Prompt 17

---

## Public RSVP

### Personal link

- Token authorizes guest + project context
- Fields: attending yes/no, optional attendee count, comment, dietary notes (per project settings)
- Prior response prefilled on the form
- Changes allowed until `rsvp_deadline_utc` (empty deadline = always open)
- Restricted/expired/unpublished projects reject submission

### Generic link

- Does not impersonate a named guest
- Requires display name; optional e-mail
- Always creates a new guest with `is_generic_response = 1`
- Never matches or updates existing guests by e-mail
- Returns a one-time personal `invitation_url` for return visits
- Rate limited per IP per project

### API

```
POST /wp-json/prikogstreg-online-invitations/v1/public/{token}/rsvp
```

Headers:

- `X-WP-Nonce` — REST nonce
- `X-PKS-OI-Idempotency-Key` — replay-safe idempotency (optional but recommended)

Body (JSON):

```json
{
  "attending": "yes",
  "attendee_count": 2,
  "rsvp_comment": "Looking forward to it",
  "dietary_notes": "Vegetarian",
  "display_name": "Only for generic link",
  "email": "optional@example.com"
}
```

---

## Owner responses (`/my-account/online-invitations/{id}/responses/`)

- Paginated guest list ordered by `responded_at_utc`
- Totals: attending / declined / pending
- Filter by RSVP status
- CSV export (formula-neutralized)
- Recent RSVP event history (`guest_rsvp_submitted`, `guest_rsvp_changed`, `generic_rsvp_created`)
- No public guest-list exposure

---

## Notifications

RSVP submissions queue confirmation and organiser e-mails via `DeliveryQueueService`. Action Scheduler sends them through WooCommerce e-mail classes (see `docs/email-delivery.md`).

| Type | When |
|------|------|
| `rsvp_confirmation` | Guest has e-mail |
| `organizer_rsvp` | Organiser `public_contact_email` or project owner e-mail |

Idempotency keys: `rsvp_confirm:{guest_id}:{signature}`, `organizer_rsvp:{guest_id}:{signature}`

---

## Key classes

| Class | Role |
|-------|------|
| `Domain\Rsvp\RsvpService` | Submit personal/generic RSVP |
| `Domain\Rsvp\RsvpDeadlinePolicy` | Deadline open/closed |
| `Domain\Rsvp\RsvpSanitizer` | Comment/dietary XSS stripping |
| `Public\RsvpController` | REST endpoint |
| `MyAccount\ResponsesController` | Owner overview |

---

## Tests

```bash
composer test -- --filter RsvpTest
```
