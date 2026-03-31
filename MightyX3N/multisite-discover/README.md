# Multisite Discover

A WordPress Multisite plugin that lets subsite admins control their visibility on a network-wide Discover page, and lets them customise their site card with a logo and banner.

---

## Installation

1. Upload the `multisite-discover/` folder to `/wp-content/plugins/`.
2. In the **Network Admin → Plugins** screen, click **Network Activate**.
3. Done. The plugin activates network-wide automatically.

---

## For Subsite Admins

Each subsite gets a new settings screen under:

> **Settings → Discover Settings**

### Options

| Setting       | Description |
|---------------|-------------|
| **Visibility** | Toggle ON to appear on the Discover page. Toggle OFF to hide. |
| **Tagline**   | Short description shown on your card (overrides the site tagline). Max 120 chars. |
| **Logo**      | Square image shown as the card avatar. Recommended: 256×256 px or larger. |
| **Banner**    | Wide hero image across the top of your card. Recommended: 1200×400 px. |

---

## For Network / Site Admins — The Discover Shortcode

Add the Discover grid to **any page on any subsite** (or the main site) using:

```
[discover]
```

### Shortcode Attributes

| Attribute     | Default | Description |
|---------------|---------|-------------|
| `columns`     | `3`     | Number of columns in the grid (1–6). |
| `per_page`    | `12`    | Cards shown initially. A "Load more" button appears for the rest. Set `0` to show all. |
| `show_search` | `true`  | Show a live search/filter box above the grid. |

### Examples

```
[discover]
[discover columns="2" per_page="6"]
[discover columns="4" show_search="false" per_page="0"]
```

---

## Features

- **Public / Private toggle** — opt-in model; sites are hidden by default.
- **Logo & Banner** — uses the native WP Media Library; no extra upload handling needed.
- **Fallback colours** — sites without a banner get a unique gradient derived from their name. Sites without a logo get an initials avatar.
- **Live search** — filters cards instantly by site name or tagline.
- **Load more** — paginated display with zero extra HTTP requests (data embedded in button).
- **Dark mode** — cards respect `prefers-color-scheme: dark` automatically.
- **Zero dependencies** — no external libraries on the front end.

---

## File Structure

```
multisite-discover/
├── multisite-discover.php      ← Main plugin file
├── includes/
│   ├── site-settings.php       ← Subsite settings page + options registration
│   ├── shortcode.php           ← [discover] shortcode + card rendering
│   └── ajax.php                ← Reserved for future AJAX endpoints
└── assets/
    ├── admin.css               ← Settings page styles
    └── admin.js                ← Media Library uploader integration
```

---

## Requirements

- WordPress 5.6+
- Multisite enabled
- PHP 7.4+
