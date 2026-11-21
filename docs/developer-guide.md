# Developer Guide

How to extend, integrate with, and contribute to TR WordPress AI Toolkit.

---

## Architecture Overview

```
WordPress Site
├── WordPress 6.9+
│   └── Abilities API (wp_register_ability) — built into WP core
├── MCP Adapter plugin (required)
│   └── Bridges Abilities API → Model Context Protocol
│       ├── HTTP transport  → /wp-json/tr-ai-toolkit/mcp
│       └── STDIO transport → wp mcp-adapter serve
└── TR WordPress AI Toolkit (this plugin)
    ├── Registers 35 abilities on wp_abilities_api_init
    ├── Creates dedicated MCP server on mcp_adapter_init
    └── Provides admin dashboard
```

**Key principle:** The plugin does NOT implement the MCP protocol. It only registers WordPress Abilities. The MCP Adapter handles all protocol work.

---

## Plugin Bootstrap Flow

```
plugins_loaded (priority 10)
  └── tr_ai_toolkit_run()
        └── tr_ai_toolkit_check_requirements()   ← PHP 8.1+, WP 6.9+, Abilities API
              └── TR_AI_Toolkit_Plugin::get_instance()->run()
                    ├── TR_AI_Toolkit_Abilities_Manager::init()
                    │     ├── wp_abilities_api_categories_init → register_category()
                    │     └── wp_abilities_api_init → register_abilities()
                    │           ├── TR_AI_Toolkit_Content_Module::register()
                    │           ├── TR_AI_Toolkit_Media_Module::register()
                    │           ├── TR_AI_Toolkit_SEO_Module::register()
                    │           ├── TR_AI_Toolkit_Health_Module::register()
                    │           └── TR_AI_Toolkit_Users_Module::register()
                    ├── TR_AI_Toolkit_Admin::init()       (admin only)
                    └── TR_AI_Toolkit_MCP_Server::init()  (mcp_adapter_init hook)
```

---

## Adding a New Ability to an Existing Module

**Example:** Adding `tr-ai-toolkit/list-comments` to the Content module.

**Step 1 — Add the registration method** in `modules/content/class-content-module.php`:

```php
// In register(), add:
$this->register_list_comments();

// New method:
private function register_list_comments(): void {
    wp_register_ability(
        'tr-ai-toolkit/list-comments',
        array(
            'label'       => __( 'List Comments', 'tr-wordpress-ai-toolkit' ),
            'description' => __( 'Returns approved comments for a post.', 'tr-wordpress-ai-toolkit' ),
            'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
            'input_schema' => array(
                'type'       => 'object',
                'required'   => array( 'post_id' ),
                'properties' => array(
                    'post_id'  => array( 'type' => 'integer' ),
                    'per_page' => array( 'type' => 'integer' ),
                ),
            ),
            'output_schema' => array( 'type' => 'object' ),
            'permission_callback' => fn() => $this->permissions->can_read()
                ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
            'execute_callback'    => fn( array $input ) => $this->execute_list_comments( $input ),
            'meta' => array( 'mcp' => array( 'public' => true ) ),
        )
    );
}

private function execute_list_comments( array $input ): array|\WP_Error {
    $post_id = absint( $input['post_id'] ?? 0 );
    if ( ! $post_id ) {
        return new \WP_Error( 'invalid_input', 'post_id is required.' );
    }

    $comments = get_comments( array(
        'post_id' => $post_id,
        'status'  => 'approve',
        'number'  => min( 50, (int) ( $input['per_page'] ?? 20 ) ),
    ) );

    $result = array_map( fn( $c ) => array(
        'id'      => $c->comment_ID,
        'author'  => $c->comment_author,
        'content' => wp_strip_all_tags( $c->comment_content ),
        'date'    => $c->comment_date_gmt,
    ), $comments );

    $this->logger->log( 'tr-ai-toolkit/list-comments', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

    return array( 'comments' => $result, 'total' => count( $result ) );
}
```

**Step 2 — Add a PHPUnit test** in `tests/phpunit/ContentModuleTest.php`.

**Step 3 — Document** in `docs/abilities-reference.md`.

---

## Adding a New Module

**Example:** Adding a `forms` module for WPForms/Gravity Forms.

**Step 1 — Create the module file** at `modules/forms/class-forms-module.php`:

```php
<?php
defined( 'ABSPATH' ) || exit;

class TR_AI_Toolkit_Forms_Module {

    private TR_AI_Toolkit_Permissions $permissions;
    private TR_AI_Toolkit_Logger      $logger;

    public function __construct(
        TR_AI_Toolkit_Permissions $permissions,
        TR_AI_Toolkit_Logger $logger
    ) {
        $this->permissions = $permissions;
        $this->logger      = $logger;
    }

    public function register(): void {
        // Register abilities here.
    }
}
```

**Step 2 — Register in `includes/class-abilities-manager.php`:**

```php
// In $module_map array, add:
'forms' => array(
    'file'  => TR_AI_TOOLKIT_PATH . 'modules/forms/class-forms-module.php',
    'class' => 'TR_AI_Toolkit_Forms_Module',
),
```

**Step 3 — Add to default settings** in `includes/class-activator.php`:

```php
'exposed_modules' => array( 'content', 'media', 'seo', 'health', 'users', 'forms' ),
```

**Step 4 — Add module info** to the dashboard view in `admin/views/dashboard.php`:

```php
'forms' => array( 'name' => 'Forms', 'count' => 3, 'desc' => 'Form submissions and entries' ),
```

---

## Security Checklist for New Abilities

Every new ability must satisfy all of these:

- [ ] `permission_callback` calls `$this->permissions->can_read()`, `can_write()`, or `can_admin()` as appropriate
- [ ] `execute_callback` validates all required inputs and returns `WP_Error` on bad input
- [ ] All string inputs pass through `sanitize_text_field()` or appropriate function
- [ ] All HTML inputs pass through `wp_kses_post()` — never raw user HTML
- [ ] Integer inputs use `absint()` or `(int)` cast
- [ ] Array inputs are validated element-by-element
- [ ] No passwords, tokens, auth keys, or hashes in any output
- [ ] `$this->logger->log()` called at end of execute_callback
- [ ] `'meta' => ['mcp' => ['public' => true]]` is intentional (opt-in exposure)

---

## Settings API

Access settings anywhere:

```php
$plugin   = TR_AI_Toolkit_Plugin::get_instance();
$settings = $plugin->get_settings();

// Read a value (dot notation).
$cap = $settings->get( 'permissions.write_capability', 'edit_posts' );

// Update a group.
$settings->update_group( 'general', array( 'plugin_enabled' => false ) );

// Save full settings array.
$settings->save( $all_settings_array );
```

---

## Logger API

```php
$logger = $plugin->get_logger();

// Log success.
$logger->log( 'my-plugin/my-ability', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

// Log error.
$logger->log( 'my-plugin/my-ability', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, 'Something went wrong' );

// Log denied.
$logger->log( 'my-plugin/my-ability', $input, TR_AI_Toolkit_Logger::STATUS_DENIED );

// Get recent entries.
$rows = $logger->get_recent( 50 );
```

Fields named `password`, `token`, `secret`, `nonce`, `auth`, or `key` are automatically redacted from stored input.

---

## Running Tests Locally

```bash
# 1. Install WordPress test database (once).
bash tests/bin/install-wp-tests.sh wordpress_test root root localhost latest

# 2. Run all tests.
composer test

# 3. Run a specific test class.
vendor/bin/phpunit --filter ContentModuleTest

# 4. Run with coverage (requires Xdebug or PCOV).
vendor/bin/phpunit --coverage-html coverage/
```

---

## Code Standards

This project follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).

```bash
# Check.
composer lint

# Auto-fix.
composer lint-fix
```

---

## Generating the Translation POT File

After adding new translatable strings, regenerate the POT file:

```bash
# Using WP-CLI (recommended).
wp i18n make-pot . languages/tr-wordpress-ai-toolkit.pot --domain=tr-wordpress-ai-toolkit

# Or using xgettext (if WP-CLI not available).
xgettext --from-code=UTF-8 --language=PHP \
  --keyword=__ --keyword=_e --keyword=esc_html__ --keyword=esc_html_e \
  --keyword=esc_attr__ --keyword=esc_attr_e --keyword=_n:1,2 \
  --output=languages/tr-wordpress-ai-toolkit.pot \
  $(find . -name "*.php" -not -path "*/vendor/*" -not -path "*/tests/*")
```

Translators use the POT file to create `.po` files (e.g., `tr-wordpress-ai-toolkit-hi_IN.po`) which are compiled to `.mo` files and placed in `languages/`.
