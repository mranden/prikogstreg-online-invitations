# Prikogstreg Online Invitations — strict Cursor rules

These rules apply to every change made in the Online Invitations workspace.

They are intentionally strict because this project combines WooCommerce commerce, customer-owned projects, public bearer-token links, guest data, file-backed HTML, uploads, e-mail delivery, scheduled jobs, and an existing PDF Builder that was not originally designed as a public integration API.

---

## 1. Instruction priority

Use this order when requirements conflict:

1. The user's latest explicit instruction.
2. This `.cursor/rules.md`.
3. `.cursor/agent.md`.
4. `docs/technical-plan.md` and accepted architecture-decision records.
5. `.cursor/prompt.md`.
6. Existing implementation conventions that do not conflict with the above.

Do not silently resolve a material contradiction. Record it in `docs/technical-plan.md` under **Open decisions** and stop before implementing the affected behavior when the answer changes data ownership, billing, privacy, security, or public URLs.

---

## 2. Fixed plugin identity

Use these identifiers consistently:

| Concern | Required value |
|---|---|
| Plugin name | Prikogstreg Online Invitations |
| Plugin slug/folder | `prikogstreg-online-invitations` |
| Main plugin file | `prikogstreg-online-invitations.php` |
| PHP namespace | `PrikOgStreg\OnlineInvitations\` |
| Text domain | `prikogstreg-online-invitations` |
| Hook/filter prefix | `pks_oi_` |
| PHP global prefix when unavoidable | `pks_oi_` |
| CSS root prefix | `.pks-oi` |
| JavaScript data prefix | `data-pks-oi-*` |
| WooCommerce product type | `online_invitation` |
| Product class | `WC_Product_Online_Invitation` |
| Project CPT | `pks_oi_project` |
| My Account endpoint | `online-invitations` |
| Public route base | `invitation` |
| Database option prefix | `pks_oi_` |
| Database table suffix prefix | `pks_oi_` |

Do not rename any identifier partially. A rename must update PHP, Composer, JavaScript, CSS, templates, database schema, migrations, hooks, routes, translation strings, tests, documentation, and release notes.

---

## 3. Workspace and source-of-truth files

Before changing code, locate and read:

- `.cursor/rules.md`
- `.cursor/agent.md`
- `.cursor/prompt.md`
- `build-prompts.md`
- `docs/technical-plan.md` when it exists
- `online-invitation-integration-audit.md`
- `online-invitation-integration-contract.json`
- The complete `pdf-plugin` repository
- The complete `prikogstreg-online-invitations` repository
- Relevant theme integrations only when a prompt explicitly requires them

Do not infer the PDF Builder contract from class names alone. The audit confirms that the existing builder has no formal public API, is coupled to `is_product()`, stores browser-generated HTML plus field JSON, and cannot currently embed safely in My Account or render a public invitation through a stable server API.

Do not modify the theme to carry business logic. Theme work may only style or override documented plugin templates.

---

## 4. Confirmed V1 scope — must include

V1 includes all of the following:

1. WooCommerce product type `online_invitation`.
2. One fixed invitation design per product with builder-defined dynamic fields.
3. Product-page customisation before purchase using the existing PDF Builder flow.
4. Quantity disabled and forced to one.
5. Fixed project price with unlimited guests.
6. Mixed carts with physical and normal WooCommerce products.
7. Account creation or account association during checkout.
8. Project creation for qualifying order items when the order is `on-hold`, `processing`, or `completed`.
9. Idempotent project creation: exactly one project per qualifying order item.
10. Private project CPT plus custom database tables.
11. File-backed editable builder state and HTML; large HTML/state blobs must not be stored in custom tables.
12. My Account project dashboard and project-management screens.
13. Reopening and editing the purchased design after checkout.
14. Event details and RSVP deadline.
15. Preview and send-a-demo-to-self.
16. Publish/unpublish.
17. Animated envelope public experience with a fixed product-selected envelope and generic background.
18. Personal guest links and a generic social-sharing link.
19. Guest management with unlimited guests.
20. Reusable private address book owned by the customer.
21. Guest invitation delivery by e-mail.
22. Sent, failed, opened, and responded status.
23. RSVP and organiser response overview.
24. Reminder e-mail five days before the RSVP deadline by default.
25. Internal wishlist items and an optional external Ønskeskyen URL.
26. Guest wishlist reservations.
27. Guest photo uploads.
28. Admin support tools.
29. Full-refund/cancellation entitlement handling.
30. Expiration 90 days after the event with an administrative override.
31. Privacy, export, erasure, cleanup, security, accessibility, and release documentation.

Do not treat address book, photo uploads, wishlist, RSVP, reminders, tracking, or guest management as optional V2 items.

---

## 5. Explicit V2 exclusions

Do not implement or pre-build these features in V1:

- SMS delivery.
- Phone-number verification.
- Sender-name verification through SMS.
- Paid guest-capacity tiers.
- Per-guest billing.
- Purchasing additional guest capacity.
- Guest limits.
- Custom domains.
- Full event microsite/page-builder functionality.
- Multiple organiser accounts or collaborator permissions.
- Direct integrations with Ønskeskyen beyond storing and displaying a validated external URL.
- Native mobile applications.
- Social-network APIs or direct social publishing.
- Artificially complex package/subscription systems.

V1 guest capacity is unlimited. Do not add hidden limits, counters that block sending, or capacity-upgrade UI.

Schema may contain a nullable future-safe capacity column only when it does not alter V1 behavior and is justified in the technical plan. Do not expose it in V1.

---

## 6. Product and experience boundaries

The product is an animated digital invitation, not a general event-site builder.

The public experience may contain:

- Envelope animation.
- Invitation/poster HTML.
- Event details.
- RSVP.
- Wishlist.
- Approved photo-upload interaction or gallery behavior defined by the project settings.

Do not add arbitrary content sections, page builders, blogs, accommodation modules, seating plans, custom domains, or broad wedding-site features.

The product's envelope and base background are selected by the product configuration. Customers do not receive an envelope/background design editor in V1.

---

## 7. Responsibility boundaries

### WooCommerce owns

- Products and prices.
- Cart and checkout.
- Customers and account association.
- Orders and order-item identity.
- Payments, refunds, and order status.
- Standard order e-mails.

### Online Invitations owns

- Product-type registration and behavior.
- Project identity and entitlement.
- Project CPT and custom tables.
- Project files and state versions.
- My Account endpoints and permissions.
- Guest records and address book.
- Public tokens and public routes.
- RSVP, wishlist, reservations, photos, delivery history, and event logs.
- Invitation-specific e-mails and scheduled jobs.
- Expiration and refund restrictions.
- Public envelope experience.

### PDF Builder owns

- Product template data.
- Dynamic editable field definitions.
- Product-page editor behavior.
- Canonical builder-state validation/normalization.
- Context-aware editor rendering and assets.
- Preview/public HTML generation from builder state.
- Builder schema versioning and migrations.
- PDF generation where requested.

### Theme owns

- Visual styling.
- WooCommerce login, registration, checkout, and My Account layout.
- Documented template overrides.

Never move authorization, database writes, project creation, token validation, e-mail scheduling, or guest logic into the theme.

---

## 8. PDF Builder integration rules

1. Do not couple the new plugin to undocumented PDF Builder internals throughout the codebase.
2. Integrate through one adapter service discovered through:
   `apply_filters( 'bpp/integration/service', null )`.
3. The PDF Builder must expose `BPP\Integration\Builder_Adapter_Interface`.
4. Preserve the existing PDF Builder product and cart flow.
5. New PDF Builder integration code must be backward-compatible unless a prompt explicitly authorizes a breaking migration.
6. The adapter must work outside `is_product()`.
7. Editor assets must be enqueueable for a My Account project context.
8. Public-view assets must be separate from editor-only assets where practical.
9. Online Invitations validates project ownership before calling the adapter.
10. The PDF Builder must not attempt to authorize an Online Invitation project based on browser-supplied IDs.
11. Never trust browser-supplied `product_id`, `project_id`, `order_id`, `order_item_id`, template ID, size, or format.
12. Validate state server-side. Client-side required-field validation is not sufficient.
13. Public HTML must be generated or sanitized through the adapter.
14. Do not echo raw `page[]` HTML directly on a logged-out route.
15. Every adapter failure must return a `WP_Error` or a documented typed exception; never expose filesystem paths or stack traces.
16. Add stable PHP hooks and JavaScript events documented in the integration audit.
17. Do not create a second unrelated integration API in the Online Invitations plugin.

Required adapter methods are defined in `.cursor/agent.md` and the integration contract. Any justified change to that contract must be documented before implementation.

---

## 9. Existing PDF Builder security rules

The audit identifies unauthenticated AJAX handlers without adequate nonce, capability, or ownership checks.

When touching those endpoints:

- Add nonce verification.
- Validate request context and product eligibility.
- Require explicit capabilities for admin/order operations.
- Retain unauthenticated access only where the pre-purchase product customizer genuinely needs it.
- Apply request-size limits.
- Apply file-count, image-size, and MIME limits.
- Apply rate limiting to expensive unauthenticated generation.
- Never allow an order item to be read or modified from only an item ID.
- Do not break the current product-page customizer while hardening it.
- Add regression tests for both authorized and rejected requests.

Never remove a `nopriv` action blindly. Determine whether guest checkout/customisation needs it, then protect it appropriately.

---

## 10. WooCommerce product-type rules

`online_invitation` must:

- Extend the simplest appropriate WooCommerce product class.
- Be virtual.
- Be sold individually.
- Disable quantity controls in product, cart, Store API, and server validation.
- Use fixed V1 pricing from the WooCommerce product.
- Support mixed carts.
- Remain compatible with WooCommerce CRUD and HPOS.
- Store product configuration with WooCommerce product CRUD/meta APIs.
- Identify the builder template through the product ID in V1 unless the technical plan proves a separate template ID is needed.
- Store envelope preset and background preset references as validated product configuration.
- Prevent publication or purchase when required builder configuration is missing.
- Show clear admin validation errors.

Do not use stock quantity as guest capacity.

---

## 11. Cart, checkout, account, and order rules

1. Preserve the pre-purchase builder payload from product page to cart and order item.
2. Never rely on the thank-you page to create projects.
3. Project creation must run from order-status/lifecycle hooks and be idempotent.
4. Qualifying statuses are `on-hold`, `processing`, and `completed`.
5. Store the created project ID on the order item through WooCommerce CRUD.
6. Use a unique database constraint on `order_item_id` in the project table.
7. Mixed carts must create projects only for `online_invitation` items.
8. Existing customers are associated by authenticated user or verified billing e-mail through WooCommerce account logic.
9. New customers receive a secure password-setup flow; never e-mail a plaintext password.
10. Invitation-specific welcome e-mail is sent only after the project exists.
11. Welcome e-mail must contain a direct signed or authenticated My Account project URL.
12. Re-running the same status transition must not create duplicate projects or duplicate welcome e-mails.
13. Full refunds disable publish/send actions but preserve data.
14. Partial refunds do not disable the project unless the invitation line item is fully refunded.
15. Cancelled/failed orders do not create new projects; an existing project is restricted according to the entitlement policy.
16. Manual admin override must be auditable.

Use WooCommerce order and order-item CRUD. Do not query `wp_posts`/`wp_postmeta` for orders.

---

## 12. CPT and custom-table rules

Register private CPT `pks_oi_project` as the WordPress/admin shell.

CPT rules:

- `public` false.
- `publicly_queryable` false.
- `exclude_from_search` true.
- `show_ui` true.
- No public single/archive templates.
- Custom capabilities.
- No customer project data in the post content.
- Post title may be a generated admin label.
- Domain status lives in the project table, not in conflicting post status values.
- Deleting a CPT must route through the domain deletion service.

Required custom tables:

1. `{$wpdb->prefix}pks_oi_projects`
2. `{$wpdb->prefix}pks_oi_guests`
3. `{$wpdb->prefix}pks_oi_address_book`
4. `{$wpdb->prefix}pks_oi_wishlist_items`
5. `{$wpdb->prefix}pks_oi_wishlist_reservations`
6. `{$wpdb->prefix}pks_oi_photos`
7. `{$wpdb->prefix}pks_oi_deliveries`
8. `{$wpdb->prefix}pks_oi_events`

Rules:

- All table access goes through repository classes.
- Use `$wpdb->prepare()` for dynamic SQL.
- Never concatenate untrusted values into SQL.
- Use `dbDelta()` only with reviewed schema SQL.
- Maintain a schema version option and ordered migrations.
- Migrations must be idempotent and restart-safe.
- Add required unique keys and indexes.
- Store UTC timestamps.
- Convert to site/user timezone only for display.
- Do not store raw HTML, base64 images, or large JSON blobs in tables.
- Do not create a separate custom users table.
- Do not create a custom job queue; use Action Scheduler.

---

## 13. Project file-storage rules

Project files are first-class domain data.

Required principles:

- Store editable builder state and HTML in files, not database blobs.
- Store only relative paths, hashes, versions, and metadata in tables.
- Use a configurable private storage root.
- Prefer a path outside the directly public uploads tree.
- Provide a documented protected fallback when private storage outside web root is unavailable.
- Never trust or serve a path from a request.
- Resolve every file path from a project record and controlled basename.
- Prevent directory traversal.
- Use random project storage IDs, not customer e-mails or names.
- Write atomically: temporary file, flush, checksum, rename.
- Use file locking for concurrent writes.
- Maintain `state_version` for optimistic concurrency.
- Preserve at least the immediately previous valid state until the new write is verified.
- Store a sanitized published snapshot separately from editable raw state.
- Public requests may read only the published snapshot.
- Never serve editable state, raw field JSON, or unpublished HTML directly.
- Validate UTF-8 and maximum file sizes.
- Keep a manifest with schema version, page order, hashes, and creation time.
- Cleanup must be retryable and logged.

Suggested project layout is defined in `.cursor/agent.md`. Do not invent parallel storage layouts per feature.

---

## 14. Project lifecycle rules

Domain statuses must be explicit and centrally defined.

Minimum project states:

- `draft`
- `active`
- `restricted`
- `expired`
- `archived`
- `deleted`

Minimum publication states:

- `unpublished`
- `published`

Rules:

- A project is created as `draft`.
- It may be edited before publication.
- Publication requires valid builder state and event configuration.
- Full refund/cancellation entitlement changes move it to `restricted`.
- Restricted projects remain visible to admins and owner, but cannot publish, send, or accept new public activity.
- Expiration occurs 90 days after the event end/date.
- Admin may set an override expiry.
- Expiration jobs are idempotent.
- Public routes return a privacy-safe unavailable response for restricted, expired, archived, or unpublished projects.
- Restore/unrestrict actions are audited.
- Do not permanently delete automatically at expiration.
- Hard deletion requires an explicit administrative/user erasure flow and cleanup confirmation.

---

## 15. My Account rules

All customer-facing project management is implemented by the plugin and rendered inside WooCommerce My Account.

Required screens:

- Project list.
- Project overview.
- Design editor.
- Event details.
- Guests.
- Address book.
- Preview.
- Publish/share/send.
- Responses.
- Wishlist.
- Guest photos.
- Settings/archive.

Rules:

- Verify logged-in ownership for every read and write.
- Do not authorize from URL project ID alone.
- Use nonces for every state-changing form.
- Apply POST/Redirect/GET for ordinary forms.
- Use authenticated AJAX/REST only where asynchronous UI is materially useful.
- Return consistent JSON errors.
- Do not expose another customer's existence through error wording.
- Use theme-overridable templates.
- Plugin templates must remain functional without theme overrides.
- Direct links from e-mails must resolve to the correct project after login.
- Avoid creating a separate WordPress page per project.

---

## 16. Public invitation and token rules

1. Public access uses high-entropy opaque bearer tokens.
2. Store token hashes, never raw personal guest tokens.
3. Do not expose project, post, user, order, or guest IDs in public URLs.
4. Support:
   - Generic project/social link.
   - Personal guest link.
5. Personal link resolves guest name and RSVP state.
6. Generic link does not impersonate a named guest.
7. Generic RSVP must create or resolve a guest through the documented generic-response flow.
8. Tokens must be rotatable and revocable.
9. Use constant-time hash comparison where applicable.
10. Apply rate limits to public form submissions and uploads.
11. Public pages must not leak organiser/customer e-mail, order data, address-book data, or other guests.
12. Open tracking records the link load, not proof that a human read the invitation.
13. Bot/prefetch handling must be considered and documented.
14. Public invitation HTML comes only from the published sanitized snapshot.
15. Public pages must remain usable without animation.
16. Respect `prefers-reduced-motion`.
17. Use `noindex` by default unless explicitly changed.

---

## 17. Guest-management rules

V1 guests are unlimited.

Each guest record may contain only documented fields such as:

- Display name.
- E-mail.
- Optional phone field for organiser reference only; no SMS in V1.
- Party/household label.
- Attendee count.
- RSVP status.
- Comment/dietary text when enabled.
- Personal token hash.
- Invitation and response timestamps.
- Address-book source reference.
- Soft-delete/archive state.

Rules:

- No guest account is created.
- Guest e-mail is optional when a copyable personal link is used.
- Personal token is generated independently of e-mail.
- Importing from address book copies selected values; deleting an address-book entry must not corrupt historical guest data.
- Guest list operations must be scoped by project ownership.
- Bulk operations need confirmation, nonce, validation, and result summaries.
- CSV export must neutralize spreadsheet formula injection.
- CSV import must have field mapping, validation, duplicate review, and row limits.
- Never reveal the full guest list on the public invitation.

---

## 18. Reusable address-book rules

The address book is private and internal to one WordPress customer.

Rules:

- Store entries in `pks_oi_address_book`.
- Scope every query by `user_id`.
- Support create, edit, archive/delete, search, and selection into a project.
- Support importing guests into the address book only after explicit user action.
- Deduplicate cautiously using normalized e-mail plus owner; names alone are not unique.
- Never automatically merge records with conflicting details.
- Address-book deletion does not erase already-created project guest snapshots.
- Address-book data is included in privacy export/erasure.
- No admin-wide marketing use.
- No global/shared contacts.

---

## 19. RSVP rules

Minimum V1 response:

- Attending.
- Not attending.
- Optional attendee count.
- Optional comment/dietary information when enabled.

Rules:

- Guest may change response until the RSVP deadline.
- Deadline is stored in UTC and displayed in project timezone.
- Organiser receives a documented notification policy.
- Every change is auditable in `pks_oi_events`.
- Public response uses guest token authorization plus CSRF/replay protections appropriate to bearer-token pages.
- Generic-link RSVP follows a separate safe flow and must not overwrite a named guest.
- Expired/restricted projects reject new responses.
- Reminder default is five days before the RSVP deadline.
- Reminder scheduling must update when deadline or guest data changes.
- Do not send reminders to guests who already responded unless explicitly configured.
- Do not claim delivery based solely on queued status.

---

## 20. Wishlist rules

V1 supports both:

1. An optional validated external Ønskeskyen URL.
2. Internal wishlist items.

Internal items may contain:

- Title.
- Description.
- Optional URL.
- Optional image reference.
- Quantity requested.
- Sort order.
- Active state.
- Reservation state.

Rules:

- Validate external URLs and allow only safe HTTP/HTTPS schemes.
- A guest may reserve or release an available item through their invitation token.
- Use atomic reservation logic to prevent double reservation.
- Do not expose one guest's identity to another.
- Support a project setting controlling whether organiser can see reservation identity.
- Default to preserving gift-surprise privacy.
- Log reservation changes.
- Do not scrape or synchronize Ønskeskyen in V1.

---

## 21. Guest photo-upload rules

Guest uploads are untrusted public uploads.

Rules:

- Require a valid active project/guest or generic upload token.
- Use signed, expiring upload intent plus rate limiting.
- Allow only reviewed image MIME types.
- Verify MIME from file contents, not filename.
- Enforce file count, per-file size, total project storage, and pixel-dimension limits.
- Reject SVG and executable/polyglot formats.
- Generate random server filenames.
- Strip EXIF metadata where supported.
- Re-encode images when practical.
- Store files outside direct public access or serve through an authorization controller.
- Store metadata in `pks_oi_photos`.
- Default moderation state is `pending`.
- Organiser can approve, reject, download, and delete.
- Do not publish uploads to a public gallery automatically.
- Never create public WordPress attachment pages by default.
- Clean abandoned temporary uploads.
- Log upload and moderation events.
- Include photos in privacy export/erasure policy.

---

## 22. Delivery, e-mail, and scheduler rules

Use WooCommerce e-mail classes for customer/project e-mails where they fit the WooCommerce e-mail settings model.

Required e-mail types:

- Project welcome.
- Demo invitation to owner.
- Guest invitation.
- RSVP reminder.
- RSVP confirmation.
- New/changed RSVP notification to organiser.
- Optional photo-upload notification based on settings.

Rules:

- Use Action Scheduler for bulk sends, reminders, expiration, and cleanup.
- One scheduled action must have an idempotency key.
- Store each delivery attempt in `pks_oi_deliveries`.
- Distinguish queued, processing, sent-to-mailer, failed, cancelled, and skipped.
- Do not equate mailer acceptance with inbox delivery.
- Retry transient failures with bounded backoff.
- Never retry permanent validation failures indefinitely.
- Avoid duplicate sends during page refreshes or job retries.
- Validate recipients and headers.
- Do not permit arbitrary From headers from customer input.
- Include direct project links in owner-facing e-mails.
- Include personal token URLs only in the intended guest e-mail.
- Redact sensitive token values from logs.

---

## 23. Security and authorization rules

For every operation, explicitly answer:

- Who is the actor?
- What resource is being accessed?
- What capability, ownership, entitlement, or token authorizes it?
- Is it read or write?
- Is CSRF protection required?
- What is the rate limit?
- What data is logged?

Mandatory controls:

- Nonces for authenticated state changes.
- Custom capabilities for admin support actions.
- Ownership checks for customer project operations.
- Entitlement checks against project status.
- Token verification for public guest operations.
- Prepared SQL.
- Contextual escaping.
- Strict allowlists.
- Safe redirects.
- Upload validation.
- Request-body limits.
- No sensitive data in query strings when avoidable.
- No raw exception messages to visitors.
- No IDOR through project/guest/order IDs.
- No mass assignment from request arrays.
- No arbitrary class, method, hook, file, template, or path selection from user input.

Do not add security theatre. A nonce does not replace authorization, and a hidden field does not make an ID trustworthy.

---

## 24. HTML and output-sanitization rules

Editable builder state and published public HTML are different trust domains.

- Editable raw state may be retained in private storage for re-editing.
- Published HTML must be created through the adapter and sanitized with a builder-specific allowlist.
- Do not use unrestricted `wp_kses_post()` as proof that complex builder HTML is safe; define reviewed tags, attributes, URL protocols, data attributes, and style properties.
- Strip scripts, event attributes, iframes, forms, object/embed, dangerous URLs, CSS expressions, imports, and external resource injection not explicitly allowed.
- Never execute HTML from editable state in wp-admin or My Account without the same controlled rendering contract.
- Escape ordinary text by context.
- Pre-approved SVGs must be fixed assets or sanitized allowlisted markup.
- Do not allow arbitrary HTML in event details, guest comments, wishlist items, or address-book fields.
- Public CSS must be scoped to `.pks-oi`.

---

## 25. Privacy and data-retention rules

Treat organiser, address-book, guest, RSVP, wishlist reservation, and photo data as personal data.

Required:

- WordPress privacy-policy suggested text.
- Personal-data exporter and eraser integration.
- Customer-facing project archive/delete request.
- Admin hard-delete workflow.
- Retention matrix documented by data category.
- Expired projects are restricted, not immediately deleted.
- Full deletion removes DB rows, project files, photos, tokens, scheduled actions, and generated derivatives.
- Deletion is idempotent and produces an admin-visible result.
- E-mail/event logs retain only necessary metadata.
- Do not store IP addresses by default; when rate limiting needs an identifier, minimize and hash/expire it.
- Never reuse address-book or guest data for marketing without separate consent.
- Logs must not contain invitation tokens, raw uploaded file contents, or complete builder payloads.

---

## 26. Accessibility and frontend rules

- Semantic HTML.
- Keyboard-accessible controls.
- Visible focus indicators.
- Correct labels, descriptions, errors, and status announcements.
- No interaction that requires hover.
- Minimum comfortable touch targets.
- Reduced-motion fallback for envelope animation.
- Public invitation remains readable when JavaScript fails.
- RSVP and upload forms remain understandable with assistive technology.
- Progress and upload states use `aria-live` appropriately.
- Color is not the only status indicator.
- Do not trap focus unnecessarily.
- Modal behavior, when used, must have correct focus management.
- Theme overrides must not be required for basic accessibility.

---

## 27. Assets and JavaScript rules

- Keep editor assets owned by the PDF Builder adapter.
- Keep Online Invitation dashboard/public assets owned by this plugin.
- Do not enqueue editor bundles on every frontend page.
- Do not enqueue public invitation assets in wp-admin.
- Use one documented build pipeline per plugin; do not replace the existing PDF Builder Webpack build merely for preference.
- New plugin may use a proportionate npm pipeline documented in the technical plan.
- Avoid a frontend framework unless existing repository evidence makes it necessary.
- Do not add jQuery to new Online Invitation code.
- Existing PDF Builder jQuery may remain inside its adapter/editor context.
- Avoid global JavaScript variables.
- Use namespaced custom events documented in the integration contract.
- Do not inject untrusted HTML with `innerHTML`.
- Compiled production assets must be included in release packages.

---

## 28. Database and migration rules

- Schema version is stored in `pks_oi_db_version`.
- Code version and schema version are separate.
- Activation installs current schema.
- Normal bootstrap runs pending migrations safely.
- Never perform heavy full-table migration on every request.
- Use batched background migrations for large data.
- Add a migration lock with expiry.
- A failed migration must be visible to admins and retryable.
- Never drop columns or tables in a routine update without an explicit destructive migration and backup guidance.
- Uninstall must not delete customer projects by default.
- Destructive uninstall requires an explicit constant or admin-confirmed setting documented before implementation.

---

## 29. Code architecture rules

- Use Composer PSR-4 for the new plugin.
- Main plugin file is thin bootstrap glue.
- Use a root `Plugin` orchestrator without a general-purpose service container.
- Constructor injection is preferred for explicit dependencies.
- Separate domain services, repositories, controllers, WooCommerce integration, builder integration, e-mails, scheduling, templates, and infrastructure.
- Do not create god classes.
- Do not use static mutable global state for domain logic.
- Keep WordPress hook registration centralized by feature.
- Use value objects/enums only where they reduce ambiguity.
- Do not create speculative abstractions.
- Use strict comparisons.
- Prefer early returns.
- Add PHPDoc for hooks, array shapes, public integration contracts, and non-obvious behavior.
- Never call repositories directly from templates.
- Templates contain presentation and minimal conditionals only.
- Translation-ready visible strings use the fixed text domain.

---

## 30. Performance and reliability rules

- Paginate guest, address-book, photo, delivery, and event lists.
- Avoid N+1 queries.
- Add indexes for owner, project, token hash, status, e-mail, scheduled dates, and order item.
- Do not load full builder HTML/state for project list screens.
- Stream downloads rather than loading large files into memory when practical.
- Queue bulk e-mails and image processing.
- Bound upload and HTML/state sizes.
- Cache read-only project summaries only with clear invalidation.
- Use atomic reservation and state-update operations.
- Record actionable failures without logging sensitive payloads.
- Every background job must be safe to run twice.

---

## 31. Testing rules

Automated tests must cover:

- Product type.
- Quantity enforcement.
- Mixed carts.
- Account/project lifecycle.
- Project idempotency.
- HPOS-safe order access.
- CPT/table installation and migrations.
- Repository CRUD and constraints.
- File storage atomicity/path safety.
- Builder adapter discovery and failure handling.
- Project ownership and admin capabilities.
- Public token hashing/revocation.
- HTML sanitization.
- Guest/address-book isolation.
- RSVP changes/deadlines.
- Wishlist reservation race behavior.
- Photo validation and authorization.
- Delivery idempotency and reminder scheduling.
- Refund restriction and expiration.
- Privacy export/erasure.
- Accessibility-critical markup where testable.

End-to-end/manual tests must cover:

- Product customisation before purchase.
- Classic checkout and the site's actual checkout implementation.
- Project appears in My Account.
- Reopen/edit/save/preview/publish.
- Envelope animation and reduced motion.
- Personal and generic links.
- Guest e-mail, opened status, RSVP, reminder.
- Wishlist reservation.
- Photo upload/moderation.
- Mobile and keyboard operation.
- Full refund restriction.
- Expiration override.

Do not claim a test passed unless it ran successfully.

---

## 32. Documentation and completion report

Every implementation prompt must update the relevant documentation and finish with:

- Files changed.
- Database migrations added.
- Hooks/routes/endpoints added or changed.
- Commands actually run.
- Automated tests actually run.
- Build result.
- Manual tests still required.
- Security/privacy considerations.
- Known limitations.
- Release blockers.

Do not say “production ready” while manual release blockers remain.

---

## 33. Scope-control rule

When a request is ambiguous:

- Preserve the confirmed V1 scope.
- Do not move a confirmed V1 feature to V2.
- Do not pull an explicit V2 feature into V1.
- Implement the smallest complete architecture that supports the confirmed behavior.
- Document assumptions.
- Ask only when the decision changes billing, public privacy, irreversible storage, or ownership.

Do not pre-build future paid capacity, SMS, or full microsite functionality “just in case”.
