<?php
/**
 * Tests for TR_AI_Toolkit_MCP_Server protocol handling.
 *
 * Tests verify protocol-level behaviour: initialization, session ownership,
 * ping, tools/list, tools/call with unknown tool, and session deletion.
 *
 * @package TRWordPressAIToolkit
 */

class MCPServerTest extends WP_UnitTestCase {

	private TR_AI_Toolkit_Settings          $settings;
	private TR_AI_Toolkit_Abilities_Manager $abilities_manager;
	private TR_AI_Toolkit_MCP_Server        $server;
	private int                             $admin_id;

	public function setUp(): void {
		parent::setUp();

		TR_AI_Toolkit_Activator::activate();

		$this->settings          = new TR_AI_Toolkit_Settings();
		$logger                  = new TR_AI_Toolkit_Logger( $this->settings );
		$permissions             = new TR_AI_Toolkit_Permissions( $this->settings );
		$this->abilities_manager = new TR_AI_Toolkit_Abilities_Manager( $this->settings, $permissions, $logger );
		$this->server            = new TR_AI_Toolkit_MCP_Server( $this->settings, $this->abilities_manager );

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function make_request( string $method_name, array $params = array(), array $headers = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/tr-ai-toolkit/mcp' );
		$request->set_header( 'Content-Type', 'application/json' );
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}
		$request->set_body( wp_json_encode( array(
			'jsonrpc' => '2.0',
			'id'      => 1,
			'method'  => $method_name,
			'params'  => $params,
		) ) );
		return $this->server->handle_request( $request );
	}

	private function initialize(): array {
		$response = $this->make_request( 'initialize', array(
			'protocolVersion' => '2025-03-26',
			'capabilities'    => new \stdClass(),
			'clientInfo'      => array( 'name' => 'test', 'version' => '1.0' ),
		) );
		return rest_get_server()->response_to_data( $response, false );
	}

	// -------------------------------------------------------------------------
	// initialize
	// -------------------------------------------------------------------------

	public function test_initialize_returns_200(): void {
		$response = $this->make_request( 'initialize', array(
			'protocolVersion' => '2025-03-26',
			'capabilities'    => new \stdClass(),
			'clientInfo'      => array( 'name' => 'test', 'version' => '1.0' ),
		) );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_initialize_returns_server_info(): void {
		$data = $this->initialize();
		$this->assertArrayHasKey( 'result', $data );
		$this->assertArrayHasKey( 'serverInfo', $data['result'] );
		$this->assertArrayHasKey( 'protocolVersion', $data['result'] );
	}

	public function test_initialize_negotiates_protocol_version(): void {
		$data = $this->initialize();
		$this->assertSame( '2025-03-26', $data['result']['protocolVersion'] );
	}

	// -------------------------------------------------------------------------
	// ping
	// -------------------------------------------------------------------------

	public function test_ping_returns_empty_object(): void {
		$response = $this->make_request( 'ping' );
		$data     = rest_get_server()->response_to_data( $response, false );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'result', $data );
	}

	// -------------------------------------------------------------------------
	// tools/list
	// -------------------------------------------------------------------------

	public function test_tools_list_returns_array(): void {
		$response = $this->make_request( 'tools/list' );
		$data     = rest_get_server()->response_to_data( $response, false );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'result', $data );
		$this->assertArrayHasKey( 'tools', $data['result'] );
		$this->assertIsArray( $data['result']['tools'] );
	}

	// -------------------------------------------------------------------------
	// tools/call — unknown tool
	// -------------------------------------------------------------------------

	public function test_tools_call_unknown_tool_returns_32601(): void {
		$response = $this->make_request( 'tools/call', array(
			'name'      => 'tr-ai-toolkit-nonexistent-tool-xyz',
			'arguments' => array(),
		) );
		$data = rest_get_server()->response_to_data( $response, false );

		$this->assertArrayHasKey( 'error', $data );
		$this->assertSame( -32601, $data['error']['code'] );
	}

	// -------------------------------------------------------------------------
	// Authentication
	// -------------------------------------------------------------------------

	public function test_unauthenticated_request_returns_401(): void {
		wp_set_current_user( 0 );

		$response = $this->make_request( 'ping' );
		$this->assertSame( 401, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// Session ownership
	// -------------------------------------------------------------------------

	public function test_delete_session_owned_by_other_user_is_blocked(): void {
		// User A creates a session.
		$session_id = wp_generate_uuid4();
		$key        = 'tr_mcp_sess_' . sanitize_key( $session_id );
		set_transient( $key, array( 'user_id' => $this->admin_id, 'created_at' => time() ), HOUR_IN_SECONDS );

		// User B tries to delete it.
		$user_b = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_b );

		$request = new \WP_REST_Request( 'DELETE', '/tr-ai-toolkit/mcp' );
		$request->set_header( 'mcp-session-id', $session_id );

		$response = $this->server->handle_delete( $request );
		$this->assertSame( 403, $response->get_status() );

		// Session should still exist.
		$this->assertNotFalse( get_transient( $key ) );

		delete_transient( $key );
	}

	// -------------------------------------------------------------------------
	// Plugin disabled
	// -------------------------------------------------------------------------

	public function test_disabled_plugin_returns_503(): void {
		$this->settings->update_group( 'general', array( 'plugin_enabled' => false ) );

		$response = $this->make_request( 'ping' );
		$this->assertSame( 503, $response->get_status() );

		$this->settings->update_group( 'general', array( 'plugin_enabled' => true ) );
	}
}
