# TR WordPress AI Toolkit

> Connect Claude, Copilot, Cursor and any MCP-compatible AI assistant to your WordPress site — manage content, media, SEO and site health using natural language.

[![Version](https://img.shields.io/badge/version-1.2.0-blue.svg)](https://github.com/bharatkabharman-pixel/tr-wordpress-ai-toolkit/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-21759b.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## What It Does

TR WordPress AI Toolkit gives AI assistants direct access to your WordPress site through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io). The plugin exposes **35 tools** across five modules — Content, Media, SEO, Health and Users — all secured by WordPress capability checks.

Once connected, you manage your site in plain English:

```
"Show all published posts from this month."
"Create a draft post titled 'AI Trends 2026' in the Technology category."
"Find all posts missing a featured image."
"Give me an SEO health summary and list gaps."
"Which plugins are active? Are any outdated?"
"List editors registered in the last 30 days."
"Find orphaned media files in the library."
"What is the current PHP version and memory limit?"
```

**Compatible clients:** Claude.ai Projects, Claude Desktop, Cursor, Windsurf, Codeium, and any MCP 2025-11-25 / 2026-07-28 compliant client.

---

## Table of Contents

- [Features](#features)
- [Quick Start](#quick-start)
- [Connecting to Claude.ai](#connecting-to-claudeai)
- [Prompt Library](#prompt-library)
- [Tools Reference](#tools-reference)
- [Security](#security)
- [Architecture](#architecture)
- [Project Structure](#project-structure)
- [Development](#development)
- [FAQ](#faq)
- [Changelog](#changelog)
- [License](#license)

---

## Features

### One-Click Quick Connect
Generate an Application Password and a ready-to-paste Claude.ai / Claude Desktop configuration in one click — no manual credential setup.

### Built-in MCP Server
The plugin ships its own MCP endpoint at `/wp-json/tr-ai-toolkit/mcp`. No separate protocol plugin required. Supports both **Streamable HTTP** (MCP 2025-11-25 and 2026-07-28) and **STDIO** transports.

### 42-Prompt Library
A categorized library of 42 ready-to-use prompts for Claude.ai — covering Content, SEO, Media, Site Health, Users, WooCommerce, Comments, Custom Post Types and Power Prompts. Copy any prompt with one click.

### Manual Credentials Encoder
Enter a WordPress username and Application Password directly in the admin — the plugin computes the base64 `Authorization` header and the full Claude.ai config JSON for you.

### 58 WordPress Abilities Across 8 Modules

| Module | Tools | Highlights |
|--------|-------|------------|
| Content | 14 | List, search, create, update, trash posts & pages; manage categories and tags |
| Media | 5 | Browse, search, update metadata, find orphaned files |
| SEO | 5 | Health score, missing titles/descriptions/images, per-post structure analysis |
| Health | 7 | WP version, PHP info, plugins, theme, cron, permalinks, memory |
| Users | 4 | List, search users, inspect roles and capabilities |
| WooCommerce | 12 | Products, orders, customers, coupons, sales summary (auto-detects WC) |
| Comments | 6 | List, approve, trash, reply to comments; moderation summary |
| Custom Post Types | 5 | Discover and manage any registered CPT with custom fields |

### Security First
Every tool has a `permission_callback` that checks the authenticated user's WordPress capabilities. Sensitive fields (passwords, auth keys, secrets) are never exposed in tool output.

### Audit Log
Every AI tool invocation is recorded — user ID, IP address, tool name and status — with configurable retention.

---

## Quick Start

### 1. Install the Plugin

Download the latest ZIP from the [Releases page](https://github.com/bharatkabharman-pixel/tr-wordpress-ai-toolkit/releases) and upload via **Plugins → Add New → Upload Plugin**, then activate.

The plugin auto-installs and activates MCP Adapter on first activation (required for STDIO transport).

### 2. Generate Your Credentials

Go to **TR AI Toolkit → Connection Guide → Quick Connect** and click **Generate Config**. The plugin creates an Application Password for the current admin user and displays the ready-to-paste configuration.

### 3. Connect Your AI Client

Copy the configuration JSON and add it to your AI client. For Claude.ai Projects, paste it in **Project Settings → MCP Servers**.

Done — your AI assistant now has direct access to WordPress.

---

## Connecting to Claude.ai

### HTTP Transport (Claude.ai Projects — Recommended)

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

Use **Quick Connect** in the admin to get the exact JSON with your real credentials pre-filled.

### STDIO Transport (Claude Desktop / Local)

```json
{
  "mcpServers": {
    "tr-ai-toolkit": {
      "command": "wp",
      "args": [
        "mcp-adapter", "serve",
        "--server=tr-ai-toolkit",
        "--user=admin",
        "--path=/path/to/wordpress"
      ]
    }
  }
}
```

### Authentication

The plugin uses **WordPress Application Passwords** (built into WordPress 5.6+). Generate one at **Users → Profile → Application Passwords**. No OAuth, no external tokens.

---

## Prompt Library

The admin includes a **Prompt Library** with 25 curated prompts across six categories:

| Category | Count | Example Prompt |
|----------|-------|----------------|
| Content Management | 7 | "List all draft posts and suggest which ones to publish first." |
| SEO Optimization | 6 | "Audit this site's SEO and list the top 5 issues to fix." |
| Media Library | 3 | "Find all media files not used in any post." |
| Site Health | 5 | "Check the site health and flag any critical issues." |
| Users | 3 | "Show all editors and their last activity." |
| WooCommerce | 7 | "Show all out of stock products and pending orders." |
| Comments | 6 | "Show all pending comments and approve the genuine ones." |
| Custom Post Types | 5 | "List all custom post types and how many entries each has." |
| Power Prompts | 6 | "Run a full site audit: SEO, health, WooCommerce, media and comments." |

Access it via **TR AI Toolkit → Prompt Library** in the WordPress admin.

---

## Tools Reference

### Content (14 tools)

| Tool | Description | Capability |
|------|-------------|------------|
| `tr-ai-toolkit/list-posts` | Paginated posts with status/category/date filters | `read` |
| `tr-ai-toolkit/get-post` | Full post details including meta and taxonomy | `read` |
| `tr-ai-toolkit/search-posts` | Full-text search across posts | `read` |
| `tr-ai-toolkit/create-post` | Create a post (defaults to draft) | `edit_posts` |
| `tr-ai-toolkit/update-post` | Partial update — title, content, status, meta | `edit_posts` |
| `tr-ai-toolkit/delete-post` | Move post to trash (no permanent deletion) | `edit_posts` |
| `tr-ai-toolkit/list-pages` | Paginated pages list | `read` |
| `tr-ai-toolkit/get-page` | Full page details | `read` |
| `tr-ai-toolkit/create-page` | Create a page (defaults to draft) | `edit_posts` |
| `tr-ai-toolkit/update-page` | Partial update for pages | `edit_posts` |
| `tr-ai-toolkit/list-categories` | All post categories with counts | `read` |
| `tr-ai-toolkit/create-category` | Create a category | `edit_posts` |
| `tr-ai-toolkit/list-tags` | All post tags with counts | `read` |
| `tr-ai-toolkit/create-tag` | Create a tag | `edit_posts` |

### Media (5 tools)

| Tool | Description | Capability |
|------|-------------|------------|
| `tr-ai-toolkit/list-media` | Paginated media library | `read` |
| `tr-ai-toolkit/get-media-details` | Full attachment details with all image sizes | `read` |
| `tr-ai-toolkit/search-media` | Search media by title or filename | `read` |
| `tr-ai-toolkit/update-media-metadata` | Update title, alt text, caption, description | `upload_files` |
| `tr-ai-toolkit/find-unused-media` | Orphaned attachments with no parent post | `upload_files` |

### SEO (5 tools)

| Tool | Description | Capability |
|------|-------------|------------|
| `tr-ai-toolkit/get-seo-summary` | Overall SEO health with score estimate | `read` |
| `tr-ai-toolkit/find-missing-meta-titles` | Posts without SEO title | `read` |
| `tr-ai-toolkit/find-missing-meta-descriptions` | Posts without meta description | `read` |
| `tr-ai-toolkit/find-posts-without-featured-images` | Posts without thumbnail | `read` |
| `tr-ai-toolkit/analyze-content-structure` | Per-post word count, headings, links, images | `read` |

SEO tools automatically detect and read from **Yoast SEO**, **Rank Math** and **All in One SEO Pack**.

### Health (7 tools)

| Tool | Description | Capability |
|------|-------------|------------|
| `tr-ai-toolkit/get-wordpress-info` | WP version, site URL, language, timezone | `manage_options` |
| `tr-ai-toolkit/get-php-version` | PHP version and loaded extensions | `manage_options` |
| `tr-ai-toolkit/get-active-plugins` | Active plugins with versions | `manage_options` |
| `tr-ai-toolkit/get-active-theme` | Active theme and block theme support | `manage_options` |
| `tr-ai-toolkit/get-site-health-summary` | Post/page counts, memory, HTTPS, debug status | `manage_options` |
| `tr-ai-toolkit/get-permalink-structure` | Permalink and URL base settings | `manage_options` |
| `tr-ai-toolkit/get-cron-status` | Scheduled WP-Cron events and overdue count | `manage_options` |

Admin email is masked in all health tool output.

### Users (4 tools)

| Tool | Description | Capability |
|------|-------------|------------|
| `tr-ai-toolkit/list-users` | Paginated user list (email masked) | `list_users` |
| `tr-ai-toolkit/get-user` | Single user profile (email masked) | `list_users` |
| `tr-ai-toolkit/search-users` | Search by display name or email | `list_users` |
| `tr-ai-toolkit/get-user-roles` | All roles with capability summary | `manage_options` |

### WooCommerce (12 tools)

> Auto-enabled when WooCommerce is active. Silently skipped when WooCommerce is not installed.

| Tool | Description | Capability |
|------|-------------|------------|
| `tr-ai-toolkit/wc-list-products` | Paginated product list with status/stock/category filters | `read` |
| `tr-ai-toolkit/wc-get-product` | Full product details | `read` |
| `tr-ai-toolkit/wc-search-products` | Search products by name or SKU | `read` |
| `tr-ai-toolkit/wc-create-product` | Create a simple product (defaults to draft) | `edit_products` |
| `tr-ai-toolkit/wc-update-product` | Update name, price, stock, SKU or status | `edit_products` |
| `tr-ai-toolkit/wc-get-low-stock-products` | Out of stock / backorder products | `edit_products` |
| `tr-ai-toolkit/wc-list-orders` | Paginated orders with status filter | `edit_shop_orders` |
| `tr-ai-toolkit/wc-get-order` | Full order details with line items and billing | `edit_shop_orders` |
| `tr-ai-toolkit/wc-update-order-status` | Change order status with optional note | `edit_shop_orders` |
| `tr-ai-toolkit/wc-list-customers` | Customers with order count and total spent (email masked) | `list_users` |
| `tr-ai-toolkit/wc-list-coupons` | Coupons with discount type, usage count and expiry | `edit_shop_orders` |
| `tr-ai-toolkit/wc-get-sales-summary` | Revenue and order counts for today / week / month / year | `view_woocommerce_reports` |

### Comments (6 tools)

| Tool | Description | Capability |
|------|-------------|------------|
| `tr-ai-toolkit/get-comment-summary` | Counts by status: pending, approved, spam, trash | `moderate_comments` |
| `tr-ai-toolkit/list-comments` | Paginated list filtered by status and optionally post ID | `moderate_comments` |
| `tr-ai-toolkit/get-comment` | Full comment details | `moderate_comments` |
| `tr-ai-toolkit/approve-comment` | Approve a pending comment | `moderate_comments` |
| `tr-ai-toolkit/trash-comment` | Move comment to trash (no permanent deletion) | `moderate_comments` |
| `tr-ai-toolkit/reply-to-comment` | Post a reply as the authenticated user | `moderate_comments` |

### Custom Post Types (5 tools)

Works with any public CPT registered on the site — portfolio, testimonials, events, team members and more.

| Tool | Description | Capability |
|------|-------------|------------|
| `tr-ai-toolkit/list-post-types` | All public CPTs with labels, archive info and published count | `read` |
| `tr-ai-toolkit/list-cpt-posts` | Paginated posts for any CPT by slug | `read` |
| `tr-ai-toolkit/get-cpt-post` | Full post details including all custom fields | `read` |
| `tr-ai-toolkit/create-cpt-post` | Create a CPT post with custom fields (defaults to draft) | `edit_posts` |
| `tr-ai-toolkit/update-cpt-post` | Update title, content, status or custom fields | `edit_posts` |

---

## Security

| Area | Implementation |
|------|---------------|
| Authentication | WordPress Application Passwords — standard HTTP Basic auth |
| Authorization | Per-tool `permission_callback` checks WordPress capabilities |
| Write operations | Require `edit_posts` or `upload_files` |
| Admin operations | Require `manage_options` |
| Input sanitization | WordPress `sanitize_*` functions throughout |
| Output escaping | All admin views use `esc_html`, `esc_attr`, `esc_url` |
| Sensitive data | Passwords, tokens, auth keys and salts never appear in tool output |
| Audit log | Every invocation logged: user ID, IP, tool name, status |
| No dangerous ops | No arbitrary PHP execution, no shell commands, no unrestricted SQL |

---

## Architecture

```
WordPress 6.9+
├── TR WordPress AI Toolkit (this plugin)
│   ├── Built-in MCP Server  ← /wp-json/tr-ai-toolkit/mcp
│   │   ├── Implements MCP protocol directly (Streamable HTTP)
│   │   └── Session management via WP transients
│   ├── Abilities Manager
│   │   ├── Content Module      (14 tools)
│   │   ├── Media Module        (5 tools)
│   │   ├── SEO Module          (5 tools)
│   │   ├── Health Module       (7 tools)
│   │   ├── Users Module        (4 tools)
│   │   ├── WooCommerce Module  (12 tools — auto-detects WC)
│   │   ├── Comments Module     (6 tools)
│   │   └── CPT Module          (5 tools)
│   └── Admin UI
│       ├── Dashboard, Settings, Activity Log
│       ├── Connection Guide + Quick Connect
│       └── Prompt Library (42 prompts)
└── MCP Adapter (auto-installed — required for STDIO transport)
```

The plugin implements the MCP protocol directly via the WordPress REST API. MCP Adapter is auto-installed for STDIO support but is not required for HTTP connections.

---

## Project Structure

```
tr-wordpress-ai-toolkit/
├── tr-wordpress-ai-toolkit.php      Plugin entry point
├── uninstall.php                    Clean removal of all plugin data
├── composer.json
├── readme.txt                       WordPress.org listing
├── includes/
│   ├── class-plugin.php             Bootstrap singleton
│   ├── class-activator.php          Activation (DB table, options, MCP Adapter install)
│   ├── class-deactivator.php        Deactivation
│   ├── class-abilities-manager.php  Ability registration orchestrator
│   ├── class-permissions.php        Capability checks
│   ├── class-logger.php             Audit logger
│   └── class-settings.php          Settings manager
├── modules/
│   ├── content/class-content-module.php
│   ├── media/class-media-module.php
│   ├── seo/class-seo-module.php
│   ├── health/class-health-module.php
│   └── users/class-users-module.php
├── admin/
│   ├── class-admin.php
│   ├── views/
│   │   ├── dashboard.php
│   │   ├── settings.php
│   │   ├── connect.php              Quick Connect + Manual Encoder
│   │   ├── prompts.php              Prompt Library (25 prompts)
│   │   └── logs.php
│   ├── css/admin.css
│   └── js/admin.js
├── integrations/
│   └── mcp-adapter/class-mcp-server.php   MCP protocol handler
├── tests/phpunit/
├── docs/
└── .github/workflows/
```

---

## Development

**Requirements:** PHP 8.1+, Composer, WordPress test suite.

```bash
# Clone the repository
git clone https://github.com/bharatkabharman-pixel/tr-wordpress-ai-toolkit.git
cd tr-wordpress-ai-toolkit

# Install all dependencies (including dev tools)
composer install

# Run PHP Code Standards check
composer lint

# Auto-fix code standards
composer lint-fix

# Run PHPUnit tests
composer test
```

### Building a Release ZIP

```bash
composer install --no-dev --optimize-autoloader

zip -r tr-wordpress-ai-toolkit.zip . \
  --exclude="*.git*" \
  --exclude="tests/*" \
  --exclude="vendor/*/test*" \
  --exclude="composer.*" \
  --exclude="phpunit.xml" \
  --exclude=".github/*"
```

---

## FAQ

**Do I need the MCP Adapter plugin?**
Not for HTTP connections. The plugin has its own built-in MCP server at `/wp-json/tr-ai-toolkit/mcp`. MCP Adapter is only required for STDIO transport (Claude Desktop). It is auto-installed on plugin activation.

**Which AI clients work?**
Any MCP 2025-11-25 or 2026-07-28 compliant client: Claude.ai Projects, Claude Desktop, Cursor, Windsurf, Codeium and others.

**How do I authenticate?**
WordPress Application Passwords (built into WP 5.6+). Use Quick Connect in the admin to generate one automatically, or create one at Users → Profile → Application Passwords.

**Is write access safe?**
Yes. Create/update/delete operations require `edit_posts` or `upload_files`. Admin operations (plugins, theme, users) require `manage_options`. Read-only operations require `read`. Post deletion moves to trash only — no permanent deletion.

**Which SEO plugins are supported?**
Yoast SEO, Rank Math, and All in One SEO Pack are auto-detected. The plugin reads SEO metadata from whichever plugin is active.

**Does this work on WordPress Multisite?**
Single-site only. Multisite support is planned for a future release.

**Where is the MCP endpoint URL?**
`https://your-site.com/wp-json/tr-ai-toolkit/mcp`. The exact URL for your site is shown in **TR AI Toolkit → Connection Guide**.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full version history.

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

---

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) © [True Response Softech Pvt. Ltd.](https://trsoftech.com)
