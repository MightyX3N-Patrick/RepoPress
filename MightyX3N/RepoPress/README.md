# RepoPress

A WordPress plugin that lets admins browse and install plugins directly from GitHub repositories — no ZIP files needed.

## How it works

RepoPress reads GitHub repository contents via the GitHub API. Plugins are stored as plain folders (not zipped), and metadata is parsed directly from the WordPress plugin header in the main PHP file.

## GitHub Repository Structure

Your plugin repository should be organised like this:

```
your-repo/
  author-slug/
    plugin-slug/
      plugin-slug.php   ← main plugin file with WP header
      other-files.php
      assets/
        ...
```

The main plugin file must contain a standard [WordPress plugin header](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/):

```php
<?php
/**
 * Plugin Name:       My Plugin
 * Description:       What it does.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL v2 or later
 * Requires Plugins:  some-dependency
 */
```

The `Requires Plugins` field is optional. RepoPress will warn the admin if any required plugin is not installed and not found in any configured repo. WordPress itself will enforce dependencies after installation.

## Installation

1. Upload the `repopress` folder to `/wp-content/plugins/`
2. Activate the plugin (Network Activate on Multisite)
3. Go to **RepoPress → Settings** to optionally add a GitHub token and extra repositories

## GitHub Token (optional but recommended)

Without a token, GitHub allows 60 API requests per hour per IP. With a token (needs `public_repo` scope for public repos), this increases to 5,000/hour.

Create one at: https://github.com/settings/tokens

## Multisite

On a WordPress Multisite network:

- RepoPress appears in the **Network Admin** area
- Network admins can enable it per-subsite via **RepoPress → Subsites**
- Enabled subsites get their own RepoPress browse page in their admin

## Default Repository

The default community repository is: https://github.com/MightyX3N-Patrick/RepoPress

To submit a plugin, open a pull request adding your folder under `your-github-username/your-plugin-slug/`.
