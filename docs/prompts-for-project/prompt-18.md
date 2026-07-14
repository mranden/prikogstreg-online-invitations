# Prompt 18 — Implement e-mail delivery, demo, Action Scheduler jobs, and RSVP reminders

## Global execution contract

For every prompt:

1. Read all `.cursor` files.
2. Read the accepted `docs/technical-plan.md` when it exists.
3. Inspect current code before editing.
4. Preserve the existing PDF Builder product-page flow.
5. Do not implement V2 features.
6. Use actual commands and report actual results.
7. Update documentation when implementation deviates.
8. Do not state that a test passed unless it ran successfully.

Confirmed V1 includes product type, pre-purchase customisation, account/project creation, My Account editing, public animated invitations, guests, private address book, delivery, open tracking, RSVP/reminders, wishlist, guest photo uploads, admin support, refund restrictions, expiration, privacy, and cleanup.

Explicit V2: SMS, phone verification, guest pricing/capacity, additional-capacity purchases, custom domains, full event microsite, collaborator accounts, direct Ønskeskyen synchronization, and direct social publishing.

---

```text
Read e-mail model, delivery table, Action Scheduler plan, and current implemented actions.

Implement complete e-mail infrastructure:

WooCommerce e-mail classes:

- ProjectWelcomeEmail
- DemoInvitationEmail
- GuestInvitationEmail
- RsvpReminderEmail
- RsvpConfirmationEmail
- OrganizerRsvpEmail
- optional PhotoUploadNotificationEmail if accepted

Requirements:

1. Register in WooCommerce e-mail settings.
2. HTML and plain-text templates.
3. Theme-overridable e-mail templates.
4. Safe subject/heading placeholders.
5. No arbitrary From header from customer input.
6. Direct My Account project link in owner e-mails.
7. Personal token URL only in intended guest e-mail.
8. No raw token in logs.
9. Delivery rows for every queued attempt.
10. Statuses:
    - queued
    - processing
    - sent
    - failed
    - cancelled
    - skipped
11. Clearly document that “sent” means accepted by wp_mail/mailer, not inbox delivery.
12. Action Scheduler:
    - dedicated group
    - minimal ID arguments
    - idempotency key
    - bounded retries/backoff
    - duplicate prevention
13. Bulk invitation send:
    - owner confirmation
    - eligible selected guests
    - valid e-mail
    - project active/published
    - per-guest actions or safe batches
    - result summary
14. Re-send is explicit and creates a new idempotency scope.
15. Demo-to-self is rate-limited and separate from guest stats.
16. Reminder:
    - default five days before RSVP deadline
    - reschedule when deadline changes
    - skip responded/no-email/restricted/expired/unpublished guests
    - cancellation on unpublish/restriction
17. Project creation welcome remains exactly once unless admin explicitly resends.
18. Admin delivery failure view.
19. Tests:
    - registration
    - queue/send
    - duplicate job
    - transient retry
    - permanent failure
    - reminder calculation
    - deadline reschedule
    - skip responded
    - restriction cancellation
    - no token in logs
20. Run scheduled-action tests synchronously in test environment where supported.

Update e-mail and scheduler documentation.
```
