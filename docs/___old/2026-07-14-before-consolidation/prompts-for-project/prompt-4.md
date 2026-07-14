# Prompt 4 — Implement the formal PDF Builder integration service and contract

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
Read:
- accepted technical plan
- integration audit
- integration contract JSON
- current Composer/bootstrap behavior

Work in pdf-plugin.

Implement:

- src/Integration/Builder_Adapter_Interface.php
- src/Integration/Online_Invitation_Builder_Adapter.php
- src/Integration/Builder_Context.php or equivalent validated context value object
- src/Integration/Integration_Provider.php
- focused support classes justified by the plan

Service discovery:

apply_filters( 'bpp/integration/service', null )

Requirements:

1. Register exactly one adapter instance through a stable filter.
2. Use the existing BPP\ PSR-4 mapping correctly.
3. Preserve all old global BPP_ classes and behavior.
4. Implement the exact contract methods:
   - is_available
   - get_template_id_for_product
   - create_initial_state
   - load_state
   - validate_state
   - render_editor
   - enqueue_editor_assets
   - save_state
   - render_preview_html
   - render_public_html
   - generate_pdf
   - get_schema_version
   - migrate_state
5. In this phase, methods may delegate to focused existing behavior, but they must not return fake success.
6. Where a later prompt is needed, return a documented WP_Error such as bpp_operation_not_ready rather than unsafe output.
7. Define precise load_state/save_state semantics according to the accepted architecture:
   - support authorized legacy order-item state
   - normalize supplied project state
   - do not query Online Invitation tables
   - do not own project authorization
8. Add schema_version to canonical state exports without breaking old payloads.
9. Old payloads without schema_version resolve to the documented legacy version.
10. Add documented hooks from the audit:
    - bpp/integration/service
    - bpp/is_product_customizable
    - bpp/template_for_product
    - bpp/initial_customer_state
    - bpp/validated_customer_state
    - bpp/before_editor_render
    - bpp/after_editor_render
    - bpp/customer_state_saved
    - bpp/preview_html
    - bpp/public_html
    - bpp/pdf_generated
11. Add PHPDoc with parameter/return/ownership contracts.
12. Add tests for service discovery, interface conformance, missing WooCommerce, missing template, legacy schema, and no circular dependency.
13. Do not yet change My Account or create the new plugin.

Run tests and update builder integration documentation.
```
