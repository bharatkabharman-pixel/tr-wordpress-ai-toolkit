<?php
/**
 * Plugin deactivator.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin deactivation.
 */
class TR_AI_Toolkit_Deactivator {

	/**
	 * Flushes rewrite rules on deactivation.
	 *
	 * Data is intentionally left intact so it survives reactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
