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

	/**
	 * Creates the database table and stores the plugin version.
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
			),
			'logging'     => array(
				'enabled'        => true,
				'retention_days' => 30,
			),
		);
	}
}
