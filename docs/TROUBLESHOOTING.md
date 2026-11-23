# Troubleshooting

Common issues and their solutions.

---

## Tools appear in the list but clicking them does nothing

**Symptom:** Claude.ai shows the tools in `tools/list` but calling any tool fails silently or returns an error.

**Cause:** Tool names contained forward slashes (e.g. `tr-ai-toolkit/list-posts`), which are invalid per the MCP spec (`[a-zA-Z0-9_-]+`). Fixed in v1.2.2 — all tool names now use hyphens.

**Fix:** Update to v1.2.2 or later.

---

## "Authentication required" error

**Symptom:** Every MCP request returns `{"error": {"code": -32001, "message": "Authentication required."}}`.

**Causes and fixes:**

1. **Missing Authorization header** — Add `Authorization: Basic <base64(user:app_password)>` to every request.
2. **Wrong credentials** — Re-generate via Quick Connect. Application Passwords are different from user passwords.
3. **Application Passwords disabled** — Some hosting providers disable Application Passwords. Check `define('DISABLE_APP_PASSWORDS', false);` in `wp-config.php`. Or ask your host to enable Application Passwords.
4. **User account issue** — The WordPress user must exist and be active.

---

## "Service unavailable" (503)

**Cause:** The plugin is disabled via **Settings → General → Enable Plugin**.

**Fix:** Check the box and save settings.

---

## "Method not found" on tools/call

**Cause 1:** Sending a tool name that doesn't exist (e.g. typo, wrong prefix).

**Fix:** Call `tools/list` first to get the exact tool names.

**Cause 2:** The module containing the tool is disabled.

**Fix:** Go to **Settings → MCP Server → Exposed Modules** and enable the relevant module.

---

## WooCommerce tools not appearing

**Cause:** WooCommerce is not installed or not active.

**Fix:** Install and activate WooCommerce. The `woocommerce` module auto-skips registration when WooCommerce is inactive.

---

## find-unused-media returns empty

**Fixed in Phase 2 (branch `feature/production-ready-mcp`).** The original code used an incorrect `meta_query` that could never match. The fix uses a direct SQL query to find attachment IDs used as featured images.

---

## SEO pagination returns duplicate results

**Fixed in Phase 2.** The original code fetched `per_page × 3` posts then filtered in PHP, causing page overlap. The fix uses a proper database-level `meta_query`.

---

## "Invalid or expired session"

**Cause:** Session tokens expire after 1 hour. Many MCP clients handle this automatically by re-initializing.

**Fix:** Send a new `initialize` request to get a fresh session. Or configure your client to re-initialize on 401 responses.

---

## Connection test fails but credentials are correct

Check in order:

1. **Permalink structure** — Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules. Plain permalinks break REST API routing.
2. **REST API disabled** — Some security plugins block `/wp-json/`. Whitelist `tr-ai-toolkit/*` or temporarily disable the security plugin to test.
3. **HTTPS redirect loop** — If your site forces HTTPS but your cert is self-signed, the internal `rest_do_request()` test will pass but external clients may fail. Use a valid SSL certificate.
4. **Firewall/WAF blocking** — Your WAF may be blocking JSON POST requests. Whitelist the MCP endpoint.

---

## Activity Log shows no entries

**Causes:**

1. Logging is disabled — go to **Settings → Logging → Enable Audit Log** and check the box.
2. All tools are failing before logging — check the error response from the tool.

---

## PHP compatibility errors

**PHP < 8.0:** The plugin uses `match()` expressions (PHP 8.0+) and named arguments (PHP 8.0+) in some places. The minimum tested version is PHP 7.4 but PHP 8.1+ is strongly recommended.

---

## Getting help

- Open an issue: https://github.com/bharatkabharman-pixel/tr-wordpress-ai-toolkit/issues
- Include: WordPress version, PHP version, plugin version, the exact error message.
