=== TR WordPress AI Toolkit ===
Contributors: trsoftech
Tags: ai, mcp, model context protocol, claude, ai assistant, content management, seo, wordpress ai, natural language, woocommerce ai
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.4.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Claude, Copilot, Cursor and any MCP-compatible AI assistant to your WordPress site. Manage content, media, SEO, redirects, categories, tags, users, WooCommerce and site health using natural language. 93 tools across 10 modules.

== Description ==

**TR WordPress AI Toolkit** bridges your WordPress site with AI assistants through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io). Once connected, you manage your entire site in plain English — no code, no manual dashboards.

**Example commands you can give your AI assistant:**

* "Show all published posts from this month."
* "Create a draft post titled 'AI Trends 2026' in the Technology category."
* "Find all posts missing a featured image and list them."
* "Give me an SEO health summary and show the top gaps."
* "Which plugins are active? Are any outdated?"
* "List editors registered in the last 30 days."
* "Find orphaned media files in the library."
* "What is the current PHP version and memory limit?"
* "Show me all WooCommerce orders from last week."
* "List products that are low on stock."
* "Show pending comments and approve the ones from regulars."
* "List all portfolio items and show their custom fields."

**Supported AI clients:** Claude.ai Projects, Claude Desktop, Cursor, Windsurf, Codeium and any MCP 2025-03-26 / 2025-11-25 compliant client.

= Modules and Tools (68 total) =

* **Content (14 tools)** — List, search, create, update and trash posts and pages; manage categories and tags
* **Media (7 tools)** — Browse, search and update media library metadata; find orphaned attachments; upload from URL with SSRF protection; find images missing alt text
* **SEO (6 tools)** — Health score estimate, missing meta titles/descriptions, missing featured images, per-post content structure analysis, write SEO meta. Supports Yoast SEO, Rank Math and All in One SEO Pack automatically.
* **Site Health (7 tools)** — WordPress version, PHP info, active plugins, active theme, site health summary, permalink structure, cron status
* **Users (7 tools)** — List, search, create, update and delete users; inspect roles and capabilities
* **WooCommerce (14 tools)** — Products, orders, customers, coupons, create coupon, inventory summary, low-stock alerts and sales summary. Auto-detects WooCommerce; silently skipped when not active.
* **Comments (8 tools)** — List, approve, unapprove, trash, spam and reply to comments; moderation summary
* **Custom Post Types (5 tools)** — Discover and manage any public CPT (portfolio, testimonials, events, etc.) including custom fields

= Key Features =

**One-Click Quick Connect** — Generate an Application Password and a ready-to-paste Claude.ai / Claude Desktop configuration JSON in a single click from the admin. Revoke access at any time with one click.

**Built-in MCP Server** — The plugin ships its own MCP endpoint (`/wp-json/tr-ai-toolkit/mcp`). No additional protocol plugin required for HTTP connections. Supports Streamable HTTP transport (MCP 2025-03-26 and later).

**Prompt Library** — 42 curated, categorized prompts for Claude.ai covering Content, SEO, Media, Site Health, Users, WooCommerce, Comments, Custom Post Types and Power Prompts. Copy any prompt with one click.

**Manual Credentials Encoder** — Enter a username and Application Password directly in the admin — the plugin computes the base64 Authorization header and full configuration JSON for you.

**Activity Log with Filter and Clear** — Filter log entries by ability name or status (success / denied / error) in real time. Clear all entries with one click.

**Test MCP Connection** — Verify the MCP server is reachable from the Connection Guide admin page without leaving WordPress.

**Security built in:**

* Every tool has a WordPress capability check in its `permission_callback`
* MCP sessions are bound to the creating user — cross-user session deletion is blocked
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

The plugin uses WordPress Application Passwords (built into WordPress 5.6+). Use Quick Connect in the admin to generate one automatically, or create one manually at **Users → Profile → Application Passwords**.

= Is write access safe? =

Yes. Create, update and delete operations require `edit_posts` or `upload_files`. Admin operations (health, plugins, users) require `manage_options`. Read-only operations require `read`. Post deletion only moves items to trash — no permanent deletion via AI.

= Which SEO plugins are supported? =

Yoast SEO, Rank Math and All in One SEO Pack are auto-detected. The plugin reads SEO metadata from whichever is active. If none are installed, native WordPress meta fields are used.

= Does WooCommerce need to be installed? =

No. The WooCommerce module auto-detects whether WooCommerce is active. If it is not installed or not active, the 12 WooCommerce tools are silently skipped and do not appear in the tool list.

= Does this work on WordPress Multisite? =

Single-site only. Multisite support is planned for a future release.

= Where is the MCP endpoint URL? =

`https://your-site.com/wp-json/tr-ai-toolkit/mcp`. The exact URL for your site is shown in **TR AI Toolkit → Connection Guide**.

= What is the Prompt Library? =

42 curated, ready-to-use prompts you can copy and paste directly into Claude.ai or any other AI assistant. Categories cover Content Management, SEO, Media, Site Health, Users, WooCommerce, Comments, Custom Post Types and Power Prompts (full site audits). Access it at **TR AI Toolkit → Prompt Library**.

= How do I disconnect an AI assistant? =

Go to **TR AI Toolkit → Connection Guide**. If a Quick Connect credential exists, a **Revoke Access** button is shown. Clicking it deletes the Application Password — the AI assistant can no longer connect. Manually created Application Passwords must be revoked from **Users → Profile → Application Passwords**.

= The tools appear in Claude but it says they don't exist when I try to use them. =

Upgrade to v1.2.2 or later. Versions before 1.2.2 used forward slashes in tool names (e.g. `tr-ai-toolkit/list-posts`) which are not valid MCP identifiers. Claude.ai accepted them in the tool list but refused to call them. After upgrading, start a new Claude.ai Project conversation so the updated tool list is fetched.

== Screenshots ==

1. Dashboard showing MCP status, module overview and recent activity.
2. Connection Guide with Quick Connect — one-click Application Password generation and ready-to-paste config.
3. Prompt Library — 42 categorized prompts with copy button and category filter.
4. Settings page — configure modules, permissions and logging.
5. Activity Log showing recent AI tool invocations.

== Changelog ==

= 1.4.0 — 2026-11-24 =
* New: SEO module — `get-post-seo` returns all SEO fields in one call: meta title, description, focus keyword, canonical URL, robots directives, Open Graph, and Twitter Card data.
* Enhanced: `update-post-seo` now supports focus_keyword, canonical_url, robots (noindex/nofollow/noarchive/nosnippet), og_title, og_description, and og_image_url. Only supplied fields are updated.
* Enhanced: `analyze-content-structure` now returns paragraph count, images missing alt text, empty links, H1 count, heading hierarchy issues, and a structured issues/warnings/recommendations object.
* New: `get-robots-txt` returns robots.txt content, source (physical or virtual), discourage-indexing status, and SEO plugin sitemap hints.
* New: `get-sitemap-status` detects the active sitemap provider (WordPress core, Yoast, Rank Math, AIOSEO) and returns expected sitemap URLs.
* New module: Redirects (6 tools) — list, get, create, update, delete redirects via the Redirection plugin; loop and chain detection.
* New module: Taxonomy (10 tools) — list, get, update, delete, and merge categories and tags; SEO metadata reading per term.
* New permission: `can_manage_terms()` — required for category/tag write operations.
* Total: 93 tools across 10 modules (up from 68 across 8).

= 1.3.1 — 2026-11-24 =
* Fixed: Fatal error "Cannot use 'true' as class name" on PHP 8.1 — `true` as a standalone return type requires PHP 8.2+. Changed return type of `Permissions::require()` to `bool|\WP_Error`.

= 1.3.0 — 2026-11-23 =
* Security: Fixed critical session hijacking vulnerability — any authenticated user could delete another user's MCP session. Sessions are now bound to the creating user; deletion verifies ownership.
* Fixed: `find-unused-media` always returned empty — wrong meta_query was checking `_thumbnail_id` on the attachment instead of on posts. Fixed with correct SQL query.
* Fixed: WooCommerce product listing called `wc_get_products(limit=-1)` twice; count always returned 1 due to int cast on array. Fixed with single count query.
* Fixed: CPT custom field values containing arrays were corrupted to string "Array" by `sanitize_text_field`. New recursive sanitizer preserves arrays, booleans and numbers.
* Fixed: SEO `find-missing-meta` pagination was inconsistent — fetched too many rows in PHP then filtered. Rewritten using database-level meta_query with proper paged support.
* Fixed: WooCommerce, Comments and CPT module tool names still used forward slashes (`tr-ai-toolkit/toolname`). All renamed to hyphen format (`tr-ai-toolkit-toolname`). All 58 tools now have valid MCP identifiers.
* Fixed: `max_execution_time = -1` now shows as `unlimited` instead of raw `-1`.
* Fixed: WooCommerce email masking format normalized to `***@domain.com` to match all other modules.
* Added: Activity Log filter bar — search by ability name, filter by status (success/denied/error), live row count.
* Added: Clear All Logs button with confirmation dialog on the Activity Log page.
* Added: Test MCP Connection section on Connection Guide — fires internal ping and shows pass/fail.
* Added: Helpful description text under Enable Plugin, Log Level, Permissions and Log Retention settings.
* Added: 8 new documentation files (CONNECTION_GUIDE, CLAUDE_DESKTOP, CLAUDE_CODE, MCP_PROTOCOL, AVAILABLE_TOOLS, PERMISSIONS, TROUBLESHOOTING, SECURITY_REVIEW).

= 1.2.2 — 2026-11-21 =
* Fixed: All original module tools (Content, Media, SEO, Health, Users) were not callable from Claude.ai — tool names contained forward slashes which are not valid MCP identifiers. Renamed from `tr-ai-toolkit/toolname` to `tr-ai-toolkit-toolname`. WooCommerce, Comments and CPT modules were already using valid names and are unaffected.
* Fixed: PHP bug in abilities manager where `$module_map` was referenced before it was defined.

= 1.2.1 — 2026-11-21 =
* Fixed: WooCommerce, Comments and CPT modules showing as DISABLED after upgrading from v1.1.0.
* Fixed: Settings page was missing checkboxes for the 3 new modules.
* Fixed: Settings save handler was filtering out new module slugs.

= 1.2.0 — 2026-11-21 =
* Added: WooCommerce module with 12 tools — products, orders, customers, coupons and sales summary. Auto-detects WooCommerce.
* Added: Comments module with 6 tools — list, approve, trash, reply and moderation summary.
* Added: Custom Post Types module with 5 tools — discover and manage any registered CPT including custom fields.
* Added: 17 new prompts in Prompt Library (WooCommerce, Comments, CPT categories).
* Total: 35 → 58 tools, 5 → 8 modules, 25 → 42 prompts.

= 1.1.0 — 2026-11-21 =
* Added: Prompt Library — 25 categorized ready-to-use AI prompts with copy button and category filter (Content, SEO, Media, Health, Users, Power Prompts).
* Added: Quick Connect — one-click Application Password generation with ready-to-paste Claude.ai config.
* Added: Manual Credentials Encoder — enter username and app password, get base64 Authorization header and config JSON instantly.
* Added: Revoke Access — disconnect any AI assistant with one click from the Connection Guide.
* Added: Auto-install MCP Adapter — plugin now auto-installs and activates MCP Adapter on activation.
* Fixed: MCP tools/list returning empty — rewrote MCP server to implement protocol directly via WordPress REST API.
* Fixed: 500 error in AJAX handler — removed `is_application_passwords_available_for_user()` call that caused PHP fatal on some WP versions.
* Fixed: Removed `Requires Plugins: mcp-adapter` header that blocked plugin activation.

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

= 1.3.0 =
Security update + 7 bug fixes. Fixes a critical vulnerability where any authenticated user could delete another user's MCP session. Also fixes find-unused-media returning empty, CPT custom field corruption, WooCommerce double query, and SEO pagination. All 58 tools now have valid MCP identifiers. Upgrade recommended for all users.

= 1.2.2 =
Critical fix: original module tools (Content, Media, SEO, Health, Users) could not be called from Claude.ai due to invalid slashes in tool names. Upgrade immediately if you are on v1.2.1 or earlier.

= 1.2.1 =
Recommended fix: WooCommerce, Comments and CPT modules were showing as DISABLED after upgrading from v1.1.0. Upgrade if you installed v1.2.0.

= 1.2.0 =
Major update: adds WooCommerce (12 tools), Comments (6 tools) and Custom Post Types (5 tools) modules. 58 total tools.

= 1.1.0 =
Recommended update: adds Quick Connect, Prompt Library and fixes MCP tools/list returning empty.

= 1.0.0 =
Initial release.
