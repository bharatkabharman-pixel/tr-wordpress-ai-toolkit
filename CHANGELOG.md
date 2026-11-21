# Changelog

All notable changes to TR WordPress AI Toolkit are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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
