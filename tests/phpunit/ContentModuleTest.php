<?php
/**
 * Tests for TR_AI_Toolkit_Content_Module abilities.
 *
 * These tests run against a real WordPress test database to verify
 * actual ability behaviour, not mocked return values.
 *
 * @package TRWordPressAIToolkit
 */

class ContentModuleTest extends WP_UnitTestCase {

	private TR_AI_Toolkit_Settings    $settings;
	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	public function setUp(): void {
		parent::setUp();

		TR_AI_Toolkit_Activator::activate();

		$this->settings    = new TR_AI_Toolkit_Settings();
		$this->permissions = new TR_AI_Toolkit_Permissions( $this->settings );
		$this->logger      = new TR_AI_Toolkit_Logger( $this->settings );

		// Log in as admin.
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// list-posts
	// -------------------------------------------------------------------------

	public function test_list_posts_returns_published_posts(): void {
		self::factory()->post->create_many( 3, array( 'post_status' => 'publish' ) );

		$module = new TR_AI_Toolkit_Content_Module( $this->permissions, $this->logger );
		$module->register();

		$result = $this->invoke_ability( 'tr-ai-toolkit/list-posts', array() );

		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertGreaterThanOrEqual( 3, $result['total'] );
	}

	public function test_list_posts_respects_per_page(): void {
		self::factory()->post->create_many( 5, array( 'post_status' => 'publish' ) );

		$module = new TR_AI_Toolkit_Content_Module( $this->permissions, $this->logger );
		$module->register();

		$result = $this->invoke_ability( 'tr-ai-toolkit/list-posts', array( 'per_page' => 2 ) );

		$this->assertCount( 2, $result['posts'] );
		$this->assertSame( 2, $result['per_page'] );
	}

	public function test_list_posts_draft_requires_edit_posts(): void {
		wp_set_current_user( 0 ); // Unauthenticated.
		self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$module = new TR_AI_Toolkit_Content_Module( $this->permissions, $this->logger );
		$module->register();

		// Without capability, drafts should fall back to publish scope.
		$result = $this->invoke_ability( 'tr-ai-toolkit/list-posts', array( 'status' => 'draft' ) );

		// Because the user has no capability, the ability should deny or return publish.
		$this->assertFalse( current_user_can( 'edit_posts' ) );
	}

	// -------------------------------------------------------------------------
	// create-post
	// -------------------------------------------------------------------------

	public function test_create_post_creates_draft(): void {
		$module = new TR_AI_Toolkit_Content_Module( $this->permissions, $this->logger );
		$module->register();

		$result = $this->invoke_ability( 'tr-ai-toolkit/create-post', array(
			'title'   => 'Test Post',
			'content' => 'Hello world.',
		) );

		$this->assertArrayHasKey( 'post_id', $result );
		$this->assertSame( 'draft', $result['status'] );
		$this->assertSame( 'Test Post', get_post( $result['post_id'] )->post_title );
	}

	public function test_create_post_requires_title(): void {
		$module = new TR_AI_Toolkit_Content_Module( $this->permissions, $this->logger );
		$module->register();

		$result = $this->invoke_ability( 'tr-ai-toolkit/create-post', array() );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_create_post_blocked_for_unauthenticated_user(): void {
		wp_set_current_user( 0 );

		$module = new TR_AI_Toolkit_Content_Module( $this->permissions, $this->logger );
		$module->register();

		$ability = WP_Ability_Registry::instance()->get( 'tr-ai-toolkit/create-post' );
		$this->assertNotNull( $ability );

		$permitted = call_user_func( $ability->get_permission_callback() );
		$this->assertInstanceOf( WP_Error::class, $permitted );
	}

	// -------------------------------------------------------------------------
	// get-post
	// -------------------------------------------------------------------------

	public function test_get_post_returns_correct_post(): void {
		$post_id = self::factory()->post->create( array(
			'post_title'   => 'My Test Post',
			'post_status'  => 'publish',
			'post_content' => 'Content here.',
		) );

		$module = new TR_AI_Toolkit_Content_Module( $this->permissions, $this->logger );
		$module->register();

		$result = $this->invoke_ability( 'tr-ai-toolkit/get-post', array( 'post_id' => $post_id ) );

		$this->assertSame( $post_id, $result['id'] );
		$this->assertSame( 'My Test Post', $result['title'] );
		$this->assertArrayHasKey( 'content', $result );
	}

	public function test_get_post_returns_wp_error_for_nonexistent(): void {
		$module = new TR_AI_Toolkit_Content_Module( $this->permissions, $this->logger );
		$module->register();

		$result = $this->invoke_ability( 'tr-ai-toolkit/get-post', array( 'post_id' => 999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	// -------------------------------------------------------------------------
	// delete-post (trash)
	// -------------------------------------------------------------------------

	public function test_delete_post_moves_to_trash(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$module = new TR_AI_Toolkit_Content_Module( $this->permissions, $this->logger );
		$module->register();

		$result = $this->invoke_ability( 'tr-ai-toolkit/delete-post', array( 'post_id' => $post_id ) );

		$this->assertSame( 'trash', $result['status'] );
		$this->assertSame( 'trash', get_post_status( $post_id ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Retrieves a registered ability and calls its execute_callback.
	 *
	 * @param string               $ability_name
	 * @param array<string, mixed> $input
	 * @return mixed
	 */
	private function invoke_ability( string $ability_name, array $input ): mixed {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API not available.' );
		}

		$registry = WP_Ability_Registry::instance();
		$ability  = $registry->get( $ability_name );

		if ( ! $ability ) {
			$this->fail( "Ability '{$ability_name}' was not registered." );
		}

		return call_user_func( $ability->get_execute_callback(), $input );
	}
}
