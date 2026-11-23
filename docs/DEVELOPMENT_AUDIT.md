# TR WordPress AI Toolkit — Development Audit

**Version audited:** 1.2.2  
**Branch:** feature/production-ready-mcp  
**Audit date:** 2026-11-23  
**Auditor:** Senior WordPress Plugin Architect / MCP Protocol Engineer

---

## 1. Architecture Overview

### Plugin Bootstrap

```
tr-wordpress-ai-toolkit.php          Entry point, constants, hooks
includes/class-plugin.php            Singleton bootstrap, DI wiring
includes/class-activator.php         Activation: DB table, default settings, MCP Adapter install
includes/class-deactivator.php       Deactivation: flush rewrite rules
includes/class-settings.php          Settings: dot-notation get/set over wp_options
includes/class-permissions.php       Capability helper: can_read / can_write / can_admin
includes/class-abilities-manager.php Module loader, MCP tool router
includes/class-logger.php            Audit log: DB insert, prune, retrieve
integrations/mcp-adapter/
  class-mcp-server.php               Built-in MCP HTTP server (REST endpoint)
modules/
  content/class-content-module.php   14 tools — posts, pages, categories, tags
  media/class-media-module.php       5 tools — library, metadata, unused detection
  seo/class-seo-module.php           5 tools — Yoast/RankMath/AIOSEO integration
  health/class-health-module.php     7 tools — WP/PHP info, plugins, theme, cron
  users/class-users-module.php       4 tools — list, search, roles
  woocommerce/class-woocommerce-module.php  12 tools (conditional on WC)
  comments/class-comments-module.php 6 tools — moderation
  cpt/class-cpt-module.php           5 tools — dynamic CPT management
admin/
  class-admin.php                    Menu registration, AJAX handlers
  views/dashboard.php                Status dashboard
  views/settings.php                 Plugin settings form
  views/connect.php                  Quick Connect / Connection Guide
  views/prompts.php                  Prompt Library (42 prompts)
  views/logs.php                     Activity Log viewer
  css/admin.css                      Admin styles
  js/admin.js                        AJAX, copy, revoke handlers
```

### Dependency Injection

`TR_AI_Toolkit_Plugin::get_instance()->run()` wires all dependencies:

```
Settings → Permissions → Logger
                          ↓
                   AbilitiesManager ← modules load here
                          ↓
                   MCP_Server
                   Admin
```

### Data Storage

| Item | Storage | Key |
|------|---------|-----|
| Plugin settings | wp_options | `tr_ai_toolkit_settings` |
| Audit logs | Custom DB table | `{prefix}tr_ai_toolkit_logs` |
| MCP sessions | Transients | `tr_mcp_sess_{uuid}` (1 hr TTL) |
| App Password UUID | User meta | `tr_ai_toolkit_app_password_uuid` |
| App Password name | User meta | `tr_ai_toolkit_app_password_name` |

---

## 2. MCP Implementation Analysis

### Transport

Custom REST API endpoint: `GET|POST /wp-json/tr-ai-toolkit/mcp`  
Session termination: `DELETE /wp-json/tr-ai-toolkit/mcp`

No dependency on MCP Adapter for HTTP transport. MCP Adapter is only used for STDIO (Claude Desktop / local).

### Protocol Version

Declared: `2025-03-26`  
This is the correct current stable MCP protocol version. The README previously claimed `2025-11-25 / 2026-07-28` which was inaccurate.

### Method Coverage

| MCP Method | Implemented | Notes |
|------------|-------------|-------|
| `initialize` | ✅ | Returns protocol version + server info |
| `notifications/initialized` | ✅ | Returns null (correct) |
| `tools/list` | ✅ | Returns all active module tools |
| `tools/call` | ✅ | Dispatches to module execute() |
| `ping` | ✅ | Returns empty object |
| `resources/list` | ❌ | Not implemented |
| `resources/read` | ❌ | Not implemented |
| `prompts/list` | ❌ | Not implemented |
| `logging/setLevel` | ❌ | Not implemented |

Resources and prompts are optional in the spec. The current `tools`-only implementation is valid.

### Session Lifecycle

**Create:** `initialize` generates UUID4, stores as transient (1 hr), returns in `Mcp-Session-Id` header.  
**Use:** No session validation on subsequent requests. Any authenticated user can call tools without a valid session header.  
**Delete:** `DELETE /mcp` deletes the transient by header value.

**CRITICAL SECURITY ISSUE:** The DELETE handler does not verify that the session belongs to the requesting user. Any authenticated user can send `DELETE /wp-json/tr-ai-toolkit/mcp` with any `Mcp-Session-Id` header value and invalidate another user's session.

**Additional Issue:** Subsequent requests (`tools/list`, `tools/call`) do not validate the session ID at all. The session header is ignored after initialization. This means sessions provide no actual security boundary.

### Authentication Flow

1. WordPress Application Password is included in `Authorization: Basic` header.
2. WordPress REST API authenticates it natively via `wp_authenticate_application_password()`.
3. `is_user_logged_in()` check at top of `handle_request()` verifies authentication.
4. Per-tool permission checks use `current_user_can()`.

This is correct and follows WordPress best practices.

### Error Handling

```php
mcp_error($id, $code, $message, $http_status = 200)
```

All protocol errors return HTTP 200 by default (MCP spec recommends this for JSON-RPC errors). Only auth failures (401) and parse errors (400) return non-200 HTTP status. This is correct.

JSON-RPC error codes used:
- `-32700` Parse error
- `-32601` Method not found
- `-32602` Invalid params (missing tool name)
- `-32001` Unauthorized / Permission denied
- `-32000` Generic server error / Tool not found

---

## 3. Existing Modules and Tools

### Complete Tool Inventory (46 active tools when WooCommerce not present, 58 when WC active)

#### Content Module (14 tools)
| Tool | Permissions | Input | Notes |
|------|-------------|-------|-------|
| `tr-ai-toolkit-list-posts` | read | per_page, page, status, category_id, tag_id, author_id, orderby, order | Max 100 per page |
| `tr-ai-toolkit-get-post` | read | post_id | Full content + metadata |
| `tr-ai-toolkit-search-posts` | read | query | Max 50 results |
| `tr-ai-toolkit-create-post` | write | title*, content, excerpt, status, category_ids, tag_ids, author_id | Defaults to draft |
| `tr-ai-toolkit-update-post` | write | post_id*, title, content, excerpt, status | Partial updates |
| `tr-ai-toolkit-delete-post` | write | post_id | Trash only — no permanent delete |
| `tr-ai-toolkit-list-pages` | read | per_page, page, status, parent_id | Max 100 |
| `tr-ai-toolkit-get-page` | read | page_id | Full content + template |
| `tr-ai-toolkit-create-page` | write | title*, content, status, parent_id, template | Defaults to draft |
| `tr-ai-toolkit-update-page` | write | page_id*, title, content, status, parent_id | Partial updates |
| `tr-ai-toolkit-list-categories` | read | hide_empty, search | Full list |
| `tr-ai-toolkit-create-category` | write | name*, slug, description, parent_id | Auto-slug |
| `tr-ai-toolkit-list-tags` | read | hide_empty, search, per_page | Max 500 total |
| `tr-ai-toolkit-create-tag` | write | name*, slug, description | Auto-slug |

#### Media Module (5 tools)
| Tool | Permissions | Input | Notes |
|------|-------------|-------|-------|
| `tr-ai-toolkit-list-media` | read | per_page, page, mime_type, search, orderby, order | Max 100 |
| `tr-ai-toolkit-get-media-details` | read | attachment_id | All image sizes |
| `tr-ai-toolkit-search-media` | read | query* | Max 50 results |
| `tr-ai-toolkit-update-media-metadata` | upload | attachment_id*, title, alt_text, caption, description | Updates post + meta |
| `tr-ai-toolkit-find-unused-media` | upload | per_page, mime_type | **BROKEN — always returns empty** |

#### SEO Module (5 tools)
| Tool | Permissions | Notes |
|------|-------------|-------|
| `tr-ai-toolkit-get-seo-summary` | read | Site-wide SEO health score |
| `tr-ai-toolkit-find-missing-meta-titles` | read | **PAGINATION BUG** |
| `tr-ai-toolkit-find-missing-meta-descriptions` | read | **PAGINATION BUG** |
| `tr-ai-toolkit-find-posts-without-featured-images` | read | Works correctly |
| `tr-ai-toolkit-analyze-content-structure` | read | Per-post word count, headings, links |

#### Health Module (7 tools — all require manage_options)
`tr-ai-toolkit-get-wordpress-info`, `tr-ai-toolkit-get-php-version`, `tr-ai-toolkit-get-active-plugins`, `tr-ai-toolkit-get-active-theme`, `tr-ai-toolkit-get-site-health-summary`, `tr-ai-toolkit-get-permalink-structure`, `tr-ai-toolkit-get-cron-status`

#### Users Module (4 tools)
`tr-ai-toolkit-list-users`, `tr-ai-toolkit-get-user`, `tr-ai-toolkit-search-users`, `tr-ai-toolkit-get-user-roles`

#### WooCommerce Module (12 tools — conditional)
`wc-list-products`, `wc-get-product`, `wc-search-products`, `wc-create-product`, `wc-update-product`, `wc-get-low-stock-products`, `wc-list-orders`, `wc-get-order`, `wc-update-order-status`, `wc-list-customers`, `wc-list-coupons`, `wc-get-sales-summary`

#### Comments Module (6 tools)
`get-comment-summary`, `list-comments`, `get-comment`, `approve-comment`, `trash-comment`, `reply-to-comment`

#### CPT Module (5 tools)
`list-post-types`, `list-cpt-posts`, `get-cpt-post`, `create-cpt-post`, `update-cpt-post`

---

## 4. Authentication Flow

```
AI Client → Authorization: Basic base64(user:app_password)
         → POST /wp-json/tr-ai-toolkit/mcp
         → WordPress authenticates via Application Passwords (WP 5.6+)
         → is_user_logged_in() check in handle_request()
         → Tool execution → current_user_can() per-tool check
```

Application Passwords are the only supported auth mechanism. Cookie-based auth also works but is not intended for AI clients.

---

## 5. Permissions Model

| Capability | Used By | Default Role |
|------------|---------|--------------|
| `read` | List/view tools | Subscriber+ |
| `edit_posts` | Create/update content | Author+ |
| `upload_files` | Media operations | Author+ |
| `moderate_comments` | Comment tools | Editor+ |
| `list_users` | User listing, WC customers | Editor+ |
| `manage_options` | Health, settings | Admin only |
| `edit_products` | WC product write | Shop Manager+ |
| `edit_shop_orders` | WC order management | Shop Manager+ |
| `view_woocommerce_reports` | WC sales summary | Shop Manager+ |

---

## 6. Existing Security Controls

✅ Per-tool `permission_callback` with `current_user_can()`  
✅ Input sanitization: `sanitize_text_field()`, `wp_kses_post()`, `absint()`, `sanitize_key()`  
✅ Prepared database queries (via WP APIs)  
✅ Email masking in user/health/comments output  
✅ Password/token redaction in logger  
✅ No `eval()`, `exec()`, `shell_exec()`, `system()`  
✅ No `$wpdb->query()` with unsanitized input  
✅ Trash-only deletion (no permanent content removal via AI)  
✅ Application Password auth (not main admin password)  
✅ Nonce verification on all admin forms and AJAX  
✅ Output escaping on all admin views (`esc_html`, `esc_attr`, `esc_url`)  

---

## 7. Existing Test Coverage

**Framework:** PHPUnit + WordPress Test Suite  
**Bootstrap:** `tests/phpunit/bootstrap.php` with `WP_UnitTestCase`

| File | Class | Tests | Status |
|------|-------|-------|--------|
| SettingsTest.php | TR_AI_Toolkit_SettingsTest | 5 | ✅ Passing |
| PermissionsTest.php | TR_AI_Toolkit_PermissionsTest | 6 | ✅ Passing |
| LoggerTest.php | TR_AI_Toolkit_LoggerTest | 4 | ✅ Passing |
| ActivatorTest.php | TR_AI_Toolkit_ActivatorTest | 3 | ✅ Passing |
| ContentModuleTest.php | TR_AI_Toolkit_ContentModuleTest | 8 | ✅ Passing |

**Estimated line coverage:** ~15%  
**Missing test classes:** Media, SEO, Health, Users, WooCommerce, Comments, CPT, MCP Server

---

## 8. Bugs and Protocol Issues

### Critical
| ID | Location | Description |
|----|----------|-------------|
| BUG-11 | `class-mcp-server.php:84` | MCP session deletion allows any user to delete any session (no ownership check) |

### High
| ID | Location | Description |
|----|----------|-------------|
| BUG-3 | `class-media-module.php:314` | `find-unused-media` always returns empty — meta_query runs against wrong post type |
| BUG-7 | `class-woocommerce-module.php:97` | Product list calls `wc_get_products(limit=-1)` twice — severe performance issue on large stores |
| BUG-10 | `class-cpt-module.php:291` | CPT custom field values always sanitized as text — corrupts array/object values |

### Medium
| ID | Location | Description |
|----|----------|-------------|
| BUG-2 | `class-content-module.php:999` | `tr-ai-toolkit-create-category` missing from `handles()` list |
| BUG-4 | `class-seo-module.php:160` | SEO missing-meta tools fetch `per_page * 3` posts then slice — pagination inconsistent |
| BUG-8 | `class-woocommerce-module.php:304` | Update product conditionals without braces (coding standard violation) |

### Low
| ID | Location | Description |
|----|----------|-------------|
| BUG-1 | `class-content-module.php:1068` | `wp_trim_excerpt()` without explicit post ID |
| BUG-5 | `class-health-module.php:118` | `max_execution_time` = -1 (unlimited) not handled |
| BUG-6 | `class-health-module.php:253` | Redundant `WP_DEBUG && WP_DEBUG` check |
| BUG-9 | `class-woocommerce-module.php:555` | Email masking algorithm differs from other modules |

---

## 9. Missing Functionality

### High Priority
- **Media upload tool** — No ability to upload new files (only update metadata)
- **SEO metadata write** — `get-seo-summary` is read-only; no tool to update SEO title/description
- **Bulk operations** — No multi-post update, no bulk status change
- **Plugin update status** — Health module lists plugins but not whether updates are available

### Medium Priority
- **User create/update/delete** — Users module is read-only
- **Schedule content** — No `schedule_post` tool (can set `future` status but no specific datetime)
- **WooCommerce create coupon** — `wc-list-coupons` exists but no create coupon tool
- **Comment unapprove** — Comments module can approve but not unapprove
- **MCP resources endpoint** — Optional but would enable Prompt Library as MCP resources
- **Connection test tool** — Admin UI has no "Test Connection" button

### Low Priority
- **Performance module** — Cache, DB size, autoload stats
- **Forms module** — Contact Form 7 / WPForms integration
- **Redirects module** — Redirection plugin integration
- **Rate limiting** — No protection against tool call flooding

---

## 10. Recommended Implementation Order

1. **Phase 2 — MCP Security (immediate)**
   - Fix BUG-11: Session ownership validation
   - Add session validation on tool calls
   - Fix protocol version negotiation

2. **Phase 3 — Feature Bug Fixes (high priority)**
   - Fix BUG-3: find-unused-media
   - Fix BUG-7: WC double query
   - Fix BUG-10: CPT meta sanitization
   - Fix BUG-2: Missing handles() entry
   - Fix BUG-4: SEO pagination
   - Normalize email masking (BUG-9)

3. **Phase 4 — Missing Features**
   - Add media upload tool
   - Add SEO metadata write tools
   - Add user create/update tools
   - Add comment unapprove tool
   - Add WC create coupon
   - Add plugin update availability to health module

4. **Phase 5 — Admin UI**
   - Add "Test Connection" button
   - Add log search/filter
   - Add bulk log clear
   - Improve settings page UX

5. **Phase 6 — Tests**
   - Add test classes for all 7 untested modules
   - Add MCP server protocol tests
   - Add negative/security test cases

6. **Phase 7 — Documentation**
   - Create all missing docs/ files
   - Resolve all count inconsistencies
   - Add real configuration examples
