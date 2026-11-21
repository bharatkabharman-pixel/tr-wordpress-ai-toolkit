# Installation Guide

## Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| WordPress   | 6.9     | Latest      |
| PHP         | 8.1     | 8.3         |
| MCP Adapter | 0.6.0   | 0.7.2+      |
| HTTPS       | —       | Required for HTTP transport |

## Step-by-Step Installation

### Step 1: Install the MCP Adapter Plugin

The MCP Adapter is a required dependency. Install it first.

**Option A — WordPress Admin:**
1. Go to **Plugins → Add New**.
2. Search for **"MCP Adapter"**.
3. Install and activate the official plugin by WordPress.org.

**Option B — WP-CLI:**
```bash
wp plugin install mcp-adapter --activate
```

### Step 2: Install TR WordPress AI Toolkit

**Option A — Upload ZIP:**
1. Download `tr-wordpress-ai-toolkit.zip` from the GitHub Releases page.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Select the ZIP file and click **Install Now**.
4. Activate the plugin.

**Option B — WP-CLI:**
```bash
wp plugin install https://github.com/trsoftech/tr-wordpress-ai-toolkit/releases/latest/download/tr-wordpress-ai-toolkit.zip --activate
```

**Option C — Manual (from source):**
```bash
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/trsoftech/tr-wordpress-ai-toolkit.git
wp plugin activate tr-wordpress-ai-toolkit
```

### Step 3: Verify Installation

1. After activation, go to **TR AI Toolkit** in the WordPress admin menu.
2. The dashboard should show:
   - MCP Adapter: **Active** ✓
   - Abilities API: **Available (WP 6.9+)** ✓
   - MCP Status: **Connected** ✓

### Step 4: Create Application Password

1. Go to **Users → Your Profile → Application Passwords**.
2. Enter a name (e.g., "Claude AI").
3. Click **Add New Application Password**.
4. **Copy the password** — it won't be shown again.

### Step 5: Connect Your AI Client

Go to **TR AI Toolkit → Connection Guide** for step-by-step instructions specific to your AI client (Claude.ai, Claude Desktop, Cursor, etc.).

---

## Troubleshooting

### "MCP Adapter Not Active" warning

Install the [MCP Adapter plugin](https://wordpress.org/plugins/mcp-adapter/) from WordPress.org.

### Abilities not registering

Verify WordPress 6.9+ is installed. The Abilities API (`wp_register_ability()`) is built into WordPress 6.9+. Earlier versions are not supported.

### 404 on the MCP endpoint

1. Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules.
2. Verify the MCP Adapter is active.
3. Check the endpoint URL in **TR AI Toolkit → Connection Guide**.

### Authentication failing (HTTP transport)

1. Ensure HTTPS is enabled — HTTP transport requires HTTPS for Application Password authentication.
2. Verify the Application Password was created for the correct user.
3. The user must have at least the `read` capability.

### Permission denied errors

The user whose Application Password is used must have the required capabilities:
- Read operations: `read` (all users)
- Write operations: `edit_posts` (contributors and above)
- Admin operations: `manage_options` (administrators only)

You can adjust the minimum capabilities in **TR AI Toolkit → Settings → Permissions**.

---

## Uninstalling

1. Deactivate the plugin via **Plugins → Installed Plugins**.
2. Click **Delete** to remove the plugin.

Uninstalling removes all plugin data:
- The `wp_tr_ai_toolkit_logs` database table
- The `tr_ai_toolkit_settings` and `tr_ai_toolkit_version` options

Your WordPress content (posts, pages, media, users) is never modified.
