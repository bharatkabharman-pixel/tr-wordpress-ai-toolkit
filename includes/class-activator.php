<?php
/**
 * Plugin activator.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation.
 */
class TR_AI_Toolkit_Activator {

	const MCP_ADAPTER_SLUG = 'mcp-adapter';
	const MCP_ADAPTER_FILE = 'mcp-adapter/mcp-adapter.php';

	/**
	 * Creates the database table, stores the plugin version,
	 * and auto-installs the MCP Adapter if not already present.
	 */
	public static function activate(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'tr_ai_toolkit_logs';

		$sql = "CREATE TABLE {$table_name} (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			log_time     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			ability      VARCHAR(255)        NOT NULL DEFAULT '',
			user_id      BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			input_data   LONGTEXT                     DEFAULT NULL,
			result_status VARCHAR(20)        NOT NULL DEFAULT 'success',
			message      TEXT                         DEFAULT NULL,
			ip_address   VARCHAR(45)         NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY ability   (ability(100)),
			KEY user_id   (user_id),
			KEY log_time  (log_time)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'tr_ai_toolkit_version', TR_AI_TOOLKIT_VERSION );

		if ( false === get_option( 'tr_ai_toolkit_settings' ) ) {
			update_option( 'tr_ai_toolkit_settings', self::default_settings() );
		}

		// Auto-install and activate MCP Adapter if not present.
		self::maybe_install_mcp_adapter();
	}

	/**
	 * Installs and activates the MCP Adapter plugin if it is not already
	 * installed or active.
	 */
	public static function maybe_install_mcp_adapter(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Already active — nothing to do.
		if ( is_plugin_active( self::MCP_ADAPTER_FILE ) ) {
			return;
		}

		// Installed but not active — just activate it.
		if ( file_exists( WP_PLUGIN_DIR . '/' . self::MCP_ADAPTER_FILE ) ) {
			activate_plugin( self::MCP_ADAPTER_FILE );
			update_option( 'tr_ai_toolkit_mcp_adapter_auto_activated', true );
			return;
		}

		// Not installed — download and install from WordPress.org.
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		// Fetch plugin info from WordPress.org API.
		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => self::MCP_ADAPTER_SLUG,
				'fields' => array( 'short_description' => false, 'sections' => false ),
			)
		);

		if ( is_wp_error( $api ) ) {
			update_option( 'tr_ai_toolkit_mcp_adapter_install_error', $api->get_error_message() );
			return;
		}

		// Use a silent upgrader skin so no HTML output is printed during activation.
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) || ! $result ) {
			$error = is_wp_error( $result ) ? $result->get_error_message() : 'Installation returned false.';
			update_option( 'tr_ai_toolkit_mcp_adapter_install_error', $error );
			return;
		}

		// Activate after successful install.
		$activated = activate_plugin( self::MCP_ADAPTER_FILE );

		if ( is_wp_error( $activated ) ) {
			update_option( 'tr_ai_toolkit_mcp_adapter_install_error', $activated->get_error_message() );
			return;
		}

		// Mark as auto-installed so we can show a success notice.
		update_option( 'tr_ai_toolkit_mcp_adapter_auto_installed', true );
		delete_option( 'tr_ai_toolkit_mcp_adapter_install_error' );
	}

	/**
	 * Returns the default plugin settings array.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'general'     => array(
				'plugin_enabled' => true,
				'log_level'      => 'info',
			),
			'permissions' => array(
				'read_capability'  => 'read',
				'write_capability' => 'edit_posts',
				'admin_capability' => 'manage_options',
			),
			'mcp'         => array(
				'server_name'     => get_bloginfo( 'name' ) . ' - TR AI Toolkit',
				'exposed_modules' => array( 'content', 'media', 'seo', 'health', 'users' ),
				'disabled_tools'  => array(),
			),
			'logging'      => array(
				'enabled'        => true,
				'retention_days' => 30,
			),
			'integrations' => array(
				'pagespeed_api_key' => '',
			),
		);
	}
}
