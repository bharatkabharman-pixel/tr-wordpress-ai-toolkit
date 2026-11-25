# TR WordPress AI Toolkit

> Connect Claude, Copilot, Cursor and any MCP-compatible AI assistant to your WordPress site — manage content, media, SEO, plugins, themes, menus, database cleanup, security, backups and more using natural language.

[![Version](https://img.shields.io/badge/version-1.7.5-blue.svg)](https://github.com/bharatkabharman-pixel/tr-wordpress-ai-toolkit/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-21759b.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## What It Does

TR WordPress AI Toolkit gives AI assistants direct access to your WordPress site through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io). The plugin exposes **148 tools** across **17 modules** — all secured by WordPress capability checks.

Once connected, you manage your site in plain English:

```
"Show all published posts from this month."
"Create a draft post titled 'AI Trends 2026' in the Technology category."
"Which plugins are active? Are any outdated?"
"Give me an SEO health summary and list gaps."
"List editors registered in the last 30 days."
"Find orphaned media files in the library."
"Show me all WooCommerce orders from last week."
"Run a full database cleanup and optimize tables."
"Check the security score and list all admin users."
"What is the current PHP version and memory limit?"
```

**Compatible clients:** Claude.ai Projects, Claude Desktop, Cursor, Windsurf, Codeium, and any MCP 2025-03-26 / 2025-11-25 compliant client.

---

## Table of Contents

- [Features](#features)
- [Quick Start](#quick-start)
- [Connecting to Claude.ai](#connecting-to-claudeai)
- [Modules and Tools](#modules-and-tools)
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
The plugin ships its own MCP endpoint at `/wp-json/tr-ai-toolkit/mcp`. No separate protocol plugin required. Supports both **Streamable HTTP** (MCP 2025-03-26) and **STDIO** transports.

### Per-Tool Access Management
Enable or disable individual MCP tools from the admin panel without disabling entire modules. Disabled tools are blocked in both the tool list and execution layer.

### 42-Prompt Library
A categorized library of 42 ready-to-use prompts for Claude.ai — covering Content, SEO, Media, Site Health, Users, WooCommerce, Comments, Custom Post Types and Power Prompts. Copy any prompt with one click.

### Manual Credentials Encoder
Enter a WordPress username and Application Password directly in the admin — the plugin computes the base64 `Authorization` header and the full Claude.ai config JSON for you.

### Automatic GitHub Updates
The plugin checks for updates directly from GitHub releases. Update notices appear in the standard WordPress Updates screen, and one-click updates work just like WordPress.org plugins.

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

## Modules and Tools

**148 tools across 17 modules.**

| Module | Tools | Highlights |
|--------|------:|------------|
| Content | 14 | List, search, create, update, trash posts & pages; manage categories and tags |
| Media | 7 | Browse, search, update metadata, find orphaned files, upload from URL, find missing alt text |
| SEO | 6 | Health score, missing titles/descriptions/images, per-post structure analysis, write SEO meta |
| Site Health | 7 | WP version, PHP info, plugins, theme, cron, permalinks, memory |
| Users | 7 | List, search, create, update and delete users; inspect roles and capabilities |
| WooCommerce | 14 | Products, orders, customers, coupons, create coupon, inventory, low-stock alerts, sales summary |
| Comments | 8 | List, approve, unapprove, trash, spam, reply; moderation summary |
| Custom Post Types | 5 | Discover and manage any public CPT with custom fields |
| Performance | 12 | PageSpeed Insights, Core Web Vitals; clear cache (WP Rocket, LiteSpeed, W3TC, WP Super Cache); image optimization and WebP conversion |
| Management | 8 | List/activate/deactivate plugins; check for updates; list/activate themes; read and update site settings |
| Diagnostics | 6 | Server info, PHP config and extensions, DB table sizes, disk usage, error log viewer, WP-Cron jobs |
| Menus | 9 | List nav menus and location assignments; get, add, update and delete menu items; create menus; list widget areas and widgets |
| Database | 8 | Cleanup report; delete revisions, spam, trash, expired transients, orphaned meta; optimize tables; permanently delete a post |
| Security | 7 | Security score report, file permission check, admin user audit, inactive plugin risk, core integrity check, SSL status, AI activity log |
| Backup | 5 | Detect active backup plugin, list backup files, trigger backup, view schedule, backup storage usage |
| Redirects | 2 | List and manage redirects (requires a redirect plugin) |
| Taxonomy | 3 | List, create and manage custom taxonomies and their terms |

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
| Tool access control | Per-tool enable/disable from admin panel |
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
│   │   ├── Content Module        (14 tools)
│   │   ├── Media Module          (7 tools)
│   │   ├── SEO Module            (6 tools)
│   │   ├── Health Module         (7 tools)
│   │   ├── Users Module          (7 tools)
│   │   ├── WooCommerce Module    (14 tools — auto-detects WC)
│   │   ├── Comments Module       (8 tools)
│   │   ├── CPT Module            (5 tools)
│   │   ├── Performance Module    (12 tools)
│   │   ├── Management Module     (8 tools)
│   │   ├── Diagnostics Module    (6 tools)
│   │   ├── Menus Module          (9 tools)
│   │   ├── Database Module       (8 tools)
│   │   ├── Security Module       (7 tools)
│   │   ├── Backup Module         (5 tools)
│   │   ├── Redirects Module      (2 tools)
│   │   └── Taxonomy Module       (3 tools)
│   ├── GitHub Updater            (auto-update from GitHub releases)
│   └── Admin UI
│       ├── Dashboard, Settings (with per-tool access control)
│       ├── Connection Guide + Quick Connect
│       ├── Activity Log
│       └── Prompt Library (42 prompts)
└── MCP Adapter (auto-installed — required for STDIO transport)
```

---

## Project Structure

```
tr-wordpress-ai-toolkit/
├── tr-wordpress-ai-toolkit.php          Plugin entry point
├── uninstall.php                        Clean removal of all plugin data
├── readme.txt                           WordPress.org / plugin header
├── includes/
│   ├── class-plugin.php                 Bootstrap singleton
│   ├── class-activator.php              Activation (DB table, options, MCP Adapter install)
│   ├── class-deactivator.php            Deactivation
│   ├── class-abilities-manager.php      Tool registration orchestrator
│   ├── class-github-updater.php         GitHub releases auto-updater
│   ├── class-permissions.php            Capability checks
│   ├── class-logger.php                 Audit logger
│   └── class-settings.php              Settings manager
├── modules/
│   ├── content/class-content-module.php
│   ├── media/class-media-module.php
│   ├── seo/class-seo-module.php
│   ├── health/class-health-module.php
│   ├── users/class-users-module.php
│   ├── woocommerce/class-woocommerce-module.php
│   ├── comments/class-comments-module.php
│   ├── cpt/class-cpt-module.php
│   ├── performance/class-performance-module.php
│   ├── management/class-management-module.php
│   ├── diagnostics/class-diagnostics-module.php
│   ├── menus/class-menus-module.php
│   ├── database/class-database-module.php
│   ├── security/class-security-module.php
│   ├── backup/class-backup-module.php
│   ├── redirects/class-redirects-module.php
│   └── taxonomy/class-taxonomy-module.php
├── admin/
│   ├── class-admin.php
│   ├── views/
│   │   ├── dashboard.php
│   │   ├── settings.php                 Includes per-tool access control
│   │   ├── connect.php                  Quick Connect + Manual Encoder
│   │   ├── prompts.php                  Prompt Library (42 prompts)
│   │   └── logs.php
│   ├── css/admin.css
│   └── js/admin.js
└── integrations/
    └── mcp-adapter/class-mcp-server.php MCP protocol handler
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

---

## FAQ

**Do I need the MCP Adapter plugin?**
Not for HTTP connections. The plugin has its own built-in MCP server at `/wp-json/tr-ai-toolkit/mcp`. MCP Adapter is only required for STDIO transport (Claude Desktop). It is auto-installed on plugin activation.

**Which AI clients work?**
Any MCP 2025-03-26 or 2025-11-25 compliant client: Claude.ai Projects, Claude Desktop, Cursor, Windsurf, Codeium and others.

**How do I authenticate?**
WordPress Application Passwords (built into WP 5.6+). Use Quick Connect in the admin to generate one automatically, or create one at Users → Profile → Application Passwords.

**Is write access safe?**
Yes. Create/update/delete operations require `edit_posts` or `upload_files`. Admin operations require `manage_options`. Read-only operations require `read`. Post deletion moves to trash only by default.

**Can I disable individual tools?**
Yes. Go to **Settings → Tool Access** to enable or disable any individual tool. Disabled tools are removed from the tool list and blocked at the execution layer.

**How do updates work?**
The plugin checks GitHub releases automatically. Update notices appear in WordPress → Dashboard → Updates and can be applied with one click, just like WordPress.org plugins.

**Which SEO plugins are supported?**
Yoast SEO, Rank Math, and All in One SEO Pack are auto-detected.

**Does this work on WordPress Multisite?**
Single-site only. Multisite support is planned for a future release.

**Where is the MCP endpoint URL?**
`https://your-site.com/wp-json/tr-ai-toolkit/mcp`. The exact URL for your site is shown in **TR AI Toolkit → Connection Guide**.

---

## Changelog

### 1.7.5
- Fixed: Performance module tools (PageSpeed, cache, image) were crashing with "Tool execution failed" because `$settings` was null — abilities manager now uses reflection to pass settings only to modules that accept it

### 1.7.4
- Fixed: Dashboard showed "Active Modules: 0/17" — `get_modules()` now triggers module loading if the abilities hook hadn't fired yet at render time

### 1.7.3
- Added per-tool enable/disable controls in Settings → Tool Access
- Disabled tools are removed from the MCP tool list and blocked at execution (403)
- Fixed settings save handler to correctly compute disabled tools

### 1.7.2
- Fixed admin dashboard to show all 17 modules dynamically (removed hardcoded module lists)
- Fixed Settings page to list all 17 modules in Exposed Modules checkboxes
- Fixed settings save handler stripping new module slugs

### 1.7.1
- Added GitHub auto-updater so WordPress detects and applies updates from GitHub releases

### 1.7.0
- Added Database module (8 tools): cleanup report, revisions, spam, trash, transients, orphaned meta, table optimization, permanent delete
- Added Security module (7 tools): security score, file permissions, admin audit, inactive plugins, core integrity, SSL status, activity log
- Added Backup module (5 tools): detect backup plugin, list files, trigger backup, schedule, storage info

### 1.6.0
- Added Management module (8 tools): plugins, themes, site settings
- Added Diagnostics module (6 tools): server info, PHP config, DB stats, disk, error log, cron
- Added Menus module (9 tools): nav menus, menu items, widget areas

### 1.5.0
- Added Performance module (12 tools): PageSpeed Insights, cache management, image optimization
- Added PageSpeed API key setting

### 1.4.0
- Added Comments module (8 tools)
- Added Custom Post Types module (5 tools)
- Added Redirects module (2 tools)
- Added Taxonomy module (3 tools)
- Added WooCommerce module (14 tools, auto-detected)

### 1.3.0
- Initial public release with 5 core modules: Content, Media, SEO, Health, Users

---

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) © [True Response Softech Pvt. Ltd.](https://trsoftech.com)
