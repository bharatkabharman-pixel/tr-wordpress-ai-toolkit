# Connection Guide

Connect any MCP-compatible AI client to your WordPress site using TR WordPress AI Toolkit.

---

## Prerequisites

| Requirement | Minimum | Recommended |
|---|---|---|
| WordPress | 6.9 | Latest |
| PHP | 7.4 | 8.1+ |
| HTTPS | Optional (dev) | Required (production) |
| Application Passwords | Enabled (default) | — |

---

## Method 1 — Quick Connect (Recommended)

The fastest way. Generates credentials and config in one click.

1. In WordPress Admin go to **TR AI Toolkit → Connection Guide**.
2. Click **Generate Connection Config**.
3. Copy the resulting config JSON or Authorization header.
4. Paste into your AI client (see client-specific guides below).

Your Application Password is shown **once only**. Save it.

---

## Method 2 — Manual Credentials

If you already have an Application Password:

1. Go to **Users → Your Profile → Application Passwords**.
2. Create a new password named `Claude AI` (or any name).
3. Note your **WordPress username** and the generated **application password**.
4. Encode them: `base64(username:app_password)`.
5. Use as: `Authorization: Basic <encoded_value>`.

The Connection Guide page includes a browser-based encoder — no server round-trip needed.

---

## MCP Endpoint

```
POST https://your-site.com/wp-json/tr-ai-toolkit/mcp
```

**Transport:** Streamable HTTP (MCP 2025-03-26)

**Authentication:** HTTP Basic Auth with Application Password.

---

## Client-Specific Guides

| Client | Guide |
|---|---|
| Claude.ai (web) | See [CLAUDE_CODE.md](CLAUDE_CODE.md) |
| Claude Desktop | See [CLAUDE_DESKTOP.md](CLAUDE_DESKTOP.md) |
| Any JSON MCP client | Use the config from Quick Connect |

---

## Testing the Connection

After configuring your client, verify everything works:

1. In **TR AI Toolkit → Connection Guide**, click **Test MCP Connection**.
   - Green result = server is reachable.
   - Red result = check the [Troubleshooting Guide](TROUBLESHOOTING.md).

2. In your AI client, ask: `"What WordPress version is running on this site?"`
   - A correct answer = tools are working.

---

## Revoking Access

- **Quick Connect credentials**: Go to **Connection Guide** → click **Revoke Access**.
- **Manual credentials**: Go to **Users → Your Profile → Application Passwords** and delete the entry.
- Revoking immediately disconnects all AI clients using that credential.
