# Connecting Claude.ai (Web) and Claude Code

---

## Claude.ai Web — Custom Connector

### 1. Generate credentials

Go to **TR AI Toolkit → Connection Guide** → click **Generate Connection Config**.

Note your:
- **MCP URL** — `https://your-site.com/wp-json/tr-ai-toolkit/mcp`
- **Authorization header value** — `Basic dXNlcm5hbWU6...`

### 2. Add connector in Claude.ai

1. Open [claude.ai](https://claude.ai) and go to any **Project**.
2. Click **Add custom connector**.
3. Fill in:
   - **Server URL**: paste the MCP URL
   - **Authentication**: select **No sign-in**
   - **Add Header** → Name: `Authorization`, Value: paste the header value
   - **Transport**: leave as **Streamable HTTP** (default)
4. Click **Save**.

### 3. Test

In the project, ask: `"What WordPress plugins are active on my site?"`

---

## Claude Code (CLI)

Claude Code uses the same `claude_desktop_config.json` format as Claude Desktop.

### Setup

```bash
# Open config (macOS)
open ~/Library/Application\ Support/Claude/claude_desktop_config.json
```

Add the tr-ai-toolkit server block (same as [CLAUDE_DESKTOP.md](CLAUDE_DESKTOP.md) Option A).

### Using in a session

Once configured, Claude Code automatically loads MCP servers on startup. Run:

```bash
claude
```

Then ask any WordPress-related question. Claude Code will call the appropriate tools.

### Inline server specification

You can also specify the MCP server inline without editing the config file:

```bash
claude --mcp-server tr-ai-toolkit=https://your-site.com/wp-json/tr-ai-toolkit/mcp
```

---

## Available Tools in Claude.ai / Claude Code

After connecting, all 58 tools from the enabled modules are available. See [AVAILABLE_TOOLS.md](AVAILABLE_TOOLS.md) for the full list.

Quick reference by module:

| Module | Tool count | Example |
|---|---|---|
| Content | 14 | List posts, create draft, update post |
| Media | 5 | Browse library, find unused media |
| SEO | 5 | Find missing meta, check readability |
| Health | 7 | PHP info, plugin list, server status |
| Users | 4 | List users, get user by role |
| WooCommerce | 12 | Products, orders, customers, coupons |
| Comments | 6 | List, approve, trash, reply |
| Custom Post Types | 5 | List CPTs, CRUD custom posts |
