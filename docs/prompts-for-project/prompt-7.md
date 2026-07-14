# Prompt 7 — Scaffold the new Online Invitations plugin and runtime requirements

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
Read all instructions and accepted plans.

Work in prikogstreg-online-invitations.

Create the production scaffold:

- prikogstreg-online-invitations.php
- composer.json
- package.json
- .gitignore
- readme.txt
- uninstall.php
- src/Plugin.php
- src/Bootstrap/Requirements.php
- src/Bootstrap/Activation.php
- src/Bootstrap/Deactivation.php
- initial namespace/folder structure
- templates directories
- assets source/build directories
- languages directory
- tests bootstrap/config

Requirements:

1. Fixed identity from .cursor files.
2. Thin main plugin file:
   - valid header
   - ABSPATH guard
   - constants
   - Composer autoload guard
   - requirements check
   - root boot
3. Declare WooCommerce dependency safely.
4. Detect the PDF Builder adapter through bpp/integration/service.
5. Missing/incompatible PDF Builder:
   - no fatal
   - clear admin notice
   - online invitation product/project actions disabled safely
   - existing customer data untouched
6. Use Composer PSR-4 for PrikOgStreg\OnlineInvitations\.
7. Use PHP minimum accepted in technical plan.
8. Root Plugin wires feature registrars without a general-purpose service container.
9. Add plugin version and database schema version separately.
10. Add HPOS compatibility declaration where supported.
11. Add Action Scheduler availability check through WooCommerce.
12. Add theme template loader contract, but no full templates yet.
13. Add npm scripts and production output paths accepted in plan.
14. Compiled assets are included and source assets are never enqueued.
15. uninstall.php must preserve customer data by default.
16. Add initial unit/bootstrap tests.
17. Run composer validate/dump-autoload, PHP syntax, npm install/build, and tests.
18. Update technical plan with actual scaffold.

Do not implement product type, tables, or customer features in this prompt.
```
