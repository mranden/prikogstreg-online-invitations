# Prompt 15 — Implement public personal/generic routes and the animated envelope experience

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
Read token model, published snapshot rules, and public UX specification.

Implement:

- public rewrite route /invitation/{token}/
- token resolver
- public controller
- public templates
- envelope view model
- open tracker
- public CSS/JS
- noindex headers/meta
- unavailable/expired response

Requirements:

1. Generate high-entropy URL-safe tokens.
2. Store only token hashes.
3. Resolve token against guest token and generic project token without disclosing match type in errors.
4. No project/user/order/guest IDs in public URL.
5. Public project must be:
   - active
   - published
   - entitled
   - unexpired
   - backed by valid published manifest/checksum
6. Personal link:
   - guest name on envelope
   - guest-specific interaction context
7. Generic link:
   - neutral envelope text
   - separate generic response flow later
8. Envelope/background from validated product settings.
9. Invitation body uses only published sanitized page files.
10. Public display uses adapter public-view assets/fonts, not raw editor state.
11. Responsive scaling for fixed builder dimensions.
12. Animation:
    - explicit open control
    - keyboard support
    - reduced motion
    - JavaScript-disabled readable fallback
13. Open tracking:
    - personal link only by default
    - first/last opened timestamps
    - avoid owner/admin preview
    - document bot/prefetch caveat
14. Set privacy-safe cache headers according to tokenized content.
15. Rotate/revoke generic and guest tokens through services.
16. Add rate limiting for repeated invalid token lookups.
17. Add tests:
    - valid personal
    - valid generic
    - invalid token
    - revoked token
    - unpublished
    - restricted
    - expired
    - checksum failure
    - XSS fixture not rendered
    - no IDs leaked
18. Build and inspect public assets.
19. Record mobile/reduced-motion manual checks.

Do not implement RSVP/wishlist/photo form actions in this prompt; render section placeholders only when enabled.
```
