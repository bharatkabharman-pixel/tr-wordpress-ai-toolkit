# Abilities Reference

Complete input/output schema for all 35 abilities registered by TR WordPress AI Toolkit.

All abilities are exposed via MCP when `meta.mcp.public = true` is set (default for all abilities in this plugin). They are also accessible through the WordPress REST API if the MCP Adapter plugin is active.

**Endpoint:** `POST /wp-json/tr-ai-toolkit/mcp`  
**Authentication:** WordPress Application Password (Basic Auth)

---

## Content Module

### `tr-ai-toolkit/list-posts`

Returns a paginated list of published posts.

**Min. capability:** `read`

**Input:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `per_page` | integer | No | 10 | Posts per page. Range: 1–100 |
| `page` | integer | No | 1 | Page number |
| `status` | string | No | `publish` | `publish`, `draft`, `pending`, `private`, `any`. Non-publish requires `edit_posts` |
| `category_id` | integer | No | — | Filter by category ID |
| `tag_id` | integer | No | — | Filter by tag ID |
| `author_id` | integer | No | — | Filter by author user ID |
| `orderby` | string | No | `date` | `date`, `title`, `ID`, `modified` |
| `order` | string | No | `DESC` | `ASC` or `DESC` |

**Output:**

```json
{
  "posts": [
    {
      "id": 42,
      "title": "Hello World",
      "status": "publish",
      "url": "https://example.com/hello-world/",
      "date": "2026-01-15 10:00:00",
      "modified": "2026-01-16 08:00:00",
      "author": { "id": 1, "name": "Admin" },
      "excerpt": "Short excerpt...",
      "categories": [{ "id": 3, "name": "News", "slug": "news" }],
      "tags": [{ "id": 7, "name": "WordPress", "slug": "wordpress" }],
      "featured_image": "https://example.com/wp-content/uploads/image.jpg",
      "comment_count": 5
    }
  ],
  "total": 120,
  "total_pages": 12,
  "page": 1,
  "per_page": 10
}
```

---

### `tr-ai-toolkit/get-post`

Returns full details of a single post.

**Min. capability:** `read`

**Input:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `post_id` | integer | **Yes** | The post ID |

**Output:** Same as list-posts item plus `content`, `slug`, `edit_url`.

---

### `tr-ai-toolkit/search-posts`

Full-text search across post titles and content.

**Min. capability:** `read`

**Input:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `query` | string | **Yes** | — | Search term |
| `per_page` | integer | No | 10 | Max 50 |
| `page` | integer | No | 1 | Page number |
| `status` | string | No | `publish` | Requires `edit_posts` for non-publish |

**Output:** Same structure as `list-posts`.

---

### `tr-ai-toolkit/create-post`

Creates a new WordPress post. **Defaults to draft status** for safety.

**Min. capability:** `edit_posts`

**Input:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `title` | string | **Yes** | — | Post title |
| `content` | string | No | `` | Post content (HTML or block markup) |
| `excerpt` | string | No | `` | Post excerpt |
| `status` | string | No | `draft` | `draft`, `pending`, `publish`. Publishing requires `publish_posts` |
| `category_ids` | array | No | — | Array of category IDs |
| `tag_ids` | array | No | — | Array of tag IDs |
| `author_id` | integer | No | current user | Requires `edit_others_posts` to set a different author |

**Output:**

```json
{
  "post_id": 101,
  "url": "https://example.com/?p=101",
  "edit_url": "https://example.com/wp-admin/post.php?post=101&action=edit",
  "status": "draft"
}
```

---

### `tr-ai-toolkit/update-post`

Updates an existing post. Only supplied fields are changed.

**Min. capability:** `edit_posts` + must pass `current_user_can('edit_post', $id)`

**Input:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `post_id` | integer | **Yes** | ID of the post to update |
| `title` | string | No | New post title |
| `content` | string | No | New post content |
| `excerpt` | string | No | New excerpt |
| `status` | string | No | `draft`, `pending`, `publish`, `private` |
| `category_ids` | array | No | Replaces all category assignments |
| `tag_ids` | array | No | Replaces all tag assignments |

**Output:**

```json
{ "post_id": 101, "status": "publish", "url": "https://example.com/my-post/" }
```

---

### `tr-ai-toolkit/delete-post`

Moves a post to the trash. **Never permanently deletes.**

**Min. capability:** `edit_posts` + `current_user_can('delete_post', $id)`

**Input:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `post_id` | integer | **Yes** | The post ID to trash |

**Output:**

```json
{ "post_id": 101, "status": "trash", "message": "Post moved to trash successfully." }
```

---

### `tr-ai-toolkit/list-pages`

Returns a paginated list of WordPress pages.

**Min. capability:** `read`

**Input:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `per_page` | integer | No | 10 | Range: 1–100 |
| `page` | integer | No | 1 | Page number |
| `status` | string | No | `publish` | `publish`, `draft`, `private`, `any` |
| `parent_id` | integer | No | — | Filter by parent. Use `0` for top-level pages |

**Output:**

```json
{
  "pages": [
    {
      "id": 5, "title": "About", "status": "publish",
      "url": "https://example.com/about/",
      "parent_id": 0, "menu_order": 2,
      "date": "2026-01-01 00:00:00", "modified": "2026-06-01 00:00:00"
    }
  ],
  "total": 8, "total_pages": 1, "page": 1, "per_page": 10
}
```

---

### `tr-ai-toolkit/get-page`

Full details of a single page.

**Min. capability:** `read`

**Input:** `page_id` (integer, required)

**Output:** Page data including `content`, `template`, `featured_image`.

---

### `tr-ai-toolkit/create-page`

Creates a new WordPress page (defaults to draft).

**Min. capability:** `edit_posts`

**Input:** `title` (required), `content`, `status`, `parent_id`, `template`.

---

### `tr-ai-toolkit/update-page`

Updates an existing page. Partial update — only supplied fields change.

**Min. capability:** `edit_posts` + `current_user_can('edit_page', $id)`

**Input:** `page_id` (required), `title`, `content`, `status`, `parent_id`.

---

### `tr-ai-toolkit/list-categories`

Returns all post categories.

**Min. capability:** `read`

**Input:** `hide_empty` (boolean, default false), `search` (string).

**Output:**

```json
{
  "categories": [
    { "id": 3, "name": "News", "slug": "news", "description": "", "parent_id": 0, "post_count": 15 }
  ],
  "total": 6
}
```

---

### `tr-ai-toolkit/create-category`

Creates a new post category.

**Min. capability:** `edit_posts`

**Input:** `name` (required), `slug`, `description`, `parent_id`.

**Output:** `{ "category_id": 10, "name": "Tech", "slug": "tech" }`

---

### `tr-ai-toolkit/list-tags`

Returns all post tags.

**Min. capability:** `read`

**Input:** `hide_empty` (boolean), `search` (string), `per_page` (integer, max 500, default 100).

---

### `tr-ai-toolkit/create-tag`

Creates a new post tag.

**Min. capability:** `edit_posts`

**Input:** `name` (required), `slug`, `description`.

---

## Media Module

### `tr-ai-toolkit/list-media`

Paginated list of media library items.

**Min. capability:** `read`

**Input:**

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `per_page` | integer | 20 | Range: 1–100 |
| `page` | integer | 1 | Page number |
| `mime_type` | string | — | e.g. `image`, `video`, `application/pdf` |
| `search` | string | — | Search by title/filename |
| `orderby` | string | `date` | `date`, `title`, `ID` |
| `order` | string | `DESC` | `ASC` or `DESC` |

**Output:**

```json
{
  "items": [
    {
      "id": 55, "title": "hero-banner",
      "url": "https://example.com/wp-content/uploads/2026/01/hero-banner.jpg",
      "mime_type": "image/jpeg",
      "date": "2026-01-10 09:00:00",
      "file_size": 204800, "width": 1920, "height": 1080,
      "parent_id": 0, "author_id": 1
    }
  ],
  "total": 342, "total_pages": 18, "page": 1, "per_page": 20
}
```

---

### `tr-ai-toolkit/get-media-details`

Full details including all image sizes, alt text, caption.

**Min. capability:** `read`

**Input:** `attachment_id` (integer, required)

**Output:** Full attachment data including `alt_text`, `caption`, `description`, `image_sizes` (all registered sizes), `file_meta`.

---

### `tr-ai-toolkit/search-media`

Searches the media library by title or filename.

**Min. capability:** `read`

**Input:** `query` (required), `per_page` (max 50), `mime_type`.

---

### `tr-ai-toolkit/update-media-metadata`

Updates the title, alt text, caption or description of an attachment.

**Min. capability:** `upload_files` + `current_user_can('edit_post', $id)`

**Input:** `attachment_id` (required), `title`, `alt_text`, `caption`, `description`.

---

### `tr-ai-toolkit/find-unused-media`

Returns attachments with no parent post (potential orphans). **Read-only — never deletes.**

**Min. capability:** `upload_files`

**Input:** `per_page` (max 100), `mime_type`.

---

## SEO Module

> SEO abilities automatically detect and use meta keys from Yoast SEO, Rank Math, and All in One SEO Pack. If no SEO plugin is active, the abilities check for custom meta fields with common key names.

### `tr-ai-toolkit/get-seo-summary`

Overall SEO health overview for the site.

**Min. capability:** `read`

**Input:** None.

**Output:**

```json
{
  "total_published_posts": 85,
  "missing_meta_titles": 12,
  "missing_meta_descriptions": 28,
  "missing_featured_images": 7,
  "seo_score_estimate": 71,
  "detected_seo_plugin": "yoast",
  "note": "Score is an estimate. Use a dedicated SEO tool for accurate audits."
}
```

`detected_seo_plugin` values: `yoast`, `rankmath`, `aioseo`, `none`.

---

### `tr-ai-toolkit/find-missing-meta-titles`

Posts and pages with no custom SEO meta title.

**Min. capability:** `read`

**Input:** `post_type` (`post`, `page`, `any`; default `post`), `per_page` (max 100).

**Output:**

```json
{
  "missing": [
    { "id": 42, "title": "Hello World", "url": "...", "edit_url": "...", "post_type": "post" }
  ],
  "count": 12,
  "type": "title"
}
```

---

### `tr-ai-toolkit/find-missing-meta-descriptions`

Posts and pages with no meta description.

Same input/output structure as `find-missing-meta-titles` with `"type": "description"`.

---

### `tr-ai-toolkit/find-posts-without-featured-images`

Published posts with no featured image (`_thumbnail_id` not set).

**Min. capability:** `read`

**Input:** `per_page` (max 100).

---

### `tr-ai-toolkit/analyze-content-structure`

Deep analysis of a single post or page.

**Min. capability:** `read`

**Input:** `post_id` (integer, required)

**Output:**

```json
{
  "post_id": 42,
  "title": "Hello World",
  "word_count": 850,
  "content_length": "medium",
  "headings": { "h2": 4, "h3": 2 },
  "image_count": 3,
  "internal_links": 5,
  "external_links": 2,
  "has_featured_image": true,
  "has_seo_title": true,
  "has_seo_description": false,
  "detected_seo_plugin": "yoast"
}
```

`content_length` values: `very_short` (<300 words), `short` (300–599), `medium` (600–1499), `long` (1500+).

---

## Health Module

> All Health abilities require `manage_options` capability (administrators only).  
> Sensitive server values (DB credentials, auth keys, file paths) are never included.

### `tr-ai-toolkit/get-wordpress-info`

**Output:**

```json
{
  "wordpress_version": "6.9.1",
  "site_url": "https://example.com",
  "home_url": "https://example.com",
  "site_name": "My Blog",
  "site_description": "Just another WordPress site",
  "language": "en_US",
  "timezone": "Asia/Kolkata",
  "admin_email": "***@example.com",
  "is_multisite": false,
  "wp_memory_limit": "256M",
  "wp_debug": false,
  "https_enabled": true
}
```

---

### `tr-ai-toolkit/get-php-version`

**Output:**

```json
{
  "php_version": "8.2.14",
  "php_major_minor": "8.2",
  "meets_wp_minimum": true,
  "meets_recommended": true,
  "max_execution_time": "30",
  "max_upload_size": 67108864,
  "extensions": {
    "curl": true, "dom": true, "mbstring": true, "mysqli": true,
    "openssl": true, "imagick": false, "intl": true, "zip": true
  }
}
```

---

### `tr-ai-toolkit/get-active-plugins`

**Output:**

```json
{
  "plugins": [
    {
      "slug": "mcp-adapter",
      "file": "mcp-adapter.php",
      "name": "MCP Adapter",
      "version": "0.7.2",
      "author": "WordPress.org",
      "url": "https://wordpress.org/plugins/mcp-adapter/"
    }
  ],
  "total": 8
}
```

---

### `tr-ai-toolkit/get-active-theme`

**Output:**

```json
{
  "name": "Twenty Twenty-Five",
  "version": "1.1",
  "author": "the WordPress team",
  "is_block_theme": true,
  "parent_theme": null,
  "features": {
    "post-thumbnails": true,
    "editor-styles": true,
    "wp-block-styles": true,
    "align-wide": true
  }
}
```

---

### `tr-ai-toolkit/get-site-health-summary`

**Output:**

```json
{
  "wordpress_version": "6.9.1",
  "php_version": "8.2.14",
  "https_enabled": true,
  "wp_debug": false,
  "auto_updates_core": "minor",
  "counts": {
    "published_posts": 85, "draft_posts": 12,
    "published_pages": 8, "media_items": 342, "total_users": 5
  },
  "memory": {
    "limit": "256M", "max_limit": "256M", "current_mb": 42.5
  }
}
```

---

### `tr-ai-toolkit/get-permalink-structure`

**Output:**

```json
{
  "structure": "/%year%/%monthnum%/%postname%/",
  "pretty_permalinks": true,
  "category_base": "category",
  "tag_base": "tag",
  "example_post_url": "https://example.com/2026/01/hello-world/"
}
```

---

### `tr-ai-toolkit/get-cron-status`

**Output:**

```json
{
  "wp_cron_disabled": false,
  "total_events": 14,
  "overdue_events": 0,
  "next_events": [
    { "hook": "wp_scheduled_delete", "next_run": "2026-11-22 00:00:00", "overdue": false },
    { "hook": "tr_ai_toolkit_prune_logs", "next_run": "2026-11-22 10:30:00", "overdue": false }
  ]
}
```

---

## Users Module

> Emails are always masked (`***@domain.com`). Password hashes, session tokens, and application passwords are never returned.

### `tr-ai-toolkit/list-users`

**Min. capability:** `list_users`

**Input:** `per_page` (max 100), `page`, `role` (slug), `orderby`, `order`.

**Output:**

```json
{
  "users": [
    {
      "id": 1, "login": "admin",
      "display_name": "Site Admin",
      "email": "***@example.com",
      "roles": ["administrator"],
      "registered": "2024-01-01 00:00:00"
    }
  ],
  "total": 5, "page": 1, "per_page": 20, "total_pages": 1
}
```

---

### `tr-ai-toolkit/get-user`

**Min. capability:** `list_users`

**Input:** `user_id` (integer, required)

**Output:** User data plus `bio`, `website`, `post_count`.

---

### `tr-ai-toolkit/search-users`

**Min. capability:** `list_users`

**Input:** `query` (required), `per_page` (max 50).

---

### `tr-ai-toolkit/get-user-roles`

**Min. capability:** `manage_options`

**Output:**

```json
{
  "roles": [
    {
      "slug": "administrator",
      "name": "Administrator",
      "capabilities": {
        "read": true, "edit_posts": true, "publish_posts": true,
        "manage_options": true, "delete_posts": true,
        "upload_files": true, "list_users": true
      }
    }
  ],
  "total": 5
}
```

---

## Error Responses

All abilities return a `WP_Error` on failure. MCP Adapter translates these to MCP error responses.

| Error Code | HTTP Status | Cause |
|------------|-------------|-------|
| `tr_ai_toolkit_forbidden` | 403 | Insufficient WordPress capability |
| `forbidden` | 403 | Specific post/page ownership check failed |
| `not_found` | — | Post, page, user or attachment does not exist |
| `invalid_input` | — | Required field missing or invalid value |
| `trash_failed` | — | `wp_trash_post()` returned false |
