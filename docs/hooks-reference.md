# Hooks Reference

All actions and filters provided by TR WordPress AI Toolkit for developers.

---

## Actions

### `tr_ai_toolkit_loaded`

Fires after the plugin has fully initialised — all abilities are registered, admin is loaded, and the MCP server is registered.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$plugin` | `TR_AI_Toolkit_Plugin` | The plugin singleton instance |

**Example:**

```php
add_action( 'tr_ai_toolkit_loaded', function ( TR_AI_Toolkit_Plugin $plugin ) {
    // The plugin is ready. All abilities are registered.
    $settings = $plugin->get_settings();
    $logger   = $plugin->get_logger();
} );
```

---

### `tr_ai_toolkit_prune_logs`

WP-Cron action that runs daily to delete audit log entries older than the configured retention period.

Fired internally. You can trigger it manually for testing:

```php
do_action( 'tr_ai_toolkit_prune_logs' );
```

---

## WordPress Core Hooks Used

The plugin attaches to these standard WordPress hooks. Useful to know if you are building integrations.

| Hook | Type | Priority | Purpose |
|------|------|----------|---------|
| `plugins_loaded` | action | 10 | Boots the plugin after all plugins load |
| `wp_abilities_api_categories_init` | action | 10 | Registers the `tr-ai-toolkit` ability category |
| `wp_abilities_api_init` | action | 10 | Registers all 35 abilities |
| `mcp_adapter_init` | action | 10 | Creates the dedicated `/wp-json/tr-ai-toolkit/mcp` server |
| `admin_menu` | action | 10 | Registers admin menu pages |
| `admin_enqueue_scripts` | action | 10 | Enqueues CSS and JS on plugin pages |
| `admin_post_tr_ai_toolkit_save_settings` | action | 10 | Handles settings form submission |
| `admin_notices` | action | 10 | Shows MCP Adapter missing notice |
| `tr_ai_toolkit_prune_logs` | action | 10 | Daily log pruning (WP-Cron) |

---

## Extending With Custom Abilities

You do not need to modify the plugin to add new abilities. Register them from your own plugin using the same hooks:

```php
add_action( 'wp_abilities_api_categories_init', function () {
    // Optional: register your own category.
    wp_register_ability_category( 'my-plugin', array(
        'label'       => __( 'My Plugin', 'my-plugin' ),
        'description' => __( 'Custom abilities from My Plugin.', 'my-plugin' ),
    ) );
} );

add_action( 'wp_abilities_api_init', function () {
    wp_register_ability( 'my-plugin/get-stats', array(
        'label'       => __( 'Get Stats', 'my-plugin' ),
        'description' => __( 'Returns custom statistics.', 'my-plugin' ),
        'category'    => 'my-plugin',
        'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
        'output_schema' => array( 'type' => 'object' ),
        'permission_callback' => fn() => current_user_can( 'read' ),
        'execute_callback'    => fn( array $input ) => array( 'stat' => 42 ),
        'meta' => array( 'mcp' => array( 'public' => true ) ),
    ) );
} );
```

The MCP Adapter will automatically expose your ability alongside TR AI Toolkit abilities.

---

## Modifying Plugin Behaviour

### Disable a specific module

```php
add_filter( 'option_tr_ai_toolkit_settings', function ( $settings ) {
    // Remove SEO module from exposed modules.
    if ( isset( $settings['mcp']['exposed_modules'] ) ) {
        $settings['mcp']['exposed_modules'] = array_diff(
            $settings['mcp']['exposed_modules'],
            array( 'seo' )
        );
    }
    return $settings;
} );
```

### Force-enable plugin for specific user role

```php
add_action( 'tr_ai_toolkit_loaded', function ( $plugin ) {
    // Allow shop_manager role to use write abilities.
    add_filter( 'user_has_cap', function ( $caps, $cap_check, $args, $user ) {
        if ( $user && in_array( 'shop_manager', $user->roles, true ) ) {
            if ( 'edit_posts' === $cap_check[0] ) {
                $caps['edit_posts'] = true;
            }
        }
        return $caps;
    }, 10, 4 );
} );
```
