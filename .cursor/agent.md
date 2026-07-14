# Prikogstreg Online Invitations — AI agent handbook

Use this file as the primary architectural and functional specification for the **Prikogstreg Online Invitations** WooCommerce plugin and its required PDF Builder integration work.

The goal is not to clone every feature or implementation detail from Invidoo. The goal is to build a production-quality WooCommerce invitation platform around Prik & Streg's existing product customizer and stored invitation HTML.

---

## 1. Product definition

A customer selects a WooCommerce product whose product type is `online_invitation`.

Each product represents one fixed invitation design with a fixed envelope preset, a generic background preset, and a controlled set of dynamic builder fields such as names, dates, event text, and images.

The customer customises the invitation on the product page before purchase through the existing PDF Builder.

After checkout and a qualifying order status, the system creates a private invitation project for the customer. The customer manages everything after purchase inside WooCommerce My Account:

- Design editing.
- Event details.
- Preview.
- Demo to self.
- Guest list.
- Reusable address book.
- E-mail invitations and personal links.
- RSVP and response overview.
- Wishlist.
- Guest photo uploads.
- Publication and project settings.

Guests do not receive WordPress accounts. They use opaque personal or generic public links.

The public experience is an animated envelope that reveals the invitation HTML, followed by the enabled interaction sections. It is not a broad event microsite builder.

---

## 2. Confirmed decisions

| Decision | V1 behavior |
|---|---|
| Product type | `online_invitation` |
| Plugin slug | `prikogstreg-online-invitations` |
| Customisation timing | Before purchase on product page |
| Product/design relation | One fixed design per WooCommerce product |
| Dynamic content | Builder-defined editable fields |
| Price | Fixed project price |
| Guest capacity | Unlimited |
| Quantity | Disabled and server-forced to one |
| Mixed cart | Supported |
| Project creation statuses | `on-hold`, `processing`, `completed` |
| Refund behavior | Full invitation-line refund restricts publish/send; data retained |
| Active lifetime | Until 90 days after event, unless admin override |
| Customer portal | WooCommerce My Account |
| Public product | Animated digital invitation |
| SMS | V2 |
| Phone verification | V2 |
| Paid additional capacity | V2 |
| Wishlist | V1: internal items plus optional Ønskeskyen link |
| Address book | V1, private to customer |
| Guest photo uploads | V1 |
| Storage | Private CPT shell + custom tables + file-backed HTML/state |

---

## 3. Existing PDF Builder facts that govern the architecture

The existing **Prikogstreg - PDF modul**:

- Uses WooCommerce products as template owners.
- Stores the template in product meta `_bpp_product`.
- Uses global `BPP_` classes rather than its Composer namespace for current code.
- Has Composer PSR-4 `BPP\` mapped to `src/`, which can be used for new integration classes.
- Renders a DOM-based editor on single product pages.
- Enqueues its editor only when `is_product()` and the product is active.
- Stores customer field JSON and complete browser-generated page HTML through cart data.
- Persists the order-item payload in filesystem `.text` files.
- Generates PDF from html2canvas PNGs and mPDF.
- Has no formal public integration API.
- Has no project identifier or My Account editor mode.
- Has no stable public HTML renderer.
- Has several AJAX endpoints that require hardening.
- Has external theme dependencies that must be located or guarded.

The new plugin therefore cannot safely scatter direct calls to `BPP_Product`, `BPP_Order_Item_Storage`, or `BPP_PDF_Plugin` throughout its services.

A formal adapter layer is a prerequisite.

---

## 4. High-level system boundaries

```text
Customer browser
    |
    | product-page customisation before purchase
    v
WooCommerce + PDF Builder
    |
    | cart item + order item builder payload
    v
Prikogstreg Online Invitations
    |
    | qualifying order status creates project
    | imports/copies builder state into project-owned private files
    v
My Account project application
    |
    | editor through PDF Builder adapter
    | guests / RSVP / wishlist / photos / e-mail
    v
Published snapshot
    |
    | opaque guest or generic token
    v
Public animated invitation
```

### Key ownership rule

The order item is the purchase source and audit reference.

The invitation project is the long-term editable domain object.

The project must not remain dependent on the order item's payload file for normal runtime editing after a successful import. Keep order references, but copy/import the canonical state into project-owned storage.

---

## 5. Required repository/workspace layout

The Cursor workspace should contain or expose both plugin repositories:

```text
workspace/
├── .cursor/
│   ├── agent.md
│   ├── prompt.md
│   └── rules.md
├── build-prompts.md
├── online-invitation-integration-audit.md
├── online-invitation-integration-contract.json
├── pdf-plugin/
└── prikogstreg-online-invitations/
```

The exact parent directory may differ. Prompts must locate repositories by main plugin files and plugin identity rather than assuming an absolute machine path.

Do not place the Online Invitations plugin inside the PDF Builder plugin.

---

## 6. Recommended Online Invitations file tree

This is the target direction. Cursor may refine filenames in `docs/technical-plan.md`, but responsibilities must remain separated.

```text
prikogstreg-online-invitations/
├── prikogstreg-online-invitations.php
├── composer.json
├── package.json
├── readme.txt
├── uninstall.php
├── languages/
├── src/
│   ├── Plugin.php
│   ├── Bootstrap/
│   │   ├── Requirements.php
│   │   ├── Activation.php
│   │   └── Deactivation.php
│   ├── Admin/
│   │   ├── ProjectPostType.php
│   │   ├── ProjectColumns.php
│   │   ├── ProjectSupportScreen.php
│   │   └── Notices.php
│   ├── Builder/
│   │   ├── BuilderService.php
│   │   ├── BuilderUnavailable.php
│   │   ├── BuilderContextFactory.php
│   │   └── PublishedHtmlSanitizer.php
│   ├── Database/
│   │   ├── Schema.php
│   │   ├── Migrator.php
│   │   ├── MigrationLock.php
│   │   └── Repositories/
│   │       ├── ProjectRepository.php
│   │       ├── GuestRepository.php
│   │       ├── AddressBookRepository.php
│   │       ├── WishlistRepository.php
│   │       ├── WishlistReservationRepository.php
│   │       ├── PhotoRepository.php
│   │       ├── DeliveryRepository.php
│   │       └── EventRepository.php
│   ├── Domain/
│   │   ├── Project/
│   │   │   ├── Project.php
│   │   │   ├── ProjectService.php
│   │   │   ├── ProjectStatus.php
│   │   │   ├── PublicationStatus.php
│   │   │   ├── ProjectFactory.php
│   │   │   ├── ProjectEntitlement.php
│   │   │   └── ProjectExpiration.php
│   │   ├── Guest/
│   │   │   ├── Guest.php
│   │   │   ├── GuestService.php
│   │   │   ├── GuestTokenService.php
│   │   │   ├── GuestImport.php
│   │   │   └── GuestCsv.php
│   │   ├── AddressBook/
│   │   ├── Rsvp/
│   │   ├── Wishlist/
│   │   ├── Photos/
│   │   └── Delivery/
│   ├── Files/
│   │   ├── ProjectStorage.php
│   │   ├── ProjectManifest.php
│   │   ├── AtomicFileWriter.php
│   │   ├── StoragePath.php
│   │   └── StreamResponse.php
│   ├── MyAccount/
│   │   ├── Endpoints.php
│   │   ├── Router.php
│   │   ├── ProjectController.php
│   │   ├── GuestController.php
│   │   ├── AddressBookController.php
│   │   ├── WishlistController.php
│   │   ├── PhotoController.php
│   │   └── Templates.php
│   ├── PublicInvitation/
│   │   ├── RewriteRules.php
│   │   ├── PublicController.php
│   │   ├── TokenResolver.php
│   │   ├── OpenTracker.php
│   │   ├── RsvpController.php
│   │   ├── WishlistController.php
│   │   └── PhotoUploadController.php
│   ├── WooCommerce/
│   │   ├── ProductType/
│   │   │   ├── ProductType.php
│   │   │   ├── ProductClass.php
│   │   │   ├── ProductData.php
│   │   │   └── QuantityGuard.php
│   │   ├── Cart/
│   │   │   └── InvitationCart.php
│   │   ├── Checkout/
│   │   │   └── AccountRequirement.php
│   │   ├── Orders/
│   │   │   ├── ProjectOrderListener.php
│   │   │   ├── ProjectCreationLock.php
│   │   │   └── RefundListener.php
│   │   └── Emails/
│   │       ├── EmailRegistry.php
│   │       ├── ProjectWelcomeEmail.php
│   │       ├── DemoInvitationEmail.php
│   │       ├── GuestInvitationEmail.php
│   │       ├── RsvpReminderEmail.php
│   │       ├── RsvpConfirmationEmail.php
│   │       └── OrganizerRsvpEmail.php
│   ├── Scheduling/
│   │   ├── Scheduler.php
│   │   ├── SendInvitationAction.php
│   │   ├── SendReminderAction.php
│   │   ├── ExpireProjectAction.php
│   │   ├── CleanupAction.php
│   │   └── ProcessPhotoAction.php
│   ├── Privacy/
│   │   ├── Policy.php
│   │   ├── Exporter.php
│   │   ├── Eraser.php
│   │   └── Retention.php
│   ├── Security/
│   │   ├── Authorization.php
│   │   ├── RateLimiter.php
│   │   ├── SignedIntent.php
│   │   └── RequestValidator.php
│   └── Support/
│       ├── Clock.php
│       ├── Uuid.php
│       ├── Url.php
│       └── Logger.php
├── templates/
│   ├── myaccount/
│   ├── public/
│   ├── emails/
│   └── admin/
├── assets/
│   ├── src/
│   │   ├── js/
│   │   └── scss/
│   └── build/
├── tests/
│   ├── Unit/
│   ├── Integration/
│   ├── Fixtures/
│   └── E2E/
└── docs/
    ├── technical-plan.md
    ├── architecture-decisions.md
    ├── database-schema.md
    ├── builder-integration.md
    ├── security-review.md
    ├── privacy-retention.md
    ├── test-plan.md
    └── production-review.md
```

Keep the architecture proportionate. A class may combine closely related responsibilities when the technical plan proves separation adds no value. Do not collapse the whole plugin into a few god classes.

---

## 7. PDF Builder integration target

Add new namespaced integration classes under the existing `pdf-plugin/src/Integration/` path. The existing Composer autoloader is already required by the PDF Builder bootstrap and maps `BPP\` to `src/`.

Recommended structure:

```text
pdf-plugin/
└── src/
    └── Integration/
        ├── Builder_Adapter_Interface.php
        ├── Online_Invitation_Builder_Adapter.php
        ├── Builder_Context.php
        ├── State_Validator.php
        ├── Public_Html_Renderer.php
        └── Integration_Provider.php
```

### Service discovery

```php
$adapter = apply_filters( 'bpp/integration/service', null );

if ( ! $adapter instanceof \BPP\Integration\Builder_Adapter_Interface ) {
    // Show a controlled dependency error.
}
```

Register the service once from the PDF Builder bootstrap/provider.

### Required interface

The implementation must match the supplied integration contract unless `docs/architecture-decisions.md` records a reviewed change:

```php
namespace BPP\Integration;

interface Builder_Adapter_Interface {
    public function is_available(): bool;

    public function get_template_id_for_product( int $product_id ): int|string|null;

    public function create_initial_state(
        int|string $template_id,
        array $context = []
    ): array;

    public function load_state( array $context ): array;

    public function validate_state(
        array $state,
        array $context = []
    ): array|\WP_Error;

    public function render_editor(
        array $state,
        array $context = []
    ): string;

    public function enqueue_editor_assets(
        array $context = []
    ): void;

    public function save_state(
        array $state,
        array $context = []
    ): array|\WP_Error;

    public function render_preview_html(
        array $state,
        array $context = []
    ): string;

    public function render_public_html(
        array $state,
        array $context = []
    ): string;

    public function generate_pdf(
        array $state,
        array $context = []
    ): array|\WP_Error;

    public function get_schema_version(
        array $state
    ): string;

    public function migrate_state(
        array $state,
        string $from_version,
        string $to_version
    ): array|\WP_Error;
}
```

### Persistence boundary

Online Invitations owns project authorization and project storage.

The PDF Builder adapter may:

- Load legacy order-item payload when `order_item_id` is supplied.
- Normalize, validate, migrate, render, and serialize builder state.
- Return canonical state for Online Invitations to persist.
- Preserve existing order-item behavior for the current shop flow.

The PDF Builder adapter must not:

- Query Online Invitation tables directly.
- Trust a browser project ID.
- decide project ownership.
- Store public invitation tokens.
- Create invitation projects.
- Implement guest/RSVP/wishlist/photo behavior.

Where the audited method names `load_state()` and `save_state()` are used for project contexts, the technical plan must define their exact semantics so storage ownership remains clear. The preferred semantics are:

- `load_state($context)`: load legacy state when an authorized legacy storage key is supplied, or normalize a supplied state file payload.
- `save_state($state, $context)`: validate and return canonical serializable state; project persistence is performed by `ProjectStorage`.

Do not introduce circular service calls between plugins.

---

## 8. Builder context contract

Every adapter call receives a controlled context assembled server-side.

```php
$context = [
    'source'          => 'online_invitation',
    'mode'            => 'edit', // edit|preview|public|pdf
    'user_id'         => 123,
    'product_id'      => 456,
    'project_id'      => 789,
    'order_id'        => 1001,
    'order_item_id'   => 1002,
    'template_id'     => 456,
    'locale'          => 'da_DK',
    'size'            => 'a5',
    'format'          => 'flat',
    'state_version'   => 4,
    'is_preview'      => false,
    'is_public'       => false,
];
```

Rules:

- `source`, `user_id`, `project_id`, `order_id`, and `order_item_id` are server-owned.
- Browser input may request a mode/action but the server resolves it against allowed transitions.
- Product/template ID must match the project record.
- Size/format must be allowlisted against the product template.
- Public mode receives only published state.
- Adapter context never contains a raw public token in logs.

---

## 9. PDF Builder changes required before Online Invitations can be considered complete

### Required

1. Add adapter discovery and interface.
2. Extract editor enqueue/render logic from `is_product()` assumptions.
3. Provide context-aware editor assets for My Account.
4. Add server-side state validation.
5. Add public HTML rendering/sanitization.
6. Add schema version to builder payload.
7. Add stable integration hooks and DOM events.
8. Secure audited AJAX endpoints.
9. Guard or internalize missing theme helper dependencies.
10. Add support for `online_invitation` as a customisable product through a stable filter.

### Backward compatibility

The existing standard-product customizer must continue working.

Old order-item payload files must remain loadable.

Existing PDF generation must remain available.

Do not rewrite the entire PDF Builder or replace Webpack merely to implement the adapter.

### Required PHP hooks

At minimum:

```text
bpp/integration/service
bpp/is_product_customizable
bpp/template_for_product
bpp/initial_customer_state
bpp/validated_customer_state
bpp/before_editor_render
bpp/after_editor_render
bpp/customer_state_saved
bpp/preview_html
bpp/public_html
bpp/pdf_generated
```

Document arguments, return types, ownership assumptions, and whether each hook is public API.

### Required JavaScript events

At minimum:

```text
bpp:editor-ready
bpp:state-loaded
bpp:state-changed
bpp:validation-failed
bpp:save-requested
bpp:save-completed
bpp:preview-generated
bpp:generation-failed
bpp:image-uploaded
```

Use `CustomEvent` with documented `detail` payloads. Keep existing events for backward compatibility.

---

## 10. Product type architecture

`WC_Product_Online_Invitation` should extend the simplest suitable WooCommerce product class, normally `WC_Product_Simple`.

### Product behavior

- Virtual.
- Sold individually.
- No shipping fields.
- No inventory-based guest capacity.
- Quantity always one.
- Fixed product price.
- Supports normal coupons/taxes unless business rules say otherwise.
- Can coexist with physical items in cart.
- Builder must be active/configured before product can be sold.
- Product page continues to use existing builder customisation and add-to-cart flow.

### Product settings

Add a dedicated product-data panel or clearly grouped fields:

```text
Builder enabled/valid status
Envelope preset identifier
Envelope preview
Generic background preset identifier
Default invitation locale
Default project expiry policy
Optional default RSVP reminder offset (default 5 days)
Optional allowed guest photo uploads toggle
Optional internal wishlist toggle
```

The builder template remains attached to the same WooCommerce product in V1.

Do not duplicate the complete `_bpp_product` template into Online Invitation product meta.

### Quantity enforcement layers

Enforce one at:

- Product object `is_sold_individually`.
- Product page markup.
- Add-to-cart validation.
- Cart quantity update.
- Store API/cart endpoints where used.
- Server-side order sanity checks.

Client-side hiding alone is insufficient.

---

## 11. Checkout and account flow

A mixed cart may contain invitation and ordinary items.

When the cart contains at least one `online_invitation` item:

1. A customer account is required.
2. Logged-in customers retain their account.
3. Guest customers provide an e-mail and WooCommerce creates/associates the account through supported checkout APIs.
4. The customer receives a secure password-setup link when needed.
5. Do not send a plaintext password.
6. Builder cart data remains attached only to its invitation line item.
7. The project is not created on `woocommerce_thankyou`.
8. Qualifying status hooks call one idempotent project creation service.

The technical plan must inspect whether the site uses classic checkout, Checkout Block, or both. Classic checkout is mandatory. Implement the actual production checkout path, and add a safe compatibility layer for the other path when repository evidence supports it.

---

## 12. Project creation flow

```text
Order enters on-hold|processing|completed
    |
    v
Iterate WooCommerce order items through CRUD
    |
    +-- non-online_invitation -> ignore
    |
    +-- online_invitation
            |
            +-- project id already on item -> verify and return
            |
            +-- unique project row for order_item_id exists -> relink and return
            |
            v
        acquire project-creation lock
            |
            v
        validate customer, product, builder payload
            |
            v
        create private CPT shell
            |
            v
        insert project table row
            |
            v
        import legacy/order-item builder payload
            |
            v
        write project state + page HTML atomically
            |
            v
        set order-item project ID
            |
            v
        schedule expiry/reminder baseline
            |
            v
        queue/send welcome e-mail once
```

### Failure handling

- Do not leave an invisible half-created project.
- Record a safe admin-visible error.
- Preserve source order-item payload.
- Make retries idempotent.
- Roll back newly created DB/file records where safe.
- Do not send welcome e-mail before the project is usable.
- Provide an admin retry action.

---

## 13. Project CPT

Register `pks_oi_project` as a private administrative shell.

Use it for:

- WordPress admin navigation.
- Human-readable project title.
- Custom capabilities.
- Linking to owner/order/product.
- Support actions.

Do not use:

- Public CPT permalinks.
- Post content as invitation HTML.
- Post meta as the primary project repository.
- The CPT post status as a second conflicting domain state machine.

Recommended generated admin title:

```text
Invitation #1234 — Product name — Customer display name
```

The title must be escaped and regenerated through a domain service when references change.

---

## 14. Database schema

Use `utf8mb4` and the site's WordPress collation.

Exact SQL belongs in `docs/database-schema.md`. The following logical schema is required.

### 14.1 `pks_oi_projects`

Primary domain row, one-to-one with CPT post ID.

Suggested columns:

```text
project_id BIGINT UNSIGNED PRIMARY KEY             -- CPT ID
storage_uuid CHAR(36) NOT NULL UNIQUE
user_id BIGINT UNSIGNED NOT NULL
order_id BIGINT UNSIGNED NOT NULL
order_item_id BIGINT UNSIGNED NOT NULL UNIQUE
product_id BIGINT UNSIGNED NOT NULL
template_id VARCHAR(191) NOT NULL
status VARCHAR(32) NOT NULL
publication_status VARCHAR(32) NOT NULL
locale VARCHAR(20) NOT NULL
timezone VARCHAR(64) NOT NULL
event_title VARCHAR(255) NULL
event_start_utc DATETIME NULL
event_end_utc DATETIME NULL
rsvp_deadline_utc DATETIME NULL
reminder_offset_days SMALLINT UNSIGNED NOT NULL DEFAULT 5
expires_at_utc DATETIME NULL
expiry_override_utc DATETIME NULL
external_wishlist_url TEXT NULL
generic_token_hash CHAR(64) NULL UNIQUE
generic_token_version INT UNSIGNED NOT NULL DEFAULT 1
builder_schema_version VARCHAR(32) NOT NULL
state_version BIGINT UNSIGNED NOT NULL DEFAULT 1
published_version BIGINT UNSIGNED NULL
state_manifest_path VARCHAR(512) NOT NULL
published_manifest_path VARCHAR(512) NULL
last_error_code VARCHAR(100) NULL
created_at_utc DATETIME NOT NULL
updated_at_utc DATETIME NOT NULL
published_at_utc DATETIME NULL
restricted_at_utc DATETIME NULL
expired_at_utc DATETIME NULL
deleted_at_utc DATETIME NULL
```

Indexes:

```text
(user_id, status)
(order_id)
(product_id)
(publication_status, status)
(expires_at_utc, status)
```

Do not store raw token, HTML, builder state, guest count cache without an invalidation strategy, or customer password.

### 14.2 `pks_oi_guests`

Suggested columns:

```text
guest_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
project_id BIGINT UNSIGNED NOT NULL
address_book_id BIGINT UNSIGNED NULL
display_name VARCHAR(255) NOT NULL
email VARCHAR(320) NULL
phone VARCHAR(64) NULL
party_label VARCHAR(255) NULL
token_hash CHAR(64) NOT NULL UNIQUE
token_version INT UNSIGNED NOT NULL DEFAULT 1
rsvp_status VARCHAR(32) NOT NULL DEFAULT 'pending'
attendee_count SMALLINT UNSIGNED NULL
rsvp_comment TEXT NULL
dietary_notes TEXT NULL
invitation_status VARCHAR(32) NOT NULL DEFAULT 'not_sent'
first_sent_at_utc DATETIME NULL
last_sent_at_utc DATETIME NULL
first_opened_at_utc DATETIME NULL
last_opened_at_utc DATETIME NULL
responded_at_utc DATETIME NULL
archived_at_utc DATETIME NULL
created_at_utc DATETIME NOT NULL
updated_at_utc DATETIME NOT NULL
```

Indexes:

```text
(project_id, archived_at_utc)
(project_id, rsvp_status)
(project_id, invitation_status)
(project_id, email)
```

Do not add a unique project/e-mail constraint because one household or duplicate e-mail may legitimately receive multiple named invitations. Provide duplicate warnings at UI level.

### 14.3 `pks_oi_address_book`

Suggested columns:

```text
address_book_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id BIGINT UNSIGNED NOT NULL
display_name VARCHAR(255) NOT NULL
email VARCHAR(320) NULL
phone VARCHAR(64) NULL
notes TEXT NULL
normalized_email_hash CHAR(64) NULL
created_at_utc DATETIME NOT NULL
updated_at_utc DATETIME NOT NULL
archived_at_utc DATETIME NULL
```

Indexes:

```text
(user_id, archived_at_utc)
(user_id, normalized_email_hash)
```

### 14.4 `pks_oi_wishlist_items`

Suggested columns:

```text
wishlist_item_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
project_id BIGINT UNSIGNED NOT NULL
title VARCHAR(255) NOT NULL
description TEXT NULL
external_url TEXT NULL
image_path VARCHAR(512) NULL
quantity_requested SMALLINT UNSIGNED NOT NULL DEFAULT 1
quantity_reserved SMALLINT UNSIGNED NOT NULL DEFAULT 0
sort_order INT NOT NULL DEFAULT 0
status VARCHAR(32) NOT NULL DEFAULT 'active'
created_at_utc DATETIME NOT NULL
updated_at_utc DATETIME NOT NULL
```

Reservation details are stored in the dedicated `pks_oi_wishlist_reservations` table. Do not encode guest reservations in a comma-separated field or event metadata alone.

### 14.5 `pks_oi_wishlist_reservations`

Suggested columns:

```text
reservation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
wishlist_item_id BIGINT UNSIGNED NOT NULL
project_id BIGINT UNSIGNED NOT NULL
guest_id BIGINT UNSIGNED NOT NULL
quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1
status VARCHAR(32) NOT NULL DEFAULT 'active'
created_at_utc DATETIME NOT NULL
updated_at_utc DATETIME NOT NULL
released_at_utc DATETIME NULL
```

Indexes/constraints:

```text
UNIQUE (wishlist_item_id, guest_id)
(project_id, status)
(guest_id, status)
```

Reservation services must use a transaction or atomic conditional update so active reservation totals never exceed the requested quantity.

### 14.6 `pks_oi_photos`

Suggested columns:

```text
photo_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
project_id BIGINT UNSIGNED NOT NULL
guest_id BIGINT UNSIGNED NULL
storage_uuid CHAR(36) NOT NULL UNIQUE
relative_path VARCHAR(512) NOT NULL
thumbnail_path VARCHAR(512) NULL
original_filename VARCHAR(255) NULL
mime_type VARCHAR(100) NOT NULL
byte_size BIGINT UNSIGNED NOT NULL
width INT UNSIGNED NULL
height INT UNSIGNED NULL
sha256 CHAR(64) NOT NULL
moderation_status VARCHAR(32) NOT NULL DEFAULT 'pending'
caption TEXT NULL
created_at_utc DATETIME NOT NULL
moderated_at_utc DATETIME NULL
deleted_at_utc DATETIME NULL
```

Indexes:

```text
(project_id, moderation_status, created_at_utc)
(guest_id)
(sha256)
```

### 14.7 `pks_oi_deliveries`

Suggested columns:

```text
delivery_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
project_id BIGINT UNSIGNED NOT NULL
guest_id BIGINT UNSIGNED NULL
delivery_type VARCHAR(32) NOT NULL
idempotency_key CHAR(64) NOT NULL UNIQUE
recipient_hash CHAR(64) NULL
status VARCHAR(32) NOT NULL
attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0
scheduled_at_utc DATETIME NULL
started_at_utc DATETIME NULL
sent_at_utc DATETIME NULL
failed_at_utc DATETIME NULL
last_error_code VARCHAR(100) NULL
last_error_message TEXT NULL
created_at_utc DATETIME NOT NULL
updated_at_utc DATETIME NOT NULL
```

Do not store full personal link or full recipient address in general logs when the guest row already owns it.

### 14.8 `pks_oi_events`

Append-oriented audit/event table:

```text
event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
project_id BIGINT UNSIGNED NOT NULL
guest_id BIGINT UNSIGNED NULL
actor_type VARCHAR(32) NOT NULL
actor_id BIGINT UNSIGNED NULL
event_type VARCHAR(64) NOT NULL
metadata_json LONGTEXT NULL
created_at_utc DATETIME NOT NULL
```

Indexes:

```text
(project_id, created_at_utc)
(project_id, event_type, created_at_utc)
(guest_id, created_at_utc)
```

Validate and size-limit metadata. Never place raw builder state, tokens, complete request bodies, or photo bytes in event metadata.

---

## 15. Project file layout

Use a configurable private root such as:

```text
PKS_OI_STORAGE_PATH
```

Recommended logical layout:

```text
{private-root}/
└── projects/
    └── {storage_uuid}/
        ├── manifest.json
        ├── state/
        │   ├── current.json
        │   └── previous.json
        ├── pages/
        │   ├── editable/
        │   │   ├── page-001.html
        │   │   └── page-002.html
        │   └── published/
        │       ├── page-001.html
        │       └── page-002.html
        ├── published/
        │   └── manifest.json
        ├── previews/
        │   └── owner-preview.webp
        ├── wishlist/
        │   └── images/
        ├── photos/
        │   ├── pending/
        │   ├── approved/
        │   └── thumbnails/
        └── tmp/
```

### `manifest.json`

Suggested shape:

```json
{
  "project_id": 123,
  "storage_uuid": "uuid",
  "builder_schema_version": "1",
  "state_version": 7,
  "product_id": 456,
  "template_id": "456",
  "pages": [
    {
      "index": 1,
      "editable_path": "pages/editable/page-001.html",
      "published_path": "pages/published/page-001.html",
      "editable_sha256": "...",
      "published_sha256": "..."
    }
  ],
  "updated_at_utc": "2026-07-14T08:00:00Z"
}
```

### Storage behavior

- Import original order payload once.
- Normalize builder state.
- Split large page HTML into page files.
- Keep compact canonical state JSON.
- Extract or reference uploaded builder images according to adapter rules.
- Write temporary files in `tmp/`.
- Atomically replace current files after all validation succeeds.
- Increment `state_version`.
- Reject stale save requests with a conflict response.
- Publish by generating a separate sanitized snapshot.
- Never mutate published files during an ordinary draft save.

---

## 16. Project domain model

### Project status

```text
draft
active
restricted
expired
archived
deleted
```

### Publication status

```text
unpublished
published
```

### Suggested transitions

```text
draft -> active
active -> restricted
restricted -> active       admin/entitlement restoration
active -> expired
draft -> archived
active -> archived
archived -> active         explicit restore
* -> deleted               erasure/hard-delete workflow only
```

Publication is independent:

```text
unpublished -> published
published -> unpublished
```

A project may be `active + unpublished`.

A project must not be publicly resolvable unless:

```text
status = active
publication_status = published
current time < effective expiry
entitlement is valid
published snapshot exists and passes checksum
```

### Effective expiry

```text
expiry_override_utc
    ?? (event_end_utc or event_start_utc) + 90 days
```

If no event date exists, project publication must require one or use a documented fallback accepted in the technical plan.

---

## 17. My Account information architecture

Main endpoint:

```text
/my-account/online-invitations/
```

Project route may use endpoint parameters or safe query variables:

```text
/my-account/online-invitations/{project-reference}/{section}/
```

The customer-visible reference must not be treated as authorization. A numeric project ID may be used inside authenticated My Account URLs when every request performs ownership checks, but an opaque project reference is preferable.

Required sections:

### Project list

Show:

- Product/design thumbnail.
- Project/event title.
- Status.
- Publication status.
- Event date.
- RSVP summary.
- Last updated.
- Primary next action.

Do not load full state/HTML.

### Overview

Show:

- Setup checklist.
- Direct links.
- Order reference.
- Expiry.
- Publish status.
- Response summary.
- Recent safe activity.

### Design

- Adapter-rendered editor.
- Load project-owned state.
- Authenticated save.
- Optimistic concurrency.
- Clear error recovery.
- No add-to-cart behavior.
- No product-page-only assumptions.

### Event details

Minimum fields:

- Event title.
- Event start/end.
- Venue/name.
- Address.
- Practical information.
- RSVP deadline.
- Project timezone.
- Reminder setting.
- Organiser display name.
- Optional public contact details.

Store structured fields in the project table or documented related storage. Do not allow arbitrary scriptable HTML.

### Guests

- Paginated guest list.
- Add/edit/archive.
- Copy personal link.
- Send/re-send.
- Status.
- RSVP.
- Bulk selection.
- CSV import/export.
- Add from address book.

### Address book

- Private reusable contacts.
- Search.
- Add/edit/archive.
- Select into current project.
- Explicit import from project guests.

### Preview

- Draft preview visible only to owner/admin through authenticated route.
- Uses adapter preview/public renderer with draft state.
- Includes envelope.
- Does not increment guest open tracking.
- Demo-to-self sends a special owner preview link or guest-like demo token that cannot submit real RSVP.

### Publish/share/send

- Validate required project state.
- Publish/unpublish.
- Rotate generic social link.
- Copy generic link.
- Queue invitation e-mails.
- Show delivery summary.
- Do not provide SMS UI.

### Responses

- RSVP totals.
- Paginated guest responses.
- Export.
- Recent changes.
- No guest identities shown publicly.

### Wishlist

- External Ønskeskyen URL.
- Internal item CRUD.
- Sort/reorder.
- Reservation counts.
- Surprise-privacy setting.

### Guest photos

- Enable/disable guest uploads.
- Upload limits.
- Pending/approved/rejected.
- Approve/reject/delete/download.
- No automatic public publication.

### Settings

- Archive.
- Token rotation.
- Expiry information.
- Project deletion request.
- E-mail notification preferences.
- Photo/wishlist toggles.

---

## 18. Public routes and token model

### Generic link

```text
/invitation/{generic-token}/
```

Uses project token hash.

Behavior:

- Shows non-personal envelope label such as “Du er inviteret”.
- Does not expose a named guest.
- May allow visitor to enter a name and create a generic-response guest record.
- Uses a separate flow from named guest RSVP.
- Can be rotated by owner.
- Is suitable for social sharing.

### Personal link

```text
/invitation/{guest-token}/
```

A single base route can resolve token hash against guest first, then generic project token, without revealing which table matched.

Behavior:

- Shows guest display name on envelope.
- Loads that guest's prior RSVP.
- Allows response changes until deadline.
- Can allow guest upload and wishlist reservation.
- Tracks link opened with bot/prefetch caveats.

### Token generation

- Use cryptographically secure random bytes.
- Encode URL-safe.
- Store SHA-256 or HMAC hash.
- Return raw token only at generation/rotation time to the link service.
- Never log raw token.
- Token rotation revokes old links.
- Include a token version in rows.
- Use constant-time comparison where a direct comparison occurs.

### Open tracking

Record:

- `first_opened_at_utc`
- `last_opened_at_utc`
- Optional bounded `open_count` only if useful

Do not increment on:

- Owner preview.
- Admin preview.
- Known health checks.
- Obvious prefetch/bot requests where detected.

Describe status as **Invitation link opened**, not **E-mail read**.

---

## 19. Envelope and invitation renderer

The public invitation renderer is composed of:

```text
Public route controller
    -> project/guest token resolution
    -> entitlement/publication checks
    -> envelope view model
    -> published builder snapshot
    -> event details
    -> enabled RSVP/wishlist/photo sections
```

### Envelope

- Preset is configured on the product.
- Guest name is dynamic for a personal link.
- Generic link uses neutral wording.
- Animation reveals the invitation.
- Reduced-motion mode skips or simplifies the animation.
- A visible “Open invitation” control is keyboard accessible.
- The invitation content remains available when JavaScript is unavailable.

### Invitation HTML

- Comes from the sanitized published snapshot.
- Uses builder-provided fonts/public CSS through the adapter.
- Fixed builder dimensions may be responsively scaled.
- Public view must be tested on phone, tablet, and desktop.
- Do not enqueue the complete editor unless the adapter proves it is required.
- Do not expose raw editable state.

---

## 20. Guest domain behavior

### Guest creation

Guests may be created:

- Manually.
- From address book.
- CSV import.
- Generic-link RSVP.
- Future API integrations, not V1.

Each guest receives an independent token even when several guests share an e-mail.

### RSVP state

```text
pending
attending
not_attending
```

Do not add `maybe` unless the user explicitly expands the response model.

### Invitation status

```text
not_sent
queued
sent
failed
cancelled
```

Opened/responded are timestamps/state dimensions, not replacements for send status.

### Guest mutation

- Owner can edit name/e-mail before or after sending.
- Changing a guest name updates future envelope rendering.
- Changing e-mail does not rotate token automatically.
- Deleting/archiving revokes public access unless a restore action reissues a token.
- Response history is logged.
- Guest can change RSVP until deadline.

---

## 21. Address book behavior

Address-book entries belong to the WordPress customer, not the project.

### Add from address book

1. Customer selects entries.
2. System shows duplicate/conflict review.
3. Project guest snapshots are created.
4. Each guest gets a project-specific token.
5. Address-book entry remains independent.

### Save project guest to address book

- Explicit action only.
- Show fields to copy.
- Resolve normalized e-mail duplicates.
- Never silently overwrite an existing contact.

### Privacy

- Other customers cannot query entry count, search results, or existence.
- Admin access requires support capability and should be audited.
- Address book is included in export/erasure.

---

## 22. RSVP and reminder behavior

### RSVP form

Minimum fields:

```text
Attending: yes/no
Attendee count: optional/configurable
Comment: optional
Dietary notes: optional/configurable
```

The project setting controls which optional fields appear.

### Deadline

- Stored in UTC.
- Public form shows local timezone/date.
- After deadline, display current response and a closed message.
- Owner/admin may still edit responses through controlled support UI.

### Reminder scheduling

Default:

```text
rsvp_deadline_utc - 5 days
```

When deadline changes:

1. Cancel pending project reminder actions.
2. Recalculate.
3. Schedule one action per eligible guest or a batched dispatcher.
4. Skip guests already responded.
5. Use an idempotency key.
6. Record skipped/sent/failed result.

Do not schedule reminders when there is no deadline or valid guest e-mail.

---

## 23. Wishlist behavior

### External list

Project stores an optional external Ønskeskyen URL.

- Validate HTTP/HTTPS.
- Display as a clearly external link.
- Do not scrape.
- Do not embed arbitrary external HTML.

### Internal list

Organiser can create items with:

- Title.
- Description.
- URL.
- Optional image.
- Quantity.
- Sort order.
- Active/hidden state.

### Reservation

A guest can:

- Reserve available quantity.
- Release their reservation.
- See availability, not other guest identity.

Concurrency:

- Use transaction/atomic conditional update.
- Never allow `quantity_reserved > quantity_requested`.
- A repeated request from the same guest is idempotent.
- Log changes.

Surprise privacy:

- Default owner view shows reserved count but not guest identity.
- Optional project setting may reveal identity.
- Public users never see another reserver's identity.

---

## 24. Guest photo-upload behavior

### V1 purpose

Guests can contribute event photos to the project.

Organizer can review and manage them in My Account.

Automatic public gallery publication is off by default.

### Upload flow

1. Guest opens valid invitation.
2. Public controller issues a short-lived signed upload intent.
3. Guest selects image.
4. Client validates basic type/size for UX.
5. Server verifies project/token/intent/rate limit.
6. Server inspects MIME and dimensions.
7. Server writes to temporary private path.
8. Image is re-encoded/EXIF-stripped where supported.
9. Final file is atomically moved.
10. Photo row is inserted as `pending`.
11. Organizer notification is queued according to settings.

### Limits

Exact defaults belong in the technical plan. Suggested initial values:

```text
Allowed: JPEG, PNG, WebP
Max file size: 10 MB
Max pixels: 25 megapixels
Max files per request: 10
Project storage soft limit: configurable, not guest-count capacity
```

Do not allow SVG, PDF, HEIC without a verified conversion path, archives, or video in V1.

### Moderation

```text
pending
approved
rejected
deleted
```

Approval may enable an optional project gallery later in V1 only if included in the accepted technical plan. The baseline requirement is upload plus organiser review/download.

---

## 25. Delivery and e-mail model

### Owner e-mails

#### Project welcome

Triggered once after successful project creation.

Contains:

- Product/design name.
- Direct My Account project link.
- Password setup guidance when relevant.
- Next steps.
- Support information.

#### Demo invitation

Sent on explicit owner action.

- Uses owner e-mail.
- Uses a demo token or authenticated preview URL.
- Does not create a real guest response.
- Does not affect sent/opened guest statistics.

#### RSVP notification

Sent when a guest changes response according to owner settings.

### Guest e-mails

#### Invitation

- Personal guest URL.
- Event/organiser display name.
- Clear button and fallback URL.
- No raw internal IDs.
- No SMS references.

#### Reminder

- Only eligible unresponded guests.
- Default five days before deadline.
- Personal guest URL.

#### RSVP confirmation

- Summarizes recorded response.
- Includes link to change response until deadline.

### Delivery table and Action Scheduler

Every queued e-mail has:

```text
delivery_type
project_id
guest_id when applicable
idempotency_key
status
attempt_count
scheduled time
result timestamps
safe error code
```

Background callbacks resolve current guest/project data by ID; do not serialize full personal records into action arguments.

---

## 26. Admin support

The CPT admin screen or related support page must provide:

- Project owner.
- Order and order-item links.
- Product/design.
- Domain/publication status.
- Event date and expiry.
- Builder schema/state version.
- Storage health/checksum status.
- Guest/response/photo/delivery counts.
- Last safe error.
- Open owner project as admin support view.
- Retry failed project import.
- Re-send owner welcome.
- Restrict/unrestrict.
- Set expiry override.
- Rotate generic token.
- Inspect delivery failures.
- Start controlled hard-delete.

Every support action:

- Requires custom capability.
- Uses nonce.
- Records an event.
- Shows result.
- Avoids exposing raw tokens/state.

Do not use the post edit content editor.

---

## 27. Refund, cancellation, and entitlement

### Full invitation line refund

- Set project `restricted`.
- Unpublish or make public resolver unavailable.
- Cancel pending invitation/reminder jobs.
- Block new send/publish/public RSVP/photo/wishlist actions.
- Preserve all project data.
- Notify admin/owner only according to accepted policy.
- Record event.

### Partial refund

- Determine refunded quantity/value against the invitation line.
- Since quantity is one, treat a complete line refund as full entitlement removal.
- A partial order refund unrelated to invitation line does not restrict the project.

### Cancellation/failed order

- Do not create new project unless order later reaches qualifying status.
- Existing project handling follows explicit entitlement transition logic.
- Avoid toggling repeatedly during status churn.

### Restore

Admin may restore entitlement.

- Re-check payment/refund state.
- Require capability and nonce.
- Record reason.
- Do not automatically resend all invitations.

---

## 28. Expiration and cleanup

### Expiration

Effective expiry is 90 days after event end/date unless overridden.

Scheduled job:

- Finds eligible active projects.
- Marks expired.
- Makes public routes unavailable.
- Cancels future sends/reminders/uploads.
- Retains customer/admin read access according to policy.
- Records event.

### Cleanup

Separate from expiration.

Cleanup may remove:

- Abandoned temp files.
- Obsolete preview derivatives.
- Failed upload temp files.
- Expired rate-limit records.
- Superseded previous state after retention window.
- Permanently erased project files.

Do not automatically hard-delete all expired projects.

---

## 29. Security threat model

### Main risks

1. IDOR against projects, guests, order items, photos, and address book.
2. Public bearer-token leakage.
3. Stored XSS through builder page HTML.
4. Arbitrary file upload.
5. Path traversal and direct access to private files.
6. E-mail abuse and bulk-send duplication.
7. Expensive unauthenticated PDF/image endpoints.
8. SQL injection from custom repositories.
9. CSV injection.
10. Race conditions in wishlist reservations and state saves.
11. Duplicate project creation on repeated order hooks.
12. Privacy leakage between guests.

### Required mitigations

- Central authorization service.
- Token hashing and rotation.
- Builder-specific HTML sanitizer.
- Strict upload pipeline.
- Configurable private storage.
- Atomic files and version checks.
- Prepared SQL and table constraints.
- Action Scheduler idempotency.
- Rate limits.
- Safe CSV encoding.
- Generic public errors.
- Security tests for negative cases.

---

## 30. Published HTML trust model

The builder currently stores full customer-supplied `page[]` HTML.

Treat it as untrusted even though the UI normally generates it.

### Draft state

- Private.
- Editable.
- May preserve builder-required attributes and structure.
- Never shown to a logged-out visitor.
- Rendered inside controlled owner/editor context.

### Published state

Generated by:

```text
draft state
    -> adapter validation
    -> builder public renderer
    -> strict HTML/CSS sanitization
    -> page files
    -> checksums
    -> published manifest
```

Publishing fails if:

- Required fields invalid.
- Template missing.
- State schema cannot migrate.
- Unsafe content cannot be normalized safely.
- File write/checksum fails.
- Public HTML is empty.
- Project entitlement invalid.

Do not fall back to echoing raw draft HTML when publication fails.

---

## 31. Template and theme integration

Plugin templates are overridable through a documented path, for example:

```text
theme/prikogstreg-online-invitations/
```

Template loader must:

- Prefer child theme override.
- Then parent theme override.
- Then plugin template.
- Validate template names against an allowlist.
- Never accept a request path.
- Pass explicit view models.
- Escape in template.

The theme can style WooCommerce account pages, but the plugin must render a complete usable interface without a custom theme.

---

## 32. Asset/build direction

### PDF Builder

Preserve its existing Webpack pipeline.

New integration code should:

- Add source modules to existing entries or add focused entries.
- Build editor/public-view assets.
- Keep legacy product flow functional.
- Avoid globally loading editor code.

### Online Invitations

Use a simple documented pipeline appropriate to repository context, likely:

- Sass.
- esbuild.
- `concurrently` for watch mode.

Suggested outputs:

```text
assets/build/css/account.css
assets/build/css/public.css
assets/build/css/admin.css
assets/build/js/account.js
assets/build/js/public.js
assets/build/js/admin.js
```

Do not require Node in production. Commit/package compiled assets.

---

## 33. API/controller strategy

Prefer ordinary server-rendered forms for CRUD when they provide good UX.

Use authenticated AJAX or REST for:

- Autosave/editor state.
- Large guest bulk operations.
- Photo uploads.
- Wishlist reorder/reservation.
- Delivery progress/status refresh.

Use public REST/AJAX only for:

- Token-authorized RSVP.
- Token-authorized wishlist reservation.
- Signed-intent photo upload.

Every endpoint must have:

- Explicit method.
- Input schema.
- Authentication/authorization.
- CSRF strategy.
- Rate limit.
- Response schema.
- Error codes.
- Tests.

Do not use `admin-ajax.php` by habit when a namespaced REST route offers clearer method and permission callbacks. Do not add REST merely to satisfy architecture aesthetics.

---

## 34. Suggested public and authenticated routes

Exact namespace versioning belongs in the technical plan.

Possible REST namespace:

```text
prikogstreg-online-invitations/v1
```

Possible endpoints:

```text
POST /projects/{project}/state
POST /projects/{project}/publish
POST /projects/{project}/demo
GET  /projects/{project}/guests
POST /projects/{project}/guests
POST /projects/{project}/guests/import
POST /projects/{project}/send
POST /projects/{project}/photos/{photo}/moderate

POST /public/{token}/rsvp
POST /public/{token}/wishlist/{item}/reserve
POST /public/{token}/upload-intent
POST /public/{token}/photos
```

Permission callbacks must resolve project ownership/token before controller logic.

Never accept user ID as authorization input.

---

## 35. Privacy and retention matrix

`docs/privacy-retention.md` must define at least:

| Data | Owner/subject | V1 retention direction |
|---|---|---|
| Project/order references | Customer/business record | Retain according to commerce/legal needs |
| Draft/published invitation | Customer | Until deletion policy after expiry |
| Guests | Customer and guests | Until project deletion/retention end |
| Address book | Customer | Until customer deletes/erasure |
| RSVP | Guest/customer | Until project deletion/retention end |
| Wishlist reservations | Guest/customer | Until project deletion/retention end |
| Photos | Guest/customer | Until deletion/retention end |
| Delivery logs | Customer/guest | Minimized retention |
| Event logs | Customer/system | Minimized support/security retention |
| Rate-limit identifiers | Visitor | Short expiry |

Do not invent exact legal retention periods. Document technical defaults and flag business/legal confirmation where needed.

---

## 36. Testing architecture

### Unit

- Status transitions.
- Expiry calculations.
- Token generation/hash.
- URL validation.
- CSV neutralization.
- HTML sanitizer.
- upload validation.
- idempotency keys.
- reservation arithmetic.

### WordPress/WooCommerce integration

- Activation/schema.
- CPT/capabilities.
- product type and sold-individually.
- mixed cart.
- account requirement.
- HPOS order CRUD.
- project creation.
- order-item meta link.
- refund listener.
- My Account endpoint.
- e-mail registration.
- privacy exporter/eraser.
- Action Scheduler callbacks.

### Builder integration

- Adapter discovery.
- load legacy order payload.
- create/validate state.
- editor assets outside product page.
- My Account render.
- public renderer strips malicious fixture.
- schema migration.
- legacy product customizer regression.

### End-to-end

1. Admin configures online invitation product.
2. Customer customises before purchase.
3. Mixed checkout creates/associates account.
4. Order enters qualifying status.
5. One project appears.
6. Customer opens direct welcome link.
7. Edits and saves.
8. Adds event details.
9. Adds address-book contact and guest.
10. Publishes.
11. Sends demo.
12. Sends guest invitation.
13. Guest opens personal envelope.
14. Open status changes.
15. Guest RSVP.
16. Guest reserves wishlist item.
17. Guest uploads photo.
18. Organizer reviews response/photo.
19. Reminder skips responded guest.
20. Full invitation refund restricts project.
21. Admin override/restore behaves as designed.
22. Expiry job restricts after 90 days.

### Manual browser matrix

- Current Chrome, Safari, Firefox, Edge.
- iOS Safari.
- Android Chrome.
- Keyboard only.
- Reduced motion.
- 200% zoom.
- Slow network.
- JavaScript disabled public fallback.
- E-mail clients used by customer base.

---

## 37. Definition of done

The project is complete only when:

1. PDF Builder adapter exists and is documented.
2. Existing PDF Builder product flow still works.
3. Audited AJAX security gaps touched by integration are fixed.
4. `online_invitation` product works and quantity is one.
5. Mixed checkout works.
6. Account and password-setup flow is safe.
7. Project creation is idempotent for all qualifying statuses.
8. CPT and tables install/migrate cleanly.
9. Order payload imports to project-owned private storage.
10. My Account design editor can reopen/save.
11. Publishing creates sanitized file-backed public snapshot.
12. Generic and personal invitation links work.
13. Envelope works with reduced-motion fallback.
14. Guest management and address book work.
15. E-mail delivery and tracking work.
16. RSVP and reminder behavior work.
17. Wishlist and reservations work.
18. Guest photo upload/moderation works.
19. Refund restriction and expiration work.
20. Privacy export/erasure and cleanup are documented/tested.
21. Automated build and test commands pass.
22. Production review has no known release blocker.
23. Remaining manual tests are listed honestly.

---

## 38. Open decisions to resolve during Prompt 1

The user has confirmed the main scope. The implementation-plan prompt must still inspect and record:

1. The active theme locations that render `BPP_PDF_Plugin::content_single_product()` and apply `bpp_wc_attribute_html`.
2. Definitions of `ks_render_custom_field_meta()` and `get_product_min_order_quantity()`.
3. Classic checkout versus Checkout Block usage.
4. Exact PDF Builder data retrieval path for stored HTML and field state.
5. Public HTML fidelity without full editor JavaScript.
6. Storage root available on production hosting.
7. Action Scheduler availability/version through WooCommerce.
8. Exact project event-detail fields.
9. Guest CSV field set.
10. Photo storage limits and whether an approved public gallery is enabled in V1.
11. Whether wishlist reservation identity is hidden from organiser by default; recommendation is yes.
12. Exact e-mail sender/from policy.
13. Legal/business retention periods.
14. Plugin minimum PHP/WordPress/WooCommerce versions based on deployment evidence.

These decisions must not be used to remove confirmed V1 features. Use safe defaults and document them when repository evidence is sufficient.
