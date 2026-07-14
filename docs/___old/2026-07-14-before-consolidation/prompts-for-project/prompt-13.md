# Prompt 13 — Build the WooCommerce My Account project application shell

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
Read My Account architecture, project repositories, and authorization rules.

Implement:

- endpoint registration and rewrite flush strategy
- navigation item
- project router
- template loader
- project list
- project overview
- settings shell
- central Authorization service
- owner/admin support view distinction

Requirements:

1. Main endpoint /my-account/online-invitations/.
2. Project routes/sections accepted in technical plan.
3. Theme-overridable templates with safe allowlisted resolution.
4. Project list is paginated and summary-only.
5. Direct project links from welcome e-mail work after login.
6. Every project read checks logged-in ownership or explicit admin support capability.
7. Do not reveal another project through different 403/404 wording.
8. Every state-changing form uses nonce and POST/Redirect/GET.
9. Show controlled dependency/storage/migration errors.
10. Overview includes:
    - project/design
    - order link
    - status
    - publication state
    - setup checklist
    - expiry
    - direct next action
11. Add sections/tabs:
    - overview
    - design
    - event
    - guests
    - address-book
    - preview
    - publish
    - responses
    - wishlist
    - photos
    - settings
12. Later sections may show honest not-yet-implemented messages only during development; no fake success.
13. Plugin templates are accessible and keyboard usable without theme override.
14. Add ownership and endpoint tests.
15. Flush rewrites only on activation/version change, not every request.

Do not implement full section behavior in this prompt.
```
