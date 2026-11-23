# Changelog

All notable changes to TR WordPress AI Toolkit are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.3.0] — 2026-11-23

### Security
- **BUG-11 (Critical): MCP session hijacking** — Any authenticated user could delete another user's session by sending a `DELETE /mcp` request with an arbitrary session ID. Sessions now store the creating user's ID; deletion verifies ownership and returns 403 on mismatch.
- MCP sessions now store `{user_id, created_at}` instead of just `1`. Invalid or stale sessions on non-initialize requests now return `-32001 Invalid or expired session`.
- Protocol error messages no longer leak internal tool names, file paths, or method details.

### Fixed
- **BUG-3 (High): `find-unused-media` always returned empty** — The `meta_query` incorrectly checked `_thumbnail_id` on the attachment's own postmeta. `_thumbnail_id` is stored on the POST that uses the attachment as featured image, not on the attachment. Fixed with a direct SQL query to fetch used attachment IDs, then `post__not_in` exclusion.
- **BUG-7 (High): WooCommerce double query** — `wc_get_products(limit=-1)` was called twice — once to fetch products and once to count them. The count call always returned `1` due to casting array to int. Single count query with the same filters is now used.
- **BUG-10 (High): CPT custom field corruption** — `sanitize_text_field()` was applied to all meta values including arrays, turning `['foo','bar']` into the string `"Array"`. New recursive `sanitize_meta_value()` helper preserves arrays, booleans, and numeric types; only strings are sanitized.
- **BUG-4 (Medium): SEO pagination inconsistency** — `find-missing-meta` fetched `per_page × 3` posts then filtered in PHP, causing page 1 and page 2 results to overlap. Rewritten to use a proper `meta_query` (NOT EXISTS OR empty) at the database level with correct `paged` parameter.
- **BUG-8 (Medium): WooCommerce update product missing braces** — Conditionals in `execute_update_product()` lacked curly braces. Fixed.
- **BUG-9 (Low): Inconsistent email masking in WooCommerce** — WooCommerce used `substr_replace()` giving a different mask format than other modules. All modules now use `***@domain.com` format.
- **BUG-5 (Low): `max_execution_time` raw `-1` value** — Server info tool returned `-1` when PHP has no execution time limit. Now returns `'unlimited'`.
- **Tool names for WooCommerce, Comments, CPT** — These 3 modules still used `tr-ai-toolkit/toolname` (slash) which is invalid per MCP spec. Renamed to `tr-ai-toolkit-toolname`. All 58 tools now use valid MCP identifiers.
- MCP protocol response now includes `'isError' => false` on all successful tool calls.
- MCP protocol negotiation: server now responds with the client's requested version if supported, otherwise falls back gracefully.

### Added
- **Activity Log filter bar** — Live search by ability name and status dropdown (all / success / denied / error) with row count on the Activity Log admin page.
- **Clear All Logs** — One-click "Clear All Logs" button on the Activity Log page with AJAX confirmation. Calls new `Logger::clear_all()` method.
- **Test MCP Connection** — New section on the Connection Guide admin page. Fires an internal REST `ping` request and displays pass/fail inline. No credentials needed.
- **Settings descriptions** — Helpful hint text added to Enable Plugin, Log Level, Permissions, and Log Retention settings fields.
- `Logger::clear_all()` method — deletes all log entries and returns the deleted row count.

### Documentation
- `docs/CONNECTION_GUIDE.md` — Step-by-step connection guide for all MCP clients.
- `docs/CLAUDE_DESKTOP.md` — HTTP and STDIO transport setup for Claude Desktop.
- `docs/CLAUDE_CODE.md` — Claude.ai web connector and Claude Code CLI setup.
- `docs/MCP_PROTOCOL.md` — Full technical reference: protocol version, endpoint, session lifecycle, error codes, examples.
- `docs/AVAILABLE_TOOLS.md` — Complete 58-tool reference with capabilities and descriptions.
- `docs/PERMISSIONS.md` — Per-tool capability requirements and sensitive data handling.
- `docs/TROUBLESHOOTING.md` — Common issues and solutions including tool name fix, auth errors, pagination, WooCommerce.
- `docs/SECURITY_REVIEW.md` — Threat model, security controls, and known limitations.
- `README.md` — Updated version badge (1.3.0), tool count (58), protocol version (2025-03-26), all tool names corrected.

### Tests
- `MCPServerTest.php` (new) — 11 tests covering: initialize response/version negotiation, ping, tools/list, tools/call with unknown tool, unauthenticated 401, session ownership enforcement (403), plugin-disabled 503.
- `LoggerTest` — Added tests for new `clear_all()` method.
- `ContentModuleTest` — Fixed all tool name references from `tr-ai-toolkit/` to `tr-ai-toolkit-`.

---

## [1.2.2] — 2026-11-21

### Fixed
- **Tools not callable from Claude.ai** — MCP tool names for all original modules (Content, Media, SEO, Health, Users) used a forward-slash separator (`tr-ai-toolkit/list-posts`). The MCP specification requires tool names to use only alphanumeric characters, hyphens and underscores — no slashes. Claude.ai accepted these names in `tools/list` but refused to call them, reporting the tools as unavailable. All 35 tool names have been renamed to use a hyphen separator (`tr-ai-toolkit-list-posts`). WooCommerce, Comments and CPT modules were already using valid names and are unaffected.
- **PHP bug in `register_abilities()`** — `array_keys($module_map)` was referenced before `$module_map` was defined. Reordered code so `$module_map` is always defined first.

---

## [1.2.1] — 2026-11-21

### Fixed
- **WooCommerce, Comments, CPT modules showing DISABLED** — Settings save handler was filtering new modules out; abilities manager and dashboard were not auto-enabling them on upgrade from v1.1.0
- **Settings page missing new module checkboxes** — WooCommerce, Comments and Custom Post Types were not showing in the Exposed Modules settings list
- **Upgrade path from v1.1.0** — Existing installs now automatically enable the 3 new modules without requiring manual Settings save

---

## [1.2.0] — 2026-11-21

### Added
- **WooCommerce Module (12 tools)** — `wc-list-products`, `wc-get-product`, `wc-search-products`, `wc-create-product`, `wc-update-product`, `wc-get-low-stock-products`, `wc-list-orders`, `wc-get-order`, `wc-update-order-status`, `wc-list-customers`, `wc-list-coupons`, `wc-get-sales-summary`. Auto-disabled when WooCommerce is not active.
- **Comments Module (6 tools)** — `get-comment-summary`, `list-comments`, `get-comment`, `approve-comment`, `trash-comment`, `reply-to-comment`
- **Custom Post Types Module (5 tools)** — `list-post-types`, `list-cpt-posts`, `get-cpt-post`, `create-cpt-post`, `update-cpt-post`. Works with any public CPT registered on the site.
- Dashboard module count updated to reflect 8 modules / 58 total tools

---

## [1.1.0] — 2026-11-21

### Added
- **Prompt Library** — 25 categorized ready-to-use AI prompts with copy button and category filter (Content, SEO, Media, Health, Users, Power Prompts)
- **Quick Connect** — One-click Application Password generation with ready-to-paste Claude.ai config
- **Manual Credentials Encoder** — Enter username + app password, get base64 Authorization header instantly
- **Auto-install MCP Adapter** — Plugin now auto-installs and activates MCP Adapter on activation

### Fixed
- **MCP tools/list returning empty** — Rewrote MCP server to implement protocol directly via WordPress REST API, no longer depends on MCP Adapter `create_server()` internal API
- **500 error in AJAX handler** — Removed `is_application_passwords_available_for_user()` call that caused PHP fatal on some WP versions
- Removed `Requires Plugins: mcp-adapter` header that blocked plugin activation

---

## [1.0.0] — 2026-11-21

### Added

**Core**
- Plugin bootstrap with singleton pattern and dependency injection
- Activation: creates `{prefix}tr_ai_toolkit_logs` audit table
- Deactivation: flushes rewrite rules
- Uninstall: removes all plugin data (options + table) on plugin deletion
- Requirements check: blocks activation on PHP < 8.1 or WordPress < 6.9
- Scheduled WP-Cron event for daily audit log pruning

**Content Module (14 abilities)**
- `tr-ai-toolkit/list-posts` — paginated list with filters
- `tr-ai-toolkit/get-post` — full post details
- `tr-ai-toolkit/search-posts` — full-text search
- `tr-ai-toolkit/create-post` — create post (defaults to draft)
- `tr-ai-toolkit/update-post` — partial update
- `tr-ai-toolkit/delete-post` — trash only (no permanent deletion)
- `tr-ai-toolkit/list-pages` — paginated pages list
- `tr-ai-toolkit/get-page` — full page details
- `tr-ai-toolkit/create-page` — create page (defaults to draft)
- `tr-ai-toolkit/update-page` — partial update
- `tr-ai-toolkit/list-categories` — all categories
- `tr-ai-toolkit/create-category` — create category
- `tr-ai-toolkit/list-tags` — all tags
- `tr-ai-toolkit/create-tag` — create tag

**Media Module (5 abilities)**
- `tr-ai-toolkit/list-media` — paginated media list
- `tr-ai-toolkit/get-media-details` — full attachment details with all image sizes
- `tr-ai-toolkit/search-media` — search media library
- `tr-ai-toolkit/update-media-metadata` — update title, alt, caption, description
- `tr-ai-toolkit/find-unused-media` — orphaned attachments (no parent post)

**SEO Module (5 abilities)**
- `tr-ai-toolkit/get-seo-summary` — overall SEO health with score estimate
- `tr-ai-toolkit/find-missing-meta-titles` — posts without SEO titles
- `tr-ai-toolkit/find-missing-meta-descriptions` — posts without meta descriptions
- `tr-ai-toolkit/find-posts-without-featured-images` — posts without thumbnails
- `tr-ai-toolkit/analyze-content-structure` — per-post: word count, headings, links, images
- SEO adapter support: Yoast SEO, Rank Math, All in One SEO Pack

**Health Module (7 abilities)**
- `tr-ai-toolkit/get-wordpress-info` — version, URL, language, timezone (admin email masked)
- `tr-ai-toolkit/get-php-version` — PHP version and extensions
- `tr-ai-toolkit/get-active-plugins` — active plugin list with versions
- `tr-ai-toolkit/get-active-theme` — theme details and block theme support
- `tr-ai-toolkit/get-site-health-summary` — counts, memory, HTTPS, debug status
- `tr-ai-toolkit/get-permalink-structure` — permalink and base settings
- `tr-ai-toolkit/get-cron-status` — WP-Cron events and overdue count

**Users Module (4 abilities)**
- `tr-ai-toolkit/list-users` — paginated user list (email masked)
- `tr-ai-toolkit/get-user` — single user profile (email masked)
- `tr-ai-toolkit/search-users` — search by name or email
- `tr-ai-toolkit/get-user-roles` — all roles with capability summary

**Admin Dashboard**
- Dashboard: MCP status, module status, quick links, recent activity
- Settings: general, MCP server, permissions, logging
- Connection Guide: requirements check, HTTP/STDIO instructions, Claude.ai config
- Activity Log: last 100 invocations with user, IP, status
- Admin notice when MCP Adapter is not active

**Security**
- Per-ability `permission_callback` with WordPress capability checks
- Input sanitization using WordPress functions throughout
- Output escaping on all admin views
- Sensitive field redaction in audit log (passwords, tokens, keys)
- IP masking not applicable — IP stored for audit, not displayed to AI
- No arbitrary PHP/SQL/shell execution anywhere

**MCP Integration**
- Dedicated MCP server registered via `mcp_adapter_init` hook
- Endpoint: `/wp-json/tr-ai-toolkit/mcp`
- All abilities have `meta.mcp.public = true` for default server exposure
- HTTP transport support (Streamable HTTP, MCP 2025-11-25 and 2026-07-28)
- STDIO transport via `wp mcp-adapter serve`
