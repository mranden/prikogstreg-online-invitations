# Prompt 26 — Documentation, release packaging, and final production review

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
Read every project document, implementation file, test result, and build output.

Do not add features.

Complete:

1. readme.txt:
   - purpose
   - dependencies
   - installation
   - product setup
   - customer flow
   - My Account
   - public links
   - guests/address book
   - RSVP/reminders
   - wishlist
   - photos
   - privacy
   - build/development
   - known limitations
   - V2 exclusions
2. Developer documentation:
   - architecture
   - database
   - file storage
   - builder adapter
   - hooks/filters/events
   - routes/endpoints
   - e-mail classes
   - Action Scheduler actions
   - template overrides
   - security model
   - privacy/retention
3. Operations runbook:
   - dependency failure
   - migration failure
   - failed project import
   - storage/checksum issue
   - failed bulk e-mail
   - refund restriction
   - token rotation
   - expiration override
   - deletion retry
4. Release contents:
   - Composer vendor when required
   - compiled assets
   - languages
   - no node_modules
   - no test secrets
   - no temp/project customer data
   - no production source maps unless approved
5. Version/changelog.
6. Create docs/production-review.md.

Run:

- composer validate for both plugins where applicable
- optimized autoload build
- PHP syntax checks
- all automated tests
- npm production builds
- package inspection
- static security searches

Production review must list:

- final file tree
- schema version
- migrations
- public API/hooks
- tests passed
- manual browser/e-mail tests remaining
- hosting requirements
- privacy/legal confirmations remaining
- release blockers
- rollback plan

Do not write “production ready” unless there are no known release blockers.
```
