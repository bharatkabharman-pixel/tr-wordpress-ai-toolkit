# Connecting Claude Desktop

Step-by-step guide for connecting Claude Desktop to your WordPress site.

---

## Option A — HTTP Transport (Recommended for Remote Sites)

Use this when Claude Desktop and WordPress are on different machines (typical production setup).

### 1. Generate credentials

Go to **TR AI Toolkit → Connection Guide** → click **Generate Connection Config**.

Copy the generated JSON. It looks like:

```json
{
  "mcpServers": {
    "tr-ai-toolkit": {
      "type": "http",
      "url": "https://your-site.com/wp-json/tr-ai-toolkit/mcp",
      "headers": {
        "Authorization": "Basic dXNlcm5hbWU6cGFzc3dvcmQ="
      }
    }
  }
}
```

### 2. Open claude_desktop_config.json

| OS | Path |
|---|---|
| macOS | `~/Library/Application Support/Claude/claude_desktop_config.json` |
| Windows | `%APPDATA%\Claude\claude_desktop_config.json` |
| Linux | `~/.config/claude/claude_desktop_config.json` |

### 3. Merge the config

If the file already has `mcpServers`, add the `tr-ai-toolkit` key inside the existing object:

```json
{
  "mcpServers": {
    "existing-server": { ... },
    "tr-ai-toolkit": {
      "type": "http",
      "url": "https://your-site.com/wp-json/tr-ai-toolkit/mcp",
      "headers": {
        "Authorization": "Basic dXNlcm5hbWU6cGFzc3dvcmQ="
      }
    }
  }
}
```

### 4. Restart Claude Desktop

Quit and reopen. The TR AI Toolkit tools will appear in the tool list.

---

## Option B — STDIO Transport (Local WordPress Server)

Use this when Claude Desktop and WordPress run on the **same machine** (local development).

Requires [WP-CLI](https://wp-cli.org/) installed and in `$PATH`.

### Config

```json
{
  "mcpServers": {
    "tr-ai-toolkit": {
      "command": "wp",
      "args": [
        "mcp-adapter",
        "serve",
        "--server=tr-ai-toolkit",
        "--user=your-admin-username",
        "--path=/path/to/wordpress"
      ]
    }
  }
}
```

Replace `your-admin-username` and `/path/to/wordpress` with your values.

---

## Verifying the Connection

After restarting Claude Desktop:

1. Open a new conversation.
2. Click the tools icon (hammer) — `tr-ai-toolkit` should appear.
3. Ask: `"List the 5 most recent posts on my site."`

If tools don't appear, see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).
