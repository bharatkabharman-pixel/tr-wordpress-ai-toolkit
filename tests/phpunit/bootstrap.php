<?php
/**
 * PHPUnit bootstrap for TR WordPress AI Toolkit.
 *
 * Loads the WordPress test library and then the plugin.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php. Run tests/bin/install-wp-tests.sh first.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

function _manually_load_plugin(): void {
	define( 'TR_AI_TOOLKIT_VERSION', '1.0.0' );
	define( 'TR_AI_TOOLKIT_FILE', dirname( __DIR__, 2 ) . '/tr-wordpress-ai-toolkit.php' );
	define( 'TR_AI_TOOLKIT_PATH', dirname( __DIR__, 2 ) . '/' );
	define( 'TR_AI_TOOLKIT_URL', 'http://example.com/wp-content/plugins/tr-wordpress-ai-toolkit/' );
	define( 'TR_AI_TOOLKIT_BASENAME', 'tr-wordpress-ai-toolkit/tr-wordpress-ai-toolkit.php' );
	define( 'TR_AI_TOOLKIT_MIN_WP', '6.9' );
	define( 'TR_AI_TOOLKIT_MIN_PHP', '8.1' );

	require_once TR_AI_TOOLKIT_PATH . 'includes/class-activator.php';
	require_once TR_AI_TOOLKIT_PATH . 'includes/class-settings.php';
	require_once TR_AI_TOOLKIT_PATH . 'includes/class-permissions.php';
	require_once TR_AI_TOOLKIT_PATH . 'includes/class-logger.php';
	require_once TR_AI_TOOLKIT_PATH . 'includes/class-abilities-manager.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require_once "{$_tests_dir}/includes/bootstrap.php";
