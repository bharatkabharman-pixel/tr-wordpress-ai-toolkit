<?php
/**
 * MCP Server — implements Model Context Protocol directly via WordPress REST API.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles MCP protocol at /wp-json/tr-ai-toolkit/mcp independently of the MCP Adapter plugin.
 */
class TR_AI_Toolkit_MCP_Server {

	private TR_AI_Toolkit_Settings          $settings;
	private TR_AI_Toolkit_Abilities_Manager $abilities_manager;

	public function __construct(
		TR_AI_Toolkit_Settings $settings,
		TR_AI_Toolkit_Abilities_Manager $abilities_manager
	) {
		$this->settings          = $settings;
		$this->abilities_manager = $abilities_manager;
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route(): void {
		register_rest_route(
			'tr-ai-toolkit',
			'/mcp',
			array(
				array(
					'methods'             => array( 'GET', 'POST' ),
					'callback'            => array( $this, 'handle_request' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'handle_delete' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public function handle_request( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! is_user_logged_in() ) {
			return $this->mcp_error( null, -32001, 'Unauthorized', 401 );
		}

		if ( ! $this->settings->get( 'general.plugin_enabled', true ) ) {
			return $this->mcp_error( null, -32000, 'TR AI Toolkit is disabled.', 503 );
		}

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return $this->mcp_error( null, -32700, 'Parse error', 400 );
		}

		$method = $body['method'] ?? '';
		$id     = $body['id'] ?? null;
		$params = $body['params'] ?? array();

		switch ( $method ) {
			case 'initialize':
				return $this->handle_initialize( $id, $params );
			case 'notifications/initialized':
				return $this->mcp_success( $id, null );
			case 'tools/list':
				return $this->handle_tools_list( $id );
			case 'tools/call':
				return $this->handle_tools_call( $id, $params );
			case 'ping':
				return $this->mcp_success( $id, new \stdClass() );
			default:
				return $this->mcp_error( $id, -32601, 'Method not found: ' . esc_html( $method ) );
		}
	}

	public function handle_delete( \WP_REST_Request $request ): \WP_REST_Response {
		$session_id = $request->get_header( 'mcp-session-id' );
		if ( $session_id ) {
			delete_transient( 'tr_mcp_sess_' . sanitize_key( $session_id ) );
		}
		return new \WP_REST_Response( null, 200 );
	}

	private function handle_initialize( mixed $id, array $params ): \WP_REST_Response {
		$session_id = wp_generate_uuid4();
		set_transient( 'tr_mcp_sess_' . $session_id, 1, HOUR_IN_SECONDS );

		$server_name = sanitize_text_field(
			$this->settings->get( 'mcp.server_name', get_bloginfo( 'name' ) . ' - TR AI Toolkit' )
		);

		$response = $this->mcp_success(
			$id,
			array(
				'protocolVersion' => '2025-03-26',
				'capabilities'    => array( 'tools' => new \stdClass() ),
				'serverInfo'      => array(
					'name'    => $server_name,
					'version' => TR_AI_TOOLKIT_VERSION,
				),
			)
		);

		$response->header( 'Mcp-Session-Id', $session_id );
		return $response;
	}

	private function handle_tools_list( mixed $id ): \WP_REST_Response {
		return $this->mcp_success(
			$id,
			array( 'tools' => $this->abilities_manager->get_mcp_tools() )
		);
	}

	private function handle_tools_call( mixed $id, array $params ): \WP_REST_Response {
		$name = $params['name'] ?? '';
		$args = $params['arguments'] ?? array();

		if ( '' === $name ) {
			return $this->mcp_error( $id, -32602, 'Missing tool name.' );
		}

		$result = $this->abilities_manager->execute_mcp_tool( $name, $args );

		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 400 );
			if ( 403 === $status ) {
				return $this->mcp_error( $id, -32001, $result->get_error_message(), 403 );
			}
			return $this->mcp_error( $id, -32000, $result->get_error_message() );
		}

		return $this->mcp_success(
			$id,
			array(
				'content' => array(
					array( 'type' => 'text', 'text' => wp_json_encode( $result ) ),
				),
			)
		);
	}

	private function mcp_success( mixed $id, mixed $result ): \WP_REST_Response {
		return new \WP_REST_Response(
			array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ),
			200
		);
	}

	private function mcp_error( mixed $id, int $code, string $message, int $http_status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $message ) ),
			$http_status
		);
	}

	public static function get_endpoint_url(): string {
		return rest_url( 'tr-ai-toolkit/mcp' );
	}

	public static function get_stdio_command(): string {
		return 'wp mcp-adapter serve --server=tr-ai-toolkit --user=<your-admin-username>';
	}
}
