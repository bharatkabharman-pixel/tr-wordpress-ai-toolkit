<?php
/**
 * Tests for TR_AI_Toolkit_Settings.
 *
 * @package TRWordPressAIToolkit
 */

use PHPUnit\Framework\TestCase;

class SettingsTest extends WP_UnitTestCase {

	private TR_AI_Toolkit_Settings $settings;

	public function setUp(): void {
		parent::setUp();
		delete_option( 'tr_ai_toolkit_settings' );
		$this->settings = new TR_AI_Toolkit_Settings();
	}

	public function test_all_returns_defaults_when_no_option(): void {
		$all = $this->settings->all();

		$this->assertIsArray( $all );
		$this->assertArrayHasKey( 'general', $all );
		$this->assertArrayHasKey( 'permissions', $all );
		$this->assertArrayHasKey( 'mcp', $all );
		$this->assertArrayHasKey( 'logging', $all );
	}

	public function test_get_dot_notation(): void {
		$this->assertSame( 'read', $this->settings->get( 'permissions.read_capability' ) );
		$this->assertSame( 'edit_posts', $this->settings->get( 'permissions.write_capability' ) );
	}

	public function test_get_returns_default_for_missing_key(): void {
		$this->assertSame( 'fallback', $this->settings->get( 'nonexistent.key', 'fallback' ) );
	}

	public function test_update_group_persists(): void {
		$this->settings->update_group( 'general', array( 'plugin_enabled' => false ) );

		$fresh    = new TR_AI_Toolkit_Settings();
		$enabled  = $fresh->get( 'general.plugin_enabled' );

		$this->assertFalse( $enabled );
	}

	public function test_update_group_does_not_erase_sibling_keys(): void {
		$this->settings->update_group( 'permissions', array( 'read_capability' => 'edit_posts' ) );

		$fresh = new TR_AI_Toolkit_Settings();
		$this->assertSame( 'edit_posts', $fresh->get( 'permissions.read_capability' ) );
		$this->assertSame( 'edit_posts', $fresh->get( 'permissions.write_capability' ) );
	}
}
