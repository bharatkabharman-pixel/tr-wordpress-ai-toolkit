=== TR WordPress AI Toolkit ===
Contributors: trsoftech
Tags: ai, mcp, model context protocol, claude, ai assistant, content management
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
Requires Plugins: mcp-adapter
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect AI assistants (Claude, Copilot and any MCP client) to your WordPress site. Manage content, media, SEO and health using natural language.

== Description ==

**TR WordPress AI Toolkit** bridges your WordPress site with AI assistants through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io). Once connected, you can manage your site using natural language.

**Example commands:**

- "Show all published posts."
- "Create a new draft post about WordPress trends."
- "Find posts without featured images."
- "List all media files uploaded this month."
- "Give me an SEO summary of this site."
- "What plugins are active on this site?"
- "Show recently registered users."
- "Find posts missing meta descriptions."

**How it works:**

The plugin registers WordPress Abilities using the native WordPress Abilities API (included in WordPress 6.9+). The [MCP Adapter plugin](https://wordpress.org/plugins/mcp-adapter/) (required dependency) exposes these abilities to any MCP-compatible AI client.

**Modules included:**

* **Content** — List, search, create and update posts, pages, categories and tags (14 abilities)
* **Media** — Browse and update media library metadata (5 abilities)
* **SEO Analysis** — Identify missing meta titles, descriptions and featured images (5 abilities)
* **Website Health** — Inspect WordPress version, plugins, theme and cron status (7 abilities)
* **Users** — List users and inspect roles (4 abilities)

**Security built in:**

* Every ability has a WordPress capability check
* Write operations require `edit_posts` or higher
* Admin operations require `manage_options`
* All inputs are validated and escaped
* Audit log records all invocations
* Sensitive data (passwords, tokens, hashes) is never exposed

== Installation ==

1. Install and activate the **MCP Adapter** plugin first (search for "MCP Adapter" in Plugins → Add New).
2. Upload `tr-wordpress-ai-toolkit.zip` via Plugins → Add New → Upload Plugin.
3. Activate the plugin.
4. Go to **TR AI Toolkit → Connection Guide** for step-by-step AI client setup.

**Minimum requirements:**

* WordPress 6.9 or higher
* PHP 8.1 or higher
* MCP Adapter plugin (official WordPress plugin)

== Frequently Asked Questions ==

= Do I need the MCP Adapter plugin? =

Yes. The MCP Adapter is a required dependency. It is the official plugin that bridges the WordPress Abilities API to the Model Context Protocol. Install it from wordpress.org/plugins/mcp-adapter.

= Which AI clients are supported? =

Any MCP-compatible client: Claude.ai Projects, Claude Desktop, Cursor, Windsurf, Codeium and others. The plugin supports both HTTP and STDIO transports.

= Is write access safe? =

Write operations (create/update post, update media metadata) require the `edit_posts` WordPress capability. Admin operations (health, plugins, users) require `manage_options`. Read operations require `read`. You can adjust capability requirements in Settings.

= Does this work on multisite? =

The plugin is compatible with single-site WordPress. Multisite compatibility is planned for a future release.

= Where is the MCP endpoint URL? =

Go to TR AI Toolkit → Connection Guide after activating the plugin.

== Screenshots ==

1. Dashboard showing MCP status, module overview and recent activity.
2. Settings page — configure modules, permissions and logging.
3. Connection Guide with endpoint URLs and Claude Desktop configuration.
4. Activity Log showing recent ability invocations.

== Changelog ==

= 1.0.0 =
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

= 1.0.0 =
Initial release.
