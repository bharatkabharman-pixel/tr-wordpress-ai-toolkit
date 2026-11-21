# TR WordPress AI Toolkit

**Connect AI assistants to your WordPress site via the Model Context Protocol.**

Developer: [True Response Softech Pvt. Ltd.](https://trsoftech.com)  
Version: 1.0.0  
Requires WordPress: 6.9+  
Requires PHP: 8.1+  
Requires Plugin: [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/)  
License: GPL-2.0-or-later

---

## What It Does

TR WordPress AI Toolkit registers 35 WordPress Abilities using the native Abilities API (built into WordPress 6.9+). The official MCP Adapter plugin exposes these abilities to any MCP-compatible AI client (Claude, Copilot, Cursor, Windsurf and others).

Once connected, you can manage your WordPress site using natural language:

```
"Show all published posts."
"Create a draft post titled 'AI Trends 2026'."
"Find posts without featured images."
"Give me an SEO summary of this site."
"What plugins are active?"
"Show users registered this month."
```

---

## Architecture

```
WordPress Site
├── WordPress 6.9+ (Abilities API built in)
├── MCP Adapter plugin (required — exposes abilities via MCP)
└── TR WordPress AI Toolkit (this plugin — registers abilities)
    ├── Content Module    (14 abilities)
    ├── Media Module      (5 abilities)
    ├── SEO Module        (5 abilities)
    ├── Health Module     (7 abilities)
    └── Users Module      (4 abilities)
```

The plugin does NOT implement the MCP protocol itself. It uses the official WordPress MCP Adapter for all protocol handling.

---

## Abilities Reference

### Content (14 abilities)

| Ability | Description | Min. Capability |
|---------|-------------|-----------------|
| `tr-ai-toolkit/list-posts` | Paginated list of posts | `read` |
| `tr-ai-toolkit/get-post` | Full post details | `read` |
| `tr-ai-toolkit/search-posts` | Full-text post search | `read` |
| `tr-ai-toolkit/create-post` | Create a new post | `edit_posts` |
| `tr-ai-toolkit/update-post` | Update an existing post | `edit_posts` |
| `tr-ai-toolkit/delete-post` | Move post to trash | `edit_posts` |
| `tr-ai-toolkit/list-pages` | Paginated list of pages | `read` |
| `tr-ai-toolkit/get-page` | Full page details | `read` |
| `tr-ai-toolkit/create-page` | Create a new page | `edit_posts` |
| `tr-ai-toolkit/update-page` | Update an existing page | `edit_posts` |
| `tr-ai-toolkit/list-categories` | All post categories | `read` |
| `tr-ai-toolkit/create-category` | Create a category | `edit_posts` |
| `tr-ai-toolkit/list-tags` | All post tags | `read` |
| `tr-ai-toolkit/create-tag` | Create a tag | `edit_posts` |

### Media (5 abilities)

| Ability | Description | Min. Capability |
|---------|-------------|-----------------|
| `tr-ai-toolkit/list-media` | Browse media library | `read` |
| `tr-ai-toolkit/get-media-details` | Full attachment details | `read` |
| `tr-ai-toolkit/search-media` | Search media library | `read` |
| `tr-ai-toolkit/update-media-metadata` | Update title, alt, caption | `upload_files` |
| `tr-ai-toolkit/find-unused-media` | Find orphaned attachments | `upload_files` |

### SEO (5 abilities)

| Ability | Description | Min. Capability |
|---------|-------------|-----------------|
| `tr-ai-toolkit/get-seo-summary` | Site SEO health overview | `read` |
| `tr-ai-toolkit/find-missing-meta-titles` | Posts without SEO titles | `read` |
| `tr-ai-toolkit/find-missing-meta-descriptions` | Posts without meta descriptions | `read` |
| `tr-ai-toolkit/find-posts-without-featured-images` | Posts without thumbnails | `read` |
| `tr-ai-toolkit/analyze-content-structure` | Per-post SEO analysis | `read` |

SEO supports Yoast SEO, Rank Math and All in One SEO Pack automatically.

### Health (7 abilities)

| Ability | Description | Min. Capability |
|---------|-------------|-----------------|
| `tr-ai-toolkit/get-wordpress-info` | WP version, URLs, timezone | `manage_options` |
| `tr-ai-toolkit/get-php-version` | PHP version and extensions | `manage_options` |
| `tr-ai-toolkit/get-active-plugins` | Active plugin list | `manage_options` |
| `tr-ai-toolkit/get-active-theme` | Active theme details | `manage_options` |
| `tr-ai-toolkit/get-site-health-summary` | Counts, memory, debug status | `manage_options` |
| `tr-ai-toolkit/get-permalink-structure` | Permalink and URL config | `manage_options` |
| `tr-ai-toolkit/get-cron-status` | WP-Cron events | `manage_options` |

### Users (4 abilities)

| Ability | Description | Min. Capability |
|---------|-------------|-----------------|
| `tr-ai-toolkit/list-users` | Paginated user list | `list_users` |
| `tr-ai-toolkit/get-user` | Single user profile | `list_users` |
| `tr-ai-toolkit/search-users` | Search by name or email | `list_users` |
| `tr-ai-toolkit/get-user-roles` | All roles and key caps | `manage_options` |

---

## Installation

### From WordPress Admin

1. Install **MCP Adapter** plugin (Plugins → Add New → search "MCP Adapter").
2. Install this plugin (Plugins → Add New → Upload Plugin → select ZIP).
3. Activate both plugins.
4. Go to **TR AI Toolkit → Connection Guide**.

### From GitHub (Development)

```bash
git clone https://github.com/trsoftech/tr-wordpress-ai-toolkit.git
cd tr-wordpress-ai-toolkit
composer install --no-dev
```

---

## Connecting an AI Client

### HTTP Transport (Recommended)

Point any MCP-compatible client at:

```
https://your-site.com/wp-json/tr-ai-toolkit/mcp
```

Authenticate with WordPress Application Passwords (Users → Profile → Application Passwords).

**Claude.ai Projects:**
```json
{
  "mcpServers": {
    "tr-ai-toolkit": {
      "type": "http",
      "url": "https://your-site.com/wp-json/tr-ai-toolkit/mcp",
      "headers": {
        "Authorization": "Basic BASE64(username:application_password)"
      }
    }
  }
}
```

### STDIO Transport (Claude Desktop)

```json
{
  "mcpServers": {
    "tr-ai-toolkit": {
      "command": "wp",
      "args": ["mcp-adapter", "serve", "--server=tr-ai-toolkit", "--user=admin", "--path=/path/to/wordpress"]
    }
  }
}
```

---

## Security

- Every ability has a WordPress capability check in its `permission_callback`
- Write operations require `edit_posts` or higher
- Sensitive fields (passwords, tokens, hashes, auth keys) are never exposed
- Audit log records every invocation with user ID, IP and status
- Input is validated and sanitized using WordPress APIs
- Output is escaped for display
- No arbitrary PHP execution, shell commands or unrestricted SQL

---

## Project Structure

```
tr-wordpress-ai-toolkit/
├── tr-wordpress-ai-toolkit.php   Main plugin entry
├── uninstall.php
├── composer.json
├── readme.txt
├── README.md
├── includes/
│   ├── class-plugin.php           Bootstrap singleton
│   ├── class-activator.php        Activation (DB table, options)
│   ├── class-deactivator.php      Deactivation
│   ├── class-abilities-manager.php Ability registration orchestrator
│   ├── class-permissions.php      Capability checks
│   ├── class-logger.php           Audit logger
│   └── class-settings.php        Settings manager
├── modules/
│   ├── content/class-content-module.php
│   ├── media/class-media-module.php
│   ├── seo/class-seo-module.php
│   ├── health/class-health-module.php
│   └── users/class-users-module.php
├── admin/
│   ├── class-admin.php
│   ├── views/dashboard.php
│   ├── views/settings.php
│   ├── views/connect.php
│   ├── views/logs.php
│   ├── css/admin.css
│   └── js/admin.js
├── integrations/
│   └── mcp-adapter/class-mcp-server.php
├── tests/phpunit/
├── docs/
└── .github/workflows/
```

---

## Development

```bash
# Run tests
composer test

# PHP code standards check
composer lint

# Auto-fix code standards
composer lint-fix
```

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
