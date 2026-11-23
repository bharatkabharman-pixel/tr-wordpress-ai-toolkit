# MCP Protocol Implementation

Technical reference for the Model Context Protocol server built into TR WordPress AI Toolkit.

---

## Protocol Version

**MCP 2025-03-26** (Streamable HTTP transport)

---

## Endpoint

```
GET|POST  /wp-json/tr-ai-toolkit/mcp
DELETE    /wp-json/tr-ai-toolkit/mcp
```

---

## Authentication

All requests (except `initialize`) require a valid WordPress authenticated user.

**Method:** HTTP Basic Authentication using a WordPress Application Password.

```
Authorization: Basic base64(username:app_password)
```

Application Passwords are managed at **Users → Your Profile → Application Passwords** or via the plugin's Quick Connect feature.

---

## Session Management

### Creating a session

Send an `initialize` request. The server returns an `Mcp-Session-Id` header.

```http
POST /wp-json/tr-ai-toolkit/mcp
Authorization: Basic <credentials>
Content-Type: application/json

{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {
    "protocolVersion": "2025-03-26",
    "capabilities": {},
    "clientInfo": { "name": "my-client", "version": "1.0" }
  }
}
```

Response includes `Mcp-Session-Id` header. Pass this header on subsequent requests.

### Session security

- Sessions are stored as WordPress transients (TTL: 1 hour).
- Each session is bound to the user who created it (`user_id` stored in the transient).
- Sending a session ID belonging to another user returns `401 Invalid or expired session`.
- Sessions expire automatically after 1 hour of inactivity.

### Terminating a session

```http
DELETE /wp-json/tr-ai-toolkit/mcp
Authorization: Basic <credentials>
Mcp-Session-Id: <session-id>
```

Only the owner of the session may delete it. Returns `204 No Content` on success.

---

## Supported Methods

| Method | Description |
|---|---|
| `initialize` | Negotiate protocol version, get server info |
| `notifications/initialized` | Acknowledge initialization (no-op, returns `null`) |
| `tools/list` | Get all available tool definitions |
| `tools/call` | Execute a tool |
| `ping` | Health check — returns `{}` |

---

## Protocol Negotiation

The server accepts the client's requested protocol version if it is in its supported list (`2025-03-26`). If the client requests an unsupported version, the server responds with the closest supported version. The agreed version is returned in `initialize` response.

---

## Tool Name Format

All tool names follow the pattern:

```
tr-ai-toolkit-{module-prefix}-{action}
```

Examples:
- `tr-ai-toolkit-list-posts`
- `tr-ai-toolkit-wc-get-products`
- `tr-ai-toolkit-cpt-list-posts`

Tool names match the regex `[a-zA-Z0-9_-]+` (MCP spec requirement — no forward slashes).

---

## Error Codes

| Code | Meaning |
|---|---|
| `-32700` | Parse error — request body is not valid JSON |
| `-32600` | Invalid request |
| `-32601` | Method not found / tool not found |
| `-32001` | Authentication required or invalid session |
| `-32000` | Service unavailable (plugin disabled) |
| `200` | Success |
| `401` | Authentication required |
| `503` | Plugin disabled |

---

## Example: Calling a Tool

```http
POST /wp-json/tr-ai-toolkit/mcp
Authorization: Basic <credentials>
Content-Type: application/json
Mcp-Session-Id: <session-id>

{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/call",
  "params": {
    "name": "tr-ai-toolkit-list-posts",
    "arguments": {
      "post_type": "post",
      "per_page": 10,
      "page": 1
    }
  }
}
```

Success response:

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "{ \"posts\": [...], \"total\": 42, \"pages\": 5 }"
      }
    ],
    "isError": false
  }
}
```
