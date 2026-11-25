=== TR WordPress AI Toolkit ===
Contributors: trsoftech
Tags: ai, mcp, model context protocol, claude, ai assistant, content management, seo, wordpress ai, natural language, woocommerce ai
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.7.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Claude, Copilot, Cursor and any MCP-compatible AI assistant to your WordPress site. Manage content, media, SEO, plugins, themes, menus, database cleanup, security, backups and more using natural language. 148 tools across 17 modules.

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

= Modules and Tools (148 total) =

* **Content (14 tools)** — List, search, create, update and trash posts and pages; manage categories and tags
* **Media (7 tools)** — Browse, search and update media library metadata; find orphaned attachments; upload from URL with SSRF protection; find images missing alt text
* **SEO (6 tools)** — Health score estimate, missing meta titles/descriptions, missing featured images, per-post content structure analysis, write SEO meta. Supports Yoast SEO, Rank Math and All in One SEO Pack automatically.
* **Site Health (7 tools)** — WordPress version, PHP info, active plugins, active theme, site health summary, permalink structure, cron status
* **Users (7 tools)** — List, search, create, update and delete users; inspect roles and capabilities
* **WooCommerce (14 tools)** — Products, orders, customers, coupons, create coupon, inventory summary, low-stock alerts and sales summary. Auto-detects WooCommerce; silently skipped when not active.
* **Comments (8 tools)** — List, approve, unapprove, trash, spam and reply to comments; moderation summary
* **Custom Post Types (5 tools)** — Discover and manage any public CPT (portfolio, testimonials, events, etc.) including custom fields
* **Performance (12 tools)** — PageSpeed Insights scores and Core Web Vitals; clear page/site cache for WP Rocket, LiteSpeed, W3 Total Cache and WP Super Cache; optimize images, convert to WebP, generate image variants
* **Management (8 tools)** — List, activate and deactivate plugins; check for plugin updates; list and activate themes; read and update site settings (blogname, timezone, discussion, reading)
* **Diagnostics (6 tools)** — Server info, PHP configuration and extensions, database table sizes, disk usage, error log viewer, scheduled tasks (WP-Cron)
* **Menus (9 tools)** — List nav menus and their theme-location assignments; get, add, update and delete menu items; create menus and assign to locations; list widget areas and their widgets
* **Database (8 tools)** — Cleanup report; delete revisions, spam, trash, expired transients, orphaned meta; optimize tables; permanently delete a post (confirm required for all destructive actions)
* **Security (7 tools)** — Security score report, file permission check, admin/editor user audit, inactive plugin risk check, WordPress core integrity verification, SSL certificate status, AI activity log
* **Backup (5 tools)** — Detect active backup plugin (UpdraftPlus, BackWPup, Duplicator, All-in-One); list backup files; trigger backup; view schedule; backup storage disk usage

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

= 1.7.5 — 2026-11-25 =
* Fixed: Performance module tools (PageSpeed Insights, cache, image) were failing with "Tool execution failed" because the module received null for $settings. The abilities manager now detects via reflection whether a module constructor accepts a third $settings parameter and passes it only when needed.

= 1.7.4 — 2026-11-25 =
* Fixed: Dashboard showed "Active Modules: 0/17" because `get_modules()` was returning an empty array when the `wp_abilities_api_init` hook had not yet fired at render time. `get_modules()` now calls `ensure_modules_loaded()` so modules are always populated before the dashboard reads them.

= 1.7.3 — 2026-11-25 =
* New: Per-tool enable/disable in Settings → Tool Access. Each tool can be individually toggled. Disabled tools are hidden from AI clients and return a 403 error if called directly. All tools remain enabled by default.
* New: "Enable All" / "Disable All" button per module group in Tool Access section for quick bulk toggling.
* Settings page now shows tool count per module (e.g. "12/12 enabled") and updates live as checkboxes are toggled.
* Technically: `mcp.disabled_tools` array stored in plugin settings; `get_mcp_tools()` filters it out; `execute_mcp_tool()` returns WP_Error 403 for disabled tools.

= 1.7.2 — 2026-11-25 =
* Fixed: Dashboard showed only 8 modules with outdated tool counts after upgrading. Dashboard now reads live data directly from the loaded module instances — counts and status are always accurate.
* Fixed: Settings page only listed the original 8 modules. All 17 modules are now shown with correct tool counts and updated descriptions.
* Fixed: `handle_save_settings()` had a hardcoded whitelist of only 8 module slugs — saving settings stripped out all Sprint 1–4 modules from exposed_modules. Whitelist now includes all 17 valid module slugs.
* Fixed: Auto-expose logic in settings.php only merged `woocommerce`, `comments`, `cpt` — did not include Sprint 1–4 modules. Fixed to merge all 12 auto-exposed module slugs.

= 1.7.1 — 2026-11-25 =
* Fixed: Plugin updates were not appearing in WordPress Dashboard → Updates. Added GitHub Releases auto-updater — WordPress now detects new versions directly from GitHub, shows the standard "Update available" notice, and handles one-click updates.

= 1.7.0 — 2026-11-25 =
* New module: Database (8 tools) — full database cleanup suite with confirm-required guards on all destructive operations.
* New: `get-database-cleanup-report` — counts revisions, auto-drafts, trashed posts/comments, spam, expired transients, and orphaned meta in one call before you clean anything.
* New: `cleanup-post-revisions` — deletes old revisions keeping the latest N per post (configurable, default 3). Requires confirm.
* New: `cleanup-spam-comments` — permanently deletes all spam comments. Requires confirm.
* New: `cleanup-trashed-content` — empties trash for posts and/or comments. Requires confirm.
* New: `cleanup-expired-transients` — removes expired (or all) transients from the options table. Requires confirm.
* New: `cleanup-orphaned-meta` — deletes post, comment and user meta with no matching parent record. Requires confirm.
* New: `optimize-database-tables` — runs OPTIMIZE TABLE on all WordPress tables. Requires confirm.
* New: `permanently-delete-post` — force-deletes a post bypassing trash. Requires confirm. Warns if post was published.
* New module: Security (7 tools) — WordPress security auditing.
* New: `get-security-report` — 0–100 security score with issues (−20 each) and warnings (−5 each) across SSL, WP version, plugin updates, file editor, debug mode, wp-config permissions, and admin count.
* New: `check-file-permissions` — verifies octal permissions on wp-config.php, .htaccess, wp-admin/, wp-includes/, wp-content/ and uploads/ against recommended values.
* New: `list-admin-users` — all admins and editors with registration date and a `is_new` flag for accounts under 7 days old.
* New: `check-inactive-plugins` — lists inactive plugins with update availability and a note about their security risk.
* New: `check-core-integrity` — compares md5 checksums of wp-admin/ and wp-includes/ against the WordPress.org API for the current version and locale. Reports modified and missing files.
* New: `check-ssl-status` — reports HTTPS status, FORCE_SSL_ADMIN flag, and live certificate details (subject, issuer, expiry, days remaining) via stream_socket_client.
* New: `get-user-activity-log` — returns recent AI tool invocations from the plugin's audit log table with user, IP, status and tool name.
* New module: Backup (5 tools) — backup plugin integration.
* New: `get-backup-status` — detects active backup plugin (UpdraftPlus, BackWPup, Duplicator, All-in-One WP Migration, WPvivid) and reports last backup age and health (ok/stale/unknown/no_plugin).
* New: `list-backups` — lists existing backup ZIP files from UpdraftPlus storage directory sorted newest first.
* New: `trigger-backup` — schedules an immediate UpdraftPlus backup via WP-Cron. Requires confirm. Returns plugin-specific instructions for other plugins.
* New: `get-backup-schedule` — returns configured backup intervals, retention counts, remote storage destinations, and next scheduled WP-Cron events for UpdraftPlus.
* New: `get-backup-storage-info` — scans known backup directories and reports path, file count and disk usage.
* Total: 128 → 148 tools across 14 → 17 modules.

= 1.6.0 — 2026-11-24 =
* New module: Management (8 tools) — plugin activation/deactivation, plugin update status, theme listing and activation, site settings read/write.
* New: `list-plugins` — lists all installed plugins with status (active/inactive), version, and whether an update is available. Supports status filter and pagination.
* New: `activate-plugin` / `deactivate-plugin` — activate or deactivate any installed plugin. Self-protection prevents deactivating TR WordPress AI Toolkit itself.
* New: `get-plugin-update-status` — returns all plugins with available updates, including current version, new version, and last-checked timestamp.
* New: `list-themes` — lists all installed themes with status, version and update availability.
* New: `activate-theme` — switches the active theme by stylesheet name.
* New: `get-site-settings` — reads general, reading, discussion, permalink and upload settings grouped by category.
* New: `update-site-settings` — updates a whitelist of safe site options (blogname, timezone, date_format, posts_per_page, etc.); siteurl and home are intentionally excluded.
* New module: Diagnostics (6 tools) — full server environment inspection.
* New: `get-server-info` — web server, PHP version, SAPI, OS, database version, WordPress debug flags, memory limits.
* New: `get-php-configuration` — PHP ini values, key extension presence, full extension list, OPcache stats (hit rate, memory, cached files).
* New: `get-database-stats` — all database tables with sizes in KB, split into WordPress tables and other tables.
* New: `get-disk-usage` — uploads, plugins, themes and wp-content directory sizes with file counts.
* New: `get-error-log` — reads last N lines from the PHP/WordPress error log (WP_DEBUG_LOG or error_log path). Returns basename only for security.
* New: `get-scheduled-tasks` — all WP-Cron events with next-run time, schedule interval, and args. Reports DISABLE_WP_CRON and ALTERNATE_WP_CRON status.
* New module: Menus (9 tools) — navigation menu and widget area management.
* New: `list-nav-menus` — all registered menus with assigned theme locations; also lists unassigned theme locations.
* New: `get-nav-menu-items` — full item list for any menu (by ID, slug or name) with type, URL, parent, and position.
* New: `create-nav-menu` — creates a new navigation menu and optionally assigns it to a theme location.
* New: `add-menu-item` — adds a custom URL, post, page or taxonomy term as a menu item.
* New: `update-menu-item` — rename, reposition or reparent an existing menu item.
* New: `delete-menu-item` — removes a menu item permanently.
* New: `assign-menu-to-location` — assigns (or clears) a menu from a registered theme location.
* New: `list-widget-areas` — lists all registered sidebars with widget counts.
* New: `list-widgets` — lists widgets in a sidebar with their title and settings keys.
* Total: 105 → 128 tools across 11 → 14 modules.

= 1.5.0 — 2026-11-24 =
* New module: Performance (12 tools) — PageSpeed Insights, Cache management, Image optimization.
* New: `get-pagespeed-score` — fetches Google PageSpeed Insights v5 for any URL (desktop or mobile), returns performance score, Core Web Vitals (LCP, CLS, FCP, TBT, INP, Speed Index), top 5 improvement opportunities, and top 10 diagnostics. Results cached for 1 hour. Requires optional API key for higher quota.
* New: `get-core-web-vitals` — returns field data (Chrome UX Report p75 values) alongside lab data, with pass/needs-improvement/fail ratings per metric.
* New: `get-cache-status` — detects active cache plugin (WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache) and reports cache directory path, file count and total size.
* New: `clear-page-cache` — purges cache for a specific post ID using the active cache plugin's native API.
* New: `clear-all-cache` — purges the entire site cache for the active cache plugin.
* New: `clear-object-cache` — flushes WordPress object cache (`wp_cache_flush()`).
* New: `get-image-optimization-stats` — scans up to 100 recent attachments and reports average file size, WebP adoption rate, missing alt text count, and oversized images (> 200 KB).
* New: `optimize-image` — re-saves a media library image at a specified quality (1–100) using WP_Image_Editor (GD/Imagick); original preserved, optimized copy registered as new attachment.
* New: `convert-to-webp` — converts any JPEG/PNG media library item to WebP using WP_Image_Editor; WebP registered as new attachment.
* New: `generate-image-variants` — regenerates all registered WordPress image sizes for an attachment using `wp_generate_attachment_metadata()`.
* New: `get-image-details` — returns dimensions, file size, MIME type, alt text, caption and all registered size variants for a media library attachment.
* New: `find-oversized-images` — lists media library images exceeding a configurable file-size threshold (default 200 KB) sorted by size descending, with download URL and edit link.
* Total: 105 tools across 11 modules (up from 93 across 10).

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

= 1.7.2 =
Critical fix: dashboard showed only 8 modules after upgrade, settings page did not list new modules, saving settings stripped out all Sprint 1-4 modules. All 17 modules now correctly displayed, saved and loaded.

= 1.7.1 =
Fixes missing WordPress auto-update notifications. Plugin now checks GitHub Releases and shows update notices in Dashboard → Updates automatically.

= 1.7.0 =
Adds Database cleanup, Security audit and Backup integration modules. All destructive database operations require confirm: true. 148 total tools across 17 modules.

= 1.6.0 =
Adds Management, Diagnostics and Menus modules — plugin/theme activation, server diagnostics, nav menu and widget management, site settings read/write. 128 total tools across 14 modules.

= 1.5.0 =
New Performance module: PageSpeed Insights scores, Core Web Vitals, cache management (WP Rocket, LiteSpeed, W3TC, WP Super Cache) and image optimization tools. 105 total tools across 11 modules.

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
