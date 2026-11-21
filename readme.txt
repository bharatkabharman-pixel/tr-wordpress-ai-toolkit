=== TR WordPress AI Toolkit ===
Contributors: trsoftech
Tags: ai, mcp, model context protocol, claude, ai assistant, content management, seo, wordpress ai, natural language, chatgpt
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Claude, Copilot, Cursor and any MCP-compatible AI assistant to your WordPress site. Manage content, media, SEO and site health using natural language.

== Description ==

**TR WordPress AI Toolkit** bridges your WordPress site with AI assistants through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io). Once connected, you manage your site in plain English — no code, no manual dashboards.

**Example commands you can give your AI assistant:**

* "Show all published posts from this month."
* "Create a draft post titled 'AI Trends 2026' in the Technology category."
* "Find all posts missing a featured image and list them."
* "Give me an SEO health summary and show the top gaps."
* "Which plugins are active? Are any outdated?"
* "List editors registered in the last 30 days."
* "Find orphaned media files in the library."
* "What is the current PHP version and memory limit?"

**Supported AI clients:** Claude.ai Projects, Claude Desktop, Cursor, Windsurf, Codeium and any MCP 2025-11-25 / 2026-07-28 compliant client.

= Modules and tools =

* **Content (14 tools)** — List, search, create, update and trash posts and pages; manage categories and tags
* **Media (5 tools)** — Browse, search and update media library metadata; find orphaned attachments
* **SEO (5 tools)** — Health score estimate, missing meta titles/descriptions, missing featured images, per-post content structure analysis. Supports Yoast SEO, Rank Math and All in One SEO Pack automatically.
* **Site Health (7 tools)** — WordPress version, PHP info, active plugins, active theme, site health summary, permalink structure, cron status
* **Users (4 tools)** — List and search users, inspect roles and capabilities
* **WooCommerce (12 tools)** — Products, orders, customers, coupons, low-stock alerts and sales summary. Auto-detects WooCommerce; silently skipped when not active.
* **Comments (6 tools)** — List, approve, trash and reply to comments; moderation summary
* **Custom Post Types (5 tools)** — Discover and manage any public CPT (portfolio, testimonials, events, etc.) including custom fields

= Key features =

**One-Click Quick Connect** — Generate an Application Password and a ready-to-paste Claude.ai / Claude Desktop configuration JSON in a single click from the admin.

**Built-in MCP Server** — The plugin ships its own MCP endpoint (`/wp-json/tr-ai-toolkit/mcp`). No additional protocol plugin required for HTTP connections. Supports Streamable HTTP transport (MCP 2025-11-25 and 2026-07-28).

**Prompt Library** — 42 curated, categorized prompts for Claude.ai covering Content, SEO, Media, Site Health, Users, WooCommerce, Comments, Custom Post Types and Power Prompts. Copy any prompt with one click.

**Manual Credentials Encoder** — Enter a username and Application Password directly in the admin — the plugin computes the base64 Authorization header and full configuration JSON for you.

**Security built in:**

* Every tool has a WordPress capability check in its `permission_callback`
* Write operations require `edit_posts` or `upload_files`
* Admin operations require `manage_options`
* All inputs are validated and sanitized using WordPress APIs
* Sensitive data (passwords, tokens, auth keys, salts) is never exposed in tool output
* Audit log records every AI invocation with user ID, IP and status
* No arbitrary PHP execution, no shell commands, no unrestricted SQL

== Installation ==

1. Upload `tr-wordpress-ai-toolkit.zip` via **Plugins → Add New → Upload Plugin** and activate.
2. The plugin auto-installs MCP Adapter on activation (required for STDIO/Claude Desktop support).
3. Go to **TR AI Toolkit → Connection Guide → Quick Connect**.
4. Click **Generate Config** to create an Application Password and get your ready-to-paste AI client configuration.
5. Copy the configuration JSON into your AI client (Claude.ai Projects: Project Settings → MCP Servers).

**Minimum requirements:**

* WordPress 6.9 or higher
* PHP 8.1 or higher

== Frequently Asked Questions ==

= Do I need the MCP Adapter plugin? =

Not for HTTP connections. The plugin has its own built-in MCP server at `/wp-json/tr-ai-toolkit/mcp`. MCP Adapter is only required for STDIO transport (Claude Desktop / local server). It is automatically installed when you activate TR WordPress AI Toolkit.

= Which AI clients are supported? =

Any MCP-compatible client: Claude.ai Projects, Claude Desktop, Cursor, Windsurf, Codeium and others. Both HTTP and STDIO transports are supported.

= How does authentication work? =

The plugin uses WordPress Application Passwords (built into WordPress 5.6+). Use Quick Connect in the admin to generate one automatically, or create one manually at Users → Profile → Application Passwords.

= Is write access safe? =

Yes. Create, update and delete operations require `edit_posts` or `upload_files`. Admin operations (health, plugins, users) require `manage_options`. Read-only operations require `read`. Post deletion only moves items to trash — no permanent deletion via AI.

= Which SEO plugins are supported? =

Yoast SEO, Rank Math and All in One SEO Pack are auto-detected. The plugin reads SEO metadata from whichever is active.

= Does this work on WordPress Multisite? =

Single-site only. Multisite support is planned for a future release.

= Where is the MCP endpoint URL? =

`https://your-site.com/wp-json/tr-ai-toolkit/mcp`. The exact URL for your site is shown in **TR AI Toolkit → Connection Guide**.

= What is the Prompt Library? =

A curated set of 25 ready-to-use prompts you can copy and paste directly into Claude.ai or any other AI assistant. Categories cover Content Management, SEO, Media, Site Health, Users and Power Prompts (full site audits). Access it at **TR AI Toolkit → Prompt Library**.

== Screenshots ==

1. Dashboard showing MCP status, module overview and recent activity.
2. Connection Guide with Quick Connect — one-click Application Password generation and ready-to-paste config.
3. Prompt Library — 25 categorized prompts with copy button and category filter.
4. Settings page — configure modules, permissions and logging.
5. Activity Log showing recent AI tool invocations.

== Changelog ==

= 1.2.0 — 2026-11-21 =
* Added: WooCommerce module with 12 tools — products, orders, customers, coupons and sales summary. Auto-detects WooCommerce.
* Added: Comments module with 6 tools — list, approve, trash, reply and moderation summary.
* Added: Custom Post Types module with 5 tools — discover and manage any registered CPT including custom fields.
* Added: 17 new prompts in Prompt Library (WooCommerce, Comments, CPT categories).
* Total: 35 → 58 tools, 5 → 8 modules, 25 → 42 prompts.

= 1.1.0 — 2026-11-21 =
* Added: Prompt Library — 25 categorized ready-to-use AI prompts with copy button and category filter (Content, SEO, Media, Health, Users, Power Prompts)
* Added: Quick Connect — one-click Application Password generation with ready-to-paste Claude.ai config
* Added: Manual Credentials Encoder — enter username and app password, get base64 Authorization header and config JSON instantly
* Added: Auto-install MCP Adapter — plugin now auto-installs and activates MCP Adapter on activation
* Fixed: MCP tools/list returning empty — rewrote MCP server to implement protocol directly via WordPress REST API
* Fixed: 500 error in AJAX handler — removed `is_application_passwords_available_for_user()` call that caused PHP fatal on some WP versions
* Fixed: Removed `Requires Plugins: mcp-adapter` header that blocked plugin activation

= 1.0.0 — 2026-11-21 =
* Initial release.
* Content module: list, get, search, create, update and trash posts and pages.
* Taxonomy module: list and create categories and tags.
* Media module: list, search, get details, update metadata and find unused items.
* SEO module: summary, missing meta titles/descriptions, missing featured images, content structure analysis.
* Health module: WordPress info, PHP version, active plugins, theme, site health summary, permalink structure and cron status.
* Users module: list, get, search users and get roles.
* Admin dashboard, settings, connection guide and activity log.
* Full audit logging with configurable retention.

== Upgrade Notice ==

= 1.2.0 =
Major update: adds WooCommerce (12 tools), Comments (6 tools) and Custom Post Types (5 tools) modules. 58 total tools.

= 1.1.0 =
Recommended update: adds Quick Connect, Prompt Library and fixes MCP tools/list returning empty.

= 1.0.0 =
Initial release.
