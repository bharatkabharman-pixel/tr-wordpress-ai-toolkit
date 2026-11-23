# Security Review

Security architecture and controls for TR WordPress AI Toolkit.

---

## Threat Model

The plugin exposes WordPress site data and write operations to AI clients over HTTP. The principal threats are:

1. **Unauthorized access** — unauthenticated users calling tools.
2. **Privilege escalation** — authenticated users calling tools above their capability level.
3. **Session hijacking** — attacker forging or stealing a session ID to impersonate another user.
4. **Information leakage** — error messages revealing internal implementation details.
5. **Injection** — AI-supplied input being used unsanitized in database queries.

---

## Controls

### Authentication

- Every MCP request requires a valid WordPress Application Password (HTTP Basic Auth).
- Application Passwords are WordPress core functionality — revoked immediately via the Users profile page.
- No custom auth tokens are issued or managed by the plugin.

### Session Binding

- MCP sessions are stored as WordPress transients.
- Each transient stores `{ user_id, created_at }` — the authenticated user is embedded in the session.
- Session deletion (`DELETE /mcp`) verifies that `session.user_id === current_user_id()`. Cross-user session deletion is blocked with 403.
- Sessions expire after 1 hour (WordPress transient TTL).

### Capability Checks

- Global gate: configurable minimum capability per operation type (read/write/admin).
- Per-tool gate: each tool calls `current_user_can()` with the appropriate WordPress capability.
- Both gates must pass. Failure at either gate returns 403 with a generic "Permission denied." message.

### Input Sanitization

- All string inputs go through `sanitize_text_field()`.
- Array meta values are sanitized recursively — scalar type (bool, int, float) is preserved.
- Integer inputs use `absint()` or `(int)` cast.
- Pagination parameters are clamped to safe ranges.
- SQL queries use `$wpdb->prepare()` exclusively — no string interpolation of user input.

### Output Encoding

- All HTML output uses `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` per context.
- JSON responses are assembled as PHP arrays and encoded via `wp_json_encode()`.

### Error Message Hardening

- MCP error messages do not include tool names, file paths, stack traces, or database error details.
- 403 responses say "Permission denied." only.
- 404 responses say "Tool not found." only — `-32601` code is used.

### Sensitive Data Masking

- User email addresses are returned as `***@domain.com` in all modules.
- Activity log redacts any input field whose key contains: `password`, `pass`, `token`, `secret`, `nonce`, `auth`, `key`, `api_key`.

### Admin UI Security

- All form submissions use WordPress nonces (`wp_nonce_field` / `check_admin_referer`).
- All AJAX handlers use `check_ajax_referer()`.
- All AJAX handlers check `current_user_can('manage_options')`.
- Settings are saved via `sanitize_key()`, `sanitize_text_field()`, `absint()`, and allowlist-validated selects.

---

## Known Limitations

| Item | Status |
|---|---|
| Rate limiting on MCP endpoint | Not implemented — relies on hosting/CDN layer |
| IP allowlist for MCP endpoint | Not implemented — use server-level firewall |
| Audit log tamper protection | Logs stored in DB without signing — privileged DB access can alter them |
| STDIO transport auth | Relies on WP-CLI `--user` flag — not suitable for untrusted environments |

---

## Responsible Disclosure

Report security vulnerabilities privately via GitHub Security Advisories:

https://github.com/bharatkabharman-pixel/tr-wordpress-ai-toolkit/security/advisories/new

Do not open public issues for security vulnerabilities.
