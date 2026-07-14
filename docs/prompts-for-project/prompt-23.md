# Prompt 23 — Complete frontend design, accessibility, internationalization, and theme overrides

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
Read all templates/assets and accessibility requirements.

Complete visual/interaction implementation for:

- My Account project application
- public envelope/invitation
- RSVP
- wishlist
- photo upload
- admin support where custom assets are needed
- e-mails

Requirements:

1. Scope CSS to .pks-oi roots.
2. Inherit theme typography where appropriate.
3. No generic global selectors.
4. Responsive phone/tablet/desktop layouts.
5. Fixed invitation canvas scales without horizontal page breakage.
6. Envelope:
   - accessible open control
   - reduced-motion mode
   - no-JS fallback
7. Forms:
   - labels
   - descriptions
   - inline and summary errors
   - focus management
   - aria-live status for async operations
8. Keyboard operation.
9. Visible focus.
10. No hover-only behavior.
11. Comfortable touch targets.
12. Modal/dialog semantics where used.
13. Upload progress accessible.
14. Color not sole status indicator.
15. 200% zoom support.
16. Translation-ready strings using only the fixed text domain.
17. Danish source translations/copy according to project conventions.
18. Generate POT documentation/command; do not require runtime i18n tooling.
19. Theme override path and template version headers.
20. Child theme then parent theme then plugin fallback.
21. Safe allowlisted template lookup.
22. E-mail plain text remains readable.
23. No SMS/capacity UI.
24. Production builds:
   - account CSS/JS
   - public CSS/JS
   - admin assets
25. No source maps in production unless release plan explicitly includes them.
26. Run build and inspect output.
27. Add accessibility manual checklist and automated checks where available.

Do not redesign the PDF Builder editor itself beyond integration needs.
```
