# Theme integration — Online Invitations

**Plugin:** `prikogstreg-online-invitations`  
**Audience:** Prikogstreg theme (and any WooCommerce theme)  
**Last updated:** 14 July 2026

---

## Goal

Let the theme answer three questions **without querying `pks_oi_*` database tables**:

1. Is the Online Invitations My Account feature available?
2. Does the logged-in customer have any invitation projects?
3. What URL should we link to (list vs. continue a specific project)?

---

## Quick start (Prikogstreg theme)

```php
<?php
// Example: header / account menu link for logged-in customers.

if ( ! is_user_logged_in() || ! function_exists( 'pks_oi_get_user_projects_nav' ) ) {
	return;
}

$nav = pks_oi_get_user_projects_nav();
if ( ! is_array( $nav ) || (int) ( $nav['count'] ?? 0 ) <= 0 ) {
	return;
}

$count = (int) $nav['count'];
$url   = 1 === $count
	? (string) ( $nav['primary_url'] ?? '' )
	: (string) ( $nav['list_url'] ?? '' );

if ( '' === $url ) {
	return;
}

$label = 1 === $count
	? __( 'Mine invitationer', 'prikogstreg' )
	: sprintf(
		/* translators: %d: number of invitation projects */
		_n( 'Mine invitationer (%d)', 'Mine invitationer (%d)', $count, 'prikogstreg' ),
		$count
	);
?>
<a class="pks-header-invitations-link" href="<?php echo esc_url( $url ); ?>">
	<?php echo esc_html( $label ); ?>
</a>
```

---

## Plugin API (global functions)

Loaded when the plugin boots My Account (`AccountPresentation::register()`).

Always guard with `function_exists()` when the plugin may be inactive.

| Function | Returns | Purpose |
|----------|---------|---------|
| `pks_oi_my_account_is_available()` | `bool` | Plugin registered the count filter |
| `pks_oi_get_user_project_count( $user_id = 0 )` | `int` | Active project count (0 if unavailable) |
| `pks_oi_get_my_account_list_url()` | `string` | List URL, e.g. `/min-konto/online-invitations/` |
| `pks_oi_get_my_account_endpoint_slug()` | `string` | WooCommerce endpoint slug: `online-invitations` |
| `pks_oi_get_user_projects_nav( $user_id = 0, $limit = 5 )` | `array\|null` | Count + URLs + recent projects |

### `pks_oi_get_user_projects_nav()` shape

```php
[
	'count'       => 2,                    // int — active projects for this user
	'list_url'    => 'https://…/min-konto/online-invitations/',
	'primary_url' => 'https://…/min-konto/online-invitations/284194/', // most recently updated
	'projects'    => [
		[
			'project_id'         => 284194,
			'title'              => 'Invitation project #284194', // or event_title when set
			'url'                => 'https://…/min-konto/online-invitations/284194/',
			'status'             => 'active',
			'publication_status' => 'unpublished',
			'updated_at'         => '2026-07-14 10:00:00',
		],
		// … up to $limit items, newest first
	],
]
```

| Field | Use in theme |
|-------|----------------|
| `count` | Badge, `_n()` copy, show/hide link |
| `list_url` | “All invitations” when customer has **multiple** projects |
| `primary_url` | “Continue invitation” when customer has **one** project (also = newest when multiple) |
| `projects` | Dropdown, dashboard sub-links, mega-menu items |

Returns `null` when the plugin is inactive or `$user_id` is not logged in.

---

## WordPress filters (low-level)

Themes may use filters directly instead of helpers.

| Filter | Args | Provided by |
|--------|------|-------------|
| `pks_oi_user_project_count` | `$count`, `$user_id` | `AccountPresentation` |
| `pks_oi_user_projects_nav` | `$nav`, `$user_id`, `$limit` | `AccountPresentation` |
| `pks_oi_account_nav_badge` | `$badge`, `$user_id`, `$count` | **Theme** (optional override of badge text) |

```php
// Count only
if ( has_filter( 'pks_oi_user_project_count' ) ) {
	$count = (int) apply_filters( 'pks_oi_user_project_count', 0, get_current_user_id() );
}
```

---

## Prikogstreg theme helpers (already in theme)

The Prikogstreg theme wraps the same data for My Account dashboard cards and legacy nav badges:

| Theme helper | Purpose |
|--------------|---------|
| `prikogstreg_get_my_account_online_invitations_endpoint()` | Discover endpoint slug (or `null`) |
| `prikogstreg_get_my_account_online_invitations_project_count( $user_id )` | Count or `null` if plugin inactive |
| `prikogstreg_get_my_account_endpoint_url( 'online-invitations' )` | Staging-normalized endpoint URL |
| `prikogstreg_my_account()->get_online_invitations_dashboard_description( $user_id )` | Danish dashboard card copy |

**Prefer plugin helpers** (`pks_oi_*`) in new theme code outside `core/woo-my-account.php` so the integration works even if theme helper names change.

---

## URL rules

| Destination | URL pattern |
|-------------|-------------|
| Project list | `/my-account/online-invitations/` (or `/min-konto/online-invitations/` on Prikogstreg) |
| Project overview | `…/online-invitations/{project_id}/` |
| Project section | `…/online-invitations/{project_id}/{section}/` |

Build URLs via:

- **Plugin:** `pks_oi_get_my_account_list_url()` or `\PrikOgStreg\OnlineInvitations\MyAccount\Endpoints::project_url( $id )`
- **Theme:** `prikogstreg_get_my_account_endpoint_url( 'online-invitations' )`

Do **not** hardcode `/min-konto/` or `/my-account/`.

---

## What the theme should do

### 1. Show a direct link when the customer has projects

| Location | Recommended behaviour |
|----------|----------------------|
| **My Account dashboard** | Featured card — already implemented in `core/woo-my-account.php` |
| **Header / account dropdown** | Link when `count > 0`; single project → `primary_url`, multiple → `list_url` |
| **Mobile menu** | Same as header |
| **Order thank-you / e-mail** | Plugin-owned; theme not required |

### 2. Hide when irrelevant

| Condition | Theme action |
|-----------|--------------|
| Visitor not logged in | No invitation link |
| Plugin inactive (`! pks_oi_my_account_is_available()`) | No link, no badge |
| `count === 0` | Optional: hide header link; dashboard card may still show generic “create invitations” copy |

### 3. Do not duplicate plugin UI

| Avoid | Why |
|-------|-----|
| Query `wp_pks_oi_projects` (or `$wpdb`) | Breaks if schema changes; bypasses authorization |
| Register a second `online-invitations` endpoint | Conflicts with plugin rewrites |
| Render full project list on dashboard | Plugin list view owns pagination and actions |
| Call `woocommerce_account_navigation` on OI pages | Replaced by opt-in sidebar — see sidebar hooks doc |

### 4. Styling

Scope theme CSS to your own link classes (e.g. `.pks-header-invitations-link`). Plugin markup inside My Account uses `.pks-oi*` — style via theme bridge SCSS, not by editing plugin templates.

---

## Link URL decision tree

```
Logged in?
  no  → hide
  yes → pks_oi_get_user_projects_nav()
          null or count 0 → hide (or generic marketing link — product decision)
          count 1 → primary_url (direct to project overview)
          count > 1 → list_url (project list)
```

For a **dropdown** with each project title, iterate `$nav['projects']` and use each item’s `url` + `title`.

---

## Sidebar (project section tabs)

When the customer is inside a project, section navigation (Overview, Design, Event, …) is rendered in the **left sidebar** via Prikogstreg hooks — not in theme PHP.

Theme docs:

- `wp-content/themes/prikogstreg/docs/my-account-sidebar-hooks.md`
- `wp-content/themes/prikogstreg/docs/my-account-online-invitations.md`

---

## Testing checklist

- [ ] Plugin **active**, user with 0 projects → no header link (if you hide on zero); dashboard card may still appear
- [ ] User with 1 project → link goes to `/min-konto/online-invitations/{id}/`
- [ ] User with 2+ projects → link goes to `/min-konto/online-invitations/`
- [ ] Plugin **deactivated** → no fatal errors; `function_exists( 'pks_oi_my_account_is_available' )` is false
- [ ] Logged-out visitor → no invitation link

---

## Related docs

| Document | Location |
|----------|----------|
| My Account shell (plugin) | `docs/my-account.md` |
| Prikogstreg OI contract | `themes/prikogstreg/docs/my-account-online-invitations.md` |
| Sidebar hooks | `themes/prikogstreg/docs/my-account-sidebar-hooks.md` |
| Template overrides | `docs/theme-overrides.md` |
