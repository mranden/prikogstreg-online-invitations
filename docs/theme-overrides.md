# Theme template overrides

**Loader:** `src/Support/TemplateLoader.php`  
**Compatibility version:** `PrikOgStreg\OnlineInvitations\Support\TemplateVersions::VERSION` (`1.0.0`)

---

## Resolution order

1. Child theme: `{child}/prikogstreg-online-invitations/{name}.php`
2. Parent theme: `{parent}/prikogstreg-online-invitations/{name}.php`
3. Plugin: `templates/{name}.php`

`locate_template()` handles child/parent precedence.

---

## Allowlisted templates

Only names in `TemplateLoader::ALLOWLIST` (and `emails/*` prefix) may load. Path traversal (`..`) is rejected.

Examples:

| Template | Purpose |
|----------|---------|
| `myaccount/project-overview` | Project dashboard |
| `myaccount/project-guests` | Guest list |
| `myaccount/project-photos` | Photo moderation |
| `public/envelope` | Animated envelope shell |
| `public/rsvp-form` | RSVP partial |
| `public/wishlist` | Wishlist partial |
| `public/photos` | Guest upload partial |
| `admin/support` | Support dashboard |

E-mail bodies under `templates/emails/` may be overridden with the same folder structure.

---

## Override guidelines

1. Copy the plugin template and bump tracking in your theme README when `TemplateVersions::VERSION` changes.
2. Preserve `@version` docblock and view-model variables documented in the template header.
3. Keep root wrapper classes (`.pks-oi`, `.pks-oi-public`, `.pks-oi-admin-support`) for scoped CSS.
4. Escape output in templates; never echo raw tokens or builder state.
5. Do not add arbitrary `$_GET` / `$_POST` paths — use provided view models only.

---

## Versioning

When plugin markup changes in a breaking way for theme overrides:

1. Increment `TemplateVersions::VERSION`
2. Document changes in release notes
3. Update `@version` in affected plugin templates
