<?php
/**
 * Tests for TR_AI_Toolkit_Permissions.
 *
 * @package TRWordPressAIToolkit
 */

class PermissionsTest extends WP_UnitTestCase {

	private TR_AI_Toolkit_Settings    $settings;
	private TR_AI_Toolkit_Permissions $permissions;

	public function setUp(): void {
		parent::setUp();
		$this->settings    = new TR_AI_Toolkit_Settings();
		$this->permissions = new TR_AI_Toolkit_Permissions( $this->settings );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_can_read_requires_logged_in_user(): void {
		wp_set_current_user( 0 );
		$this->assertFalse( $this->permissions->can_read() );
	}

	public function test_can_read_returns_true_for_subscriber(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->permissions->can_read() );
	}

	public function test_can_write_returns_false_for_subscriber(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		$this->assertFalse( $this->permissions->can_write() );
	}

	public function test_can_write_returns_true_for_editor(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->permissions->can_write() );
	}

	public function test_can_admin_returns_false_for_editor(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		$this->assertFalse( $this->permissions->can_admin() );
	}

	public function test_can_admin_returns_true_for_admin(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->permissions->can_admin() );
	}

	public function test_require_returns_wp_error_on_failure(): void {
		wp_set_current_user( 0 );
		$result = $this->permissions->require( 'can_read' );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_require_returns_true_on_success(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$result = $this->permissions->require( 'can_admin' );
		$this->assertTrue( $result );
	}
}
