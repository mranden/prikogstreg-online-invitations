# Prikogstreg Online Invitations — Cursor project prompt

You are building and reviewing two coordinated WordPress/WooCommerce codebases:

1. The existing **Prikogstreg - PDF modul** (`pdf-plugin`).
2. The new **Prikogstreg Online Invitations** plugin (`prikogstreg-online-invitations`).

The Online Invitations plugin registers WooCommerce product type `online_invitation` and turns a customised purchased design into a customer-owned invitation project managed through WooCommerce My Account.

---

## Required reading before every task

Read in this order:

1. `.cursor/rules.md`
2. `.cursor/agent.md`
3. `online-invitation-integration-audit.md`
4. `online-invitation-integration-contract.json`
5. `docs/technical-plan.md` when it exists
6. Relevant architecture/security/database documents
7. The complete current code for both affected plugins
8. Theme integration files only when the task depends on them

Treat `.cursor/rules.md` as strict requirements and `.cursor/agent.md` as the functional/architectural specification.

Do not code from an assumed file tree. Inspect the real repositories first.

---

## Fixed project identity

```text
Plugin name: Prikogstreg Online Invitations
Plugin slug: prikogstreg-online-invitations
Main file: prikogstreg-online-invitations.php
PHP namespace: PrikOgStreg\OnlineInvitations\
Text domain: prikogstreg-online-invitations
Hook prefix: pks_oi_
CSS prefix: .pks-oi
WooCommerce product type: online_invitation
Product class: WC_Product_Online_Invitation
Project CPT: pks_oi_project
My Account endpoint: online-invitations
Public route base: invitation
```

Do not silently rename these.

---

## Confirmed V1

Build all confirmed V1 features:

- Product-page customisation before purchase through the existing PDF Builder.
- One fixed design per product with dynamic fields.
- Fixed project price and unlimited guests.
- Quantity forced to one.
- Mixed carts.
- Secure account creation/association.
- Idempotent project creation when order is on-hold, processing, or completed.
- Private project CPT plus custom DB tables.
- File-backed builder state and invitation HTML.
- My Account project application.
- Edit, preview, demo-to-self, publish, and share.
- Animated envelope.
- Personal and generic links.
- Guest list.
- Private reusable address book.
- E-mail invitation delivery.
- Sent/failed/opened/responded status.
- RSVP and five-day reminder default.
- Internal wishlist and external Ønskeskyen link.
- Wishlist reservation.
- Guest photo uploads and organiser moderation.
- Admin support, refund restriction, expiration, privacy, and cleanup.

V2 exclusions:

- SMS.
- Phone verification.
- Paid guest capacity.
- Additional-capacity purchases.
- Guest limits.
- Full event microsite.
- Custom domains.
- Collaborator accounts.
- Direct Ønskeskyen sync.
- Direct social publishing.

Do not move a V1 feature to V2.

---

## Architectural boundary

WooCommerce owns products, cart, checkout, customers, orders, payments, and refunds.

Online Invitations owns projects, tables, files, My Account, tokens, guests, RSVP, address book, wishlist, photos, delivery history, scheduling, public routes, and entitlement.

PDF Builder owns template/editor state, field validation, editor rendering/assets, public/preview rendering, schema migration, and PDF generation.

Theme owns styling and documented template overrides only.

---

## PDF Builder integration requirement

The audit proves direct integration is not safe enough.

Implement or use one service discovered through:

```php
$adapter = apply_filters( 'bpp/integration/service', null );
```

The service must implement:

```php
\BPP\Integration\Builder_Adapter_Interface
```

Do not scatter direct `BPP_*` calls throughout the new plugin.

The adapter must support My Account editor context, server validation, schema versioning, public HTML rendering, and context-aware assets.

Online Invitations must validate project ownership before adapter calls.

Project state persistence remains owned by Online Invitations.

---

## Data/storage requirements

Use:

- Private CPT `pks_oi_project`.
- Versioned custom tables defined in `.cursor/agent.md`.
- Private file-backed project storage.
- Custom repository classes.
- Atomic file writes.
- Published snapshot separate from editable state.
- Token hashes rather than raw tokens.
- Action Scheduler rather than a custom queue.
- WooCommerce CRUD/HPOS-safe order access.

Never put raw page HTML, base64 images, or complete builder state in custom tables.

---

## Security baseline

Every write requires explicit actor, resource, authorization, CSRF strategy, validation, and rate limit.

Protect against:

- IDOR.
- Stored XSS from page HTML.
- arbitrary upload.
- path traversal.
- token leakage.
- duplicate bulk e-mails.
- unauthenticated expensive generation abuse.
- SQL injection.
- CSV formula injection.
- wishlist reservation races.
- duplicate project creation.

A nonce does not replace authorization.

Do not expose raw tokens, private paths, builder payloads, or stack traces in logs or responses.

---

## Implementation behavior

Before editing:

1. Inspect relevant code and current hooks.
2. Read the accepted technical plan.
3. State which plugin(s) will change.
4. State the exact data-flow and authorization path.
5. Identify backward-compatibility risks to the existing PDF Builder.
6. Update the plan first when architecture changes.

While editing:

- Make complete changes, not sample snippets.
- Reuse documented services.
- Keep classes single-purpose.
- Use strict comparisons and early returns.
- Add PHPDoc for public hooks, contracts, and array shapes.
- Use translated strings.
- Keep templates presentation-only.
- Preserve current PDF Builder product flow.
- Do not add speculative V2 behavior.
- Do not claim a security control exists until implemented server-side.

After editing:

1. Run relevant Composer validation/autoload commands.
2. Run PHP syntax checks.
3. Run plugin tests.
4. Run npm production build for every plugin whose assets changed.
5. Inspect generated asset paths.
6. Run database/migration tests when schema changed.
7. Review permissions and negative cases.
8. Update documentation.
9. Report actual evidence.

---

## Required completion report

Finish every build task with:

```text
Files changed
Plugin(s) affected
Schema/migrations
Hooks/routes/endpoints
Commands run
Automated tests run
Build result
Manual tests remaining
Security/privacy review
Known limitations
Release blockers
```

Do not state that a command or test passed unless it actually ran and succeeded.

---

## Current-task rule

Execute only the requested prompt from `build-prompts.md`.

Do not skip ahead into later phases unless a small prerequisite is necessary for the current phase and is documented.

Do not create placeholder production methods that silently return success.

When a dependency is missing, fail safely with an admin-visible diagnostic and preserve customer data.
