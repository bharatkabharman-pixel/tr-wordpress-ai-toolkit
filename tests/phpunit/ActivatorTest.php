<?php
/**
 * Tests for TR_AI_Toolkit_Activator.
 *
 * @package TRWordPressAIToolkit
 */

class ActivatorTest extends WP_UnitTestCase {

	public function test_activate_creates_log_table(): void {
		global $wpdb;

		TR_AI_Toolkit_Activator::activate();

		$table  = $wpdb->prefix . 'tr_ai_toolkit_logs';
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->assertSame( $table, $result, 'Log table should exist after activation.' );
	}

	public function test_activate_stores_version(): void {
		TR_AI_Toolkit_Activator::activate();

		$this->assertSame( TR_AI_TOOLKIT_VERSION, get_option( 'tr_ai_toolkit_version' ) );
	}

	public function test_activate_stores_default_settings(): void {
		delete_option( 'tr_ai_toolkit_settings' );
		TR_AI_Toolkit_Activator::activate();

		$settings = get_option( 'tr_ai_toolkit_settings' );
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'general', $settings );
	}

	public function test_activate_does_not_overwrite_existing_settings(): void {
		update_option( 'tr_ai_toolkit_settings', array( 'custom' => 'value' ) );
		TR_AI_Toolkit_Activator::activate();

		$settings = get_option( 'tr_ai_toolkit_settings' );
		$this->assertSame( array( 'custom' => 'value' ), $settings );
	}

	public function test_default_settings_structure(): void {
		$defaults = TR_AI_Toolkit_Activator::default_settings();

		$this->assertArrayHasKey( 'general', $defaults );
		$this->assertArrayHasKey( 'permissions', $defaults );
		$this->assertArrayHasKey( 'mcp', $defaults );
		$this->assertArrayHasKey( 'logging', $defaults );
		$this->assertTrue( $defaults['general']['plugin_enabled'] );
		$this->assertSame( 'read', $defaults['permissions']['read_capability'] );
		$this->assertSame( 'edit_posts', $defaults['permissions']['write_capability'] );
		$this->assertSame( 'manage_options', $defaults['permissions']['admin_capability'] );
	}
}
