# Permissions Reference

TR WordPress AI Toolkit enforces two layers of access control for every tool call.

---

## Layer 1 — Global Capability Gate

Configured in **Settings → Permissions**. Sets the minimum WordPress capability required per operation type.

| Setting | Default | Applies to |
|---|---|---|
| Read Capability | `read` | All read/list tools |
| Write Capability | `edit_posts` | Create, update, delete tools |
| Admin Capability | `manage_options` | Health, system info tools |

Any authenticated user **below** the required capability will receive a `403 Permission denied` response — no detail is leaked.

---

## Layer 2 — Per-Tool Capability Checks

Each tool performs its own `current_user_can()` check using the WordPress Abilities API (WP 6.9+). These are defined per module and cannot be bypassed by the global gate alone.

### Content module

| Tool | Capability |
|---|---|
| `list-posts`, `get-post`, `list-categories`, `list-tags` | `read` |
| `create-post`, `update-post` | `edit_posts` |
| `delete-post` | `delete_posts` |

### Media module

| Tool | Capability |
|---|---|
| `list-media`, `get-media`, `find-unused-media` | `read` |
| `update-media-metadata` | `edit_posts` |
| `delete-media` | `delete_posts` |

### SEO module

| Tool | Capability |
|---|---|
| All SEO tools | `read` |

### Health module

| Tool | Capability |
|---|---|
| All health tools | `manage_options` |

### Users module

| Tool | Capability |
|---|---|
| `list-users`, `get-user` | `list_users` |
| `get-user-roles`, `get-current-user` | `read` |

### WooCommerce module

| Tool | Capability |
|---|---|
| `wc-get-products`, `wc-get-product`, `wc-get-orders`, `wc-get-order` | `read` |
| `wc-create-product`, `wc-update-product` | `edit_posts` |
| `wc-delete-product` | `delete_posts` |
| `wc-get-customers`, `wc-get-customer` | `list_users` |
| `wc-get-coupons`, `wc-get-coupon` | `manage_options` |

### Comments module

| Tool | Capability |
|---|---|
| `list-comments`, `get-comment` | `moderate_comments` |
| `approve-comment`, `trash-comment`, `reply-to-comment` | `moderate_comments` |
| `delete-comment` | `moderate_comments` |

### Custom Post Types module

| Tool | Capability |
|---|---|
| `cpt-list-types`, `cpt-list-posts`, `cpt-get-post` | `read` |
| `cpt-create-post`, `cpt-update-post` | `edit_posts` |

---

## Sensitive Data Handling

The plugin automatically masks or omits sensitive fields in tool responses:

- **User emails** — displayed as `***@domain.com`
- **User passwords** — never returned
- **Log entries** — fields containing `password`, `token`, `secret`, `key`, `auth`, `nonce` are stored as `[REDACTED]`
- **MCP error messages** — do not leak internal tool names, file paths, or stack traces

---

## Raising the Security Bar

For maximum security (e.g., agencies managing client sites):

1. Set **Read Capability** to `edit_posts` — only editors and above can read data.
2. Set **Write Capability** to `publish_posts` — only authors and above can write.
3. Disable unused modules in **Settings → MCP Server → Exposed Modules**.
4. Use short Log Retention (7 days) to minimize stored audit data.
