# Available Tools Reference

TR WordPress AI Toolkit exposes **58 tools** across 8 modules. All tool names follow the pattern `tr-ai-toolkit-{action}` or `tr-ai-toolkit-{prefix}-{action}`.

---

## Content Module (14 tools)

Manage posts, pages, categories, and tags.

| Tool | Description | Required Capability |
|---|---|---|
| `tr-ai-toolkit-list-posts` | List posts with filters (type, status, category, tag, search, date range, per_page, page) | `read` |
| `tr-ai-toolkit-get-post` | Get a single post by ID including meta, categories, tags | `read` |
| `tr-ai-toolkit-create-post` | Create a new post or page | `edit_posts` |
| `tr-ai-toolkit-update-post` | Update an existing post's title, content, status, meta | `edit_posts` |
| `tr-ai-toolkit-delete-post` | Move a post to trash | `delete_posts` |
| `tr-ai-toolkit-list-categories` | List all categories with post counts | `read` |
| `tr-ai-toolkit-get-category` | Get a single category by ID or slug | `read` |
| `tr-ai-toolkit-create-category` | Create a new category | `edit_posts` |
| `tr-ai-toolkit-list-tags` | List all tags with post counts | `read` |
| `tr-ai-toolkit-get-tag` | Get a single tag by ID or slug | `read` |
| `tr-ai-toolkit-create-tag` | Create a new tag | `edit_posts` |
| `tr-ai-toolkit-search-content` | Full-text search across posts and pages | `read` |
| `tr-ai-toolkit-get-post-revisions` | List revisions for a post | `edit_posts` |
| `tr-ai-toolkit-get-post-meta` | Get all custom fields for a post | `read` |

---

## Media Module (5 tools)

Browse and manage the media library.

| Tool | Description | Required Capability |
|---|---|---|
| `tr-ai-toolkit-list-media` | List media files with filters (type, date, search, per_page, page) | `read` |
| `tr-ai-toolkit-get-media` | Get details for a single media file | `read` |
| `tr-ai-toolkit-update-media-metadata` | Update alt text, caption, description for a media item | `edit_posts` |
| `tr-ai-toolkit-find-unused-media` | Find media files with no parent post and not used as featured image | `read` |
| `tr-ai-toolkit-delete-media` | Permanently delete a media file | `delete_posts` |

---

## SEO Module (5 tools)

Audit SEO metadata and content quality.

| Tool | Description | Required Capability |
|---|---|---|
| `tr-ai-toolkit-analyze-post-seo` | Analyze a single post's SEO: title length, meta description, keyword density, readability | `read` |
| `tr-ai-toolkit-find-missing-meta` | Find published posts missing SEO title or description (Yoast/RankMath/AIOSEO aware) | `read` |
| `tr-ai-toolkit-get-seo-overview` | Site-wide SEO health summary: coverage percentage, missing counts | `read` |
| `tr-ai-toolkit-analyze-readability` | Analyze content readability: sentence length, passive voice, Flesch score estimate | `read` |
| `tr-ai-toolkit-get-duplicate-titles` | Find posts sharing the same title | `read` |

---

## Health Module (7 tools)

Inspect server and WordPress environment.

| Tool | Description | Required Capability |
|---|---|---|
| `tr-ai-toolkit-get-server-info` | PHP version, memory limit, max execution time, server software | `manage_options` |
| `tr-ai-toolkit-get-wordpress-info` | WP version, multisite, debug mode, active theme, language | `manage_options` |
| `tr-ai-toolkit-list-active-plugins` | All active plugins with version and update availability | `manage_options` |
| `tr-ai-toolkit-get-database-info` | DB version, table prefix, database size | `manage_options` |
| `tr-ai-toolkit-check-filesystem` | WordPress file/directory write permissions | `manage_options` |
| `tr-ai-toolkit-get-cron-jobs` | Scheduled cron events with next run time | `manage_options` |
| `tr-ai-toolkit-get-site-health-score` | WordPress Site Health status (critical/recommended counts) | `manage_options` |

---

## Users Module (4 tools)

Inspect users and roles.

| Tool | Description | Required Capability |
|---|---|---|
| `tr-ai-toolkit-list-users` | List users with filters (role, search, per_page, page) | `list_users` |
| `tr-ai-toolkit-get-user` | Get a user's profile (email masked) | `list_users` |
| `tr-ai-toolkit-get-user-roles` | List all registered roles and capabilities | `read` |
| `tr-ai-toolkit-get-current-user` | Get the authenticated user's own profile | `read` |

---

## WooCommerce Module (12 tools)

Manage products, orders, customers, and coupons. Auto-skipped when WooCommerce is inactive.

| Tool | Description | Required Capability |
|---|---|---|
| `tr-ai-toolkit-wc-get-products` | List products with filters (status, category, search, per_page, page) | `read` |
| `tr-ai-toolkit-wc-get-product` | Get a single product's details | `read` |
| `tr-ai-toolkit-wc-create-product` | Create a new product | `edit_posts` |
| `tr-ai-toolkit-wc-update-product` | Update product title, price, status, stock, description | `edit_posts` |
| `tr-ai-toolkit-wc-delete-product` | Move product to trash | `delete_posts` |
| `tr-ai-toolkit-wc-get-orders` | List orders with filters (status, date_from, date_to, per_page, page) | `read` |
| `tr-ai-toolkit-wc-get-order` | Get full order details including line items | `read` |
| `tr-ai-toolkit-wc-get-customers` | List customers (email masked) | `list_users` |
| `tr-ai-toolkit-wc-get-customer` | Get a customer profile (email masked) | `list_users` |
| `tr-ai-toolkit-wc-get-coupons` | List coupons with type, amount, usage count | `manage_options` |
| `tr-ai-toolkit-wc-get-coupon` | Get a single coupon's details | `manage_options` |
| `tr-ai-toolkit-wc-get-sales-report` | Summarize sales totals for a date range | `manage_options` |

---

## Comments Module (6 tools)

Moderate comments.

| Tool | Description | Required Capability |
|---|---|---|
| `tr-ai-toolkit-list-comments` | List comments with filters (status, post_id, per_page, page) | `moderate_comments` |
| `tr-ai-toolkit-get-comment` | Get a single comment | `moderate_comments` |
| `tr-ai-toolkit-approve-comment` | Approve a pending comment | `moderate_comments` |
| `tr-ai-toolkit-trash-comment` | Move a comment to trash | `moderate_comments` |
| `tr-ai-toolkit-reply-to-comment` | Post a reply to a comment | `moderate_comments` |
| `tr-ai-toolkit-delete-comment` | Permanently delete a comment | `moderate_comments` |

---

## Custom Post Types Module (5 tools)

Manage any registered custom post type.

| Tool | Description | Required Capability |
|---|---|---|
| `tr-ai-toolkit-cpt-list-types` | List all registered public custom post types | `read` |
| `tr-ai-toolkit-cpt-list-posts` | List posts of any CPT with filters | `read` |
| `tr-ai-toolkit-cpt-get-post` | Get a CPT post including custom fields | `read` |
| `tr-ai-toolkit-cpt-create-post` | Create a new CPT post with custom fields | `edit_posts` |
| `tr-ai-toolkit-cpt-update-post` | Update a CPT post and its custom fields | `edit_posts` |

---

## Notes

- Tools in disabled modules do not appear in `tools/list`.
- `woocommerce` module tools only register when `class WooCommerce` exists.
- Paginated tools accept `per_page` (1–100) and `page` parameters.
- Write tools log every invocation to the Activity Log.
