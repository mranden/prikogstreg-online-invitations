# Prompt 17 — Implement RSVP, generic-link response creation, response overview, and open status

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
Read public routes, guest model, deadline policy, and event details.

Implement:

1. Personal guest RSVP form.
2. Generic-link RSVP flow.
3. Response overview in My Account.
4. RSVP confirmation behavior.
5. Event logging.
6. Opened/responded status integration.

Personal RSVP:

- token authorizes guest/project context
- attending or not attending
- optional attendee count
- optional comment
- optional dietary notes
- prior response prefilled
- changes allowed until deadline
- expired/restricted/deadline-closed rejects changes
- safe success and error messages
- rate limiting and replay-safe idempotency

Generic RSVP:

- does not impersonate an existing named guest
- asks for name and accepted configured fields
- creates or resolves a guest record using the documented flow
- generates a new guest token internally
- prevents mass guest creation abuse with rate limits and validation
- never exposes whether an e-mail already exists
- records source as generic link

Owner responses:

- paginated
- totals
- filter by status
- export safely
- response history/recent changes
- no public guest-list exposure

Notifications:

- queue RSVP confirmation to guest when e-mail exists
- queue organizer new/changed response notification per settings
- idempotency keys prevent duplicates

Tests:

- first response
- response change
- after deadline
- restricted/expired
- invalid token
- generic response
- generic abuse rate limit
- attendee count validation
- XSS in comments
- organizer notification once
- CSV export
- response event history

Do not implement reminder scheduler until the next prompt.
```
