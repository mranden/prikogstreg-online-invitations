# E-mail delivery and scheduling

**Status:** Implemented in Prompt 18

---

## Overview

All outbound e-mails flow through `pks_oi_deliveries` rows and Action Scheduler jobs in group `pks-oi`. WooCommerce e-mail classes render HTML/plain templates and dispatch via `wp_mail` (or the active mailer).

**“Sent”** means the message was accepted by `wp_mail` / the mailer — not confirmed inbox delivery.

---

## WooCommerce e-mail classes

Registered in WooCommerce → Settings → E-mails via `EmailRegistry`:

| Class | ID | Audience |
|-------|-----|----------|
| `ProjectWelcomeEmail` | `pks_oi_project_welcome` | Project owner — once after creation |
| `DemoInvitationEmail` | `pks_oi_demo_invitation` | Owner demo-to-self |
| `GuestInvitationEmail` | `pks_oi_guest_invitation` | Named guests |
| `RsvpReminderEmail` | `pks_oi_rsvp_reminder` | Pending guests before deadline |
| `RsvpConfirmationEmail` | `pks_oi_rsvp_confirmation` | Guest after RSVP |
| `OrganizerRsvpEmail` | `pks_oi_organizer_rsvp` | Organiser on guest response |
| `PhotoNotificationEmail` | `pks_oi_photo_upload` | Optional — pending photo upload |

Templates live under `templates/emails/` (HTML) and `templates/emails/plain/` (plain text). Themes may override via `prikogstreg-online-invitations/emails/{name}.php`.

- **From:** WooCommerce store settings only — never customer-supplied From headers
- **Reply-To:** `public_contact_email` when set
- **Owner e-mails:** My Account project URL in `account_url`
- **Guest e-mails:** Personal token URL only in guest-facing messages; raw tokens stored in transients (`GuestSendTokenStore`), never logged

---

## Delivery statuses

| Status | Meaning |
|--------|---------|
| `queued` | Row created; send job scheduled |
| `processing` | Send in progress |
| `sent` | Accepted by mailer |
| `failed` | Permanent failure after retries |
| `cancelled` | Superseded or project unpublished |
| `skipped` | Ineligible at send time (responded, no e-mail, revoked, etc.) |

---

## Queue and send flow

1. `DeliveryQueueService` inserts a row with an idempotency key and schedules Action Scheduler.
2. `DeliveryActionHandler` receives `pks_oi_send_invitation` or `pks_oi_send_reminder` with `[delivery_id]`.
3. `DeliverySendService` resolves recipient/context, dispatches the WC e-mail, updates status.
4. Retries: up to 3 attempts with delays 60s / 300s / 900s.

### Idempotency examples

| Type | Key pattern |
|------|-------------|
| Welcome | `welcome:{project_id}` |
| Demo | `demo:{project_id}:{scope}` |
| Guest invite | `guest_invite:{guest_id}:{scope}` |
| RSVP reminder | `reminder:{project_id}:{guest_id}:{deadline_date}` |
| RSVP confirm | `rsvp_confirm:{guest_id}:{signature}` |

Re-send invitations use a new scope (`initial` vs timestamp) so a fresh row is created.

---

## Triggers

| Flow | Entry point |
|------|-------------|
| Welcome | `WelcomeScheduler` after qualifying order / usable project |
| Demo | `DemoInvitationService::send_demo()` — rate-limited 300s per project |
| Guest bulk send | My Account guests → “Send invitations to selected” (`InvitationSendService`) |
| RSVP confirm / organiser | `RsvpService` on response |
| Reminders | `ReminderScheduler` on publish/event save; cancelled on unpublish |

Reminder schedule: `rsvp_deadline_utc − reminder_offset_days` (default 5). Skips responded guests, missing e-mail, archived guests, and unavailable projects.

---

## Admin

Failed and skipped deliveries appear in a meta box on the `pks_oi_project` post type (`DeliveryFailures`).

---

## Key classes

| Class | Role |
|-------|------|
| `DeliveryQueueService` | Insert rows + schedule jobs |
| `DeliverySendService` | Process rows, WC dispatch, retries |
| `DeliveryRecipientResolver` | E-mail + URL context |
| `InvitationSendService` | Bulk guest send eligibility |
| `GuestSendTokenStore` | Transient raw tokens for background URLs |
| `ActionSchedulerBridge` | AS integration + sync fallback |
| `ReminderScheduler` | Reminder reschedule/cancel |
| `WelcomeScheduler` | One-time welcome |

---

## Tests

`tests/Integration/Delivery/DeliveryTest.php` covers registration, queue/send, idempotency, retries, reminder timing, reschedule, skip/cancel rules, and token hygiene.

Integration tests use synchronous Action Scheduler stubs (`tests/stubs/action-scheduler.php`).
