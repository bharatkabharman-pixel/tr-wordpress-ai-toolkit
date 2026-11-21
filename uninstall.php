<?php
/**
 * Runs on plugin deletion (not deactivation).
 *
 * Removes all plugin data from the database.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Remove options.
delete_option( 'tr_ai_toolkit_settings' );
delete_option( 'tr_ai_toolkit_version' );
delete_option( 'tr_ai_toolkit_onboarding_complete' );

// Remove logs table.
$table_name = $wpdb->prefix . 'tr_ai_toolkit_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Multisite: clean up all subsites.
if ( is_multisite() ) {
	$blog_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		delete_option( 'tr_ai_toolkit_settings' );
		delete_option( 'tr_ai_toolkit_version' );
		delete_option( 'tr_ai_toolkit_onboarding_complete' );
		$site_table = $wpdb->prefix . 'tr_ai_toolkit_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DROP TABLE IF EXISTS `{$site_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		restore_current_blog();
	}
}
