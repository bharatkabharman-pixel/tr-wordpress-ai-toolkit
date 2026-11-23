<?php
/**
 * Plugin Name: TR WordPress AI Toolkit
 * Plugin URI: https://trsoftech.com/tr-wordpress-ai-toolkit
 * Description: Connect AI assistants (Claude, Copilot, and any MCP client) to your WordPress site via the Model Context Protocol. Manage content, media, SEO, and site health using natural language.
 * Version: 1.3.0
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author: True Response Softech Pvt. Ltd.
 * Author URI: https://trsoftech.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tr-wordpress-ai-toolkit
 * Domain Path: /languages
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

define( 'TR_AI_TOOLKIT_VERSION', '1.3.0' );
define( 'TR_AI_TOOLKIT_FILE', __FILE__ );
define( 'TR_AI_TOOLKIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'TR_AI_TOOLKIT_URL', plugin_dir_url( __FILE__ ) );
define( 'TR_AI_TOOLKIT_BASENAME', plugin_basename( __FILE__ ) );
define( 'TR_AI_TOOLKIT_MIN_WP', '6.9' );
define( 'TR_AI_TOOLKIT_MIN_PHP', '8.1' );

require_once TR_AI_TOOLKIT_PATH . 'includes/class-activator.php';
require_once TR_AI_TOOLKIT_PATH . 'includes/class-deactivator.php';

register_activation_hook( __FILE__, array( 'TR_AI_Toolkit_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TR_AI_Toolkit_Deactivator', 'deactivate' ) );

/**
 * Boots the plugin after all plugins are loaded.
 */
function tr_ai_toolkit_run(): void {
	if ( ! tr_ai_toolkit_check_requirements() ) {
		return;
	}

	require_once TR_AI_TOOLKIT_PATH . 'includes/class-plugin.php';
	TR_AI_Toolkit_Plugin::get_instance()->run();
}
add_action( 'plugins_loaded', 'tr_ai_toolkit_run', 10 );

/**
 * Checks minimum WordPress and PHP version requirements.
 *
 * Shows an admin notice and returns false if requirements are not met.
 */
function tr_ai_toolkit_check_requirements(): bool {
	global $wp_version;

	$errors = array();

	if ( version_compare( PHP_VERSION, TR_AI_TOOLKIT_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: required PHP version, 2: current PHP version */
			__( 'TR WordPress AI Toolkit requires PHP %1$s or higher. Your server is running PHP %2$s.', 'tr-wordpress-ai-toolkit' ),
			TR_AI_TOOLKIT_MIN_PHP,
			PHP_VERSION
		);
	}

	if ( version_compare( $wp_version, TR_AI_TOOLKIT_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: required WP version, 2: current WP version */
			__( 'TR WordPress AI Toolkit requires WordPress %1$s or higher. Your site is running WordPress %2$s.', 'tr-wordpress-ai-toolkit' ),
			TR_AI_TOOLKIT_MIN_WP,
			$wp_version
		);
	}

	if ( ! function_exists( 'wp_register_ability' ) ) {
		$errors[] = __( 'TR WordPress AI Toolkit requires the WordPress Abilities API (included in WordPress 6.9+). Please upgrade WordPress.', 'tr-wordpress-ai-toolkit' );
	}

	if ( empty( $errors ) ) {
		return true;
	}

	add_action(
		'admin_notices',
		static function () use ( $errors ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'TR WordPress AI Toolkit', 'tr-wordpress-ai-toolkit' ) . '</strong></p>';
			foreach ( $errors as $error ) {
				echo '<p>' . esc_html( $error ) . '</p>';
			}
			echo '</div>';
		}
	);

	return false;
}
