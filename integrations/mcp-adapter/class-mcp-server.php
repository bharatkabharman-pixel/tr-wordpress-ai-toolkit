<?php
/**
 * MCP Server — implements Model Context Protocol directly via WordPress REST API.
 *
 * Transport:        Streamable HTTP (MCP 2025-03-26)
 * Authentication:   WordPress Application Passwords (HTTP Basic Auth)
 * Session storage:  WordPress transients, bound to authenticated user
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles MCP protocol at /wp-json/tr-ai-toolkit/mcp.
 *
 * Security model:
 * - Every request requires a valid WordPress authenticated user (Application Password).
 * - MCP sessions are bound to the user who created them.
 * - Session deletion requires ownership verification.
 * - Tool execution runs per-tool capability checks via current_user_can().
 * - No sensitive information is returned in protocol-level error messages.
 */
class TR_AI_Toolkit_MCP_Server {

	/** Supported MCP protocol versions, in preference order. */
	private const SUPPORTED_PROTOCOL_VERSIONS = array( '2025-03-26' );

	/** Transient key prefix for MCP sessions. */
	private const SESSION_PREFIX = 'tr_mcp_sess_';

	/** Session TTL in seconds (1 hour). */
	private const SESSION_TTL = HOUR_IN_SECONDS;

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

	// -------------------------------------------------------------------------
	// Public request handlers
	// -------------------------------------------------------------------------

	public function handle_request( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! is_user_logged_in() ) {
			return $this->mcp_error( null, -32001, 'Authentication required.', 401 );
		}

		if ( ! $this->settings->get( 'general.plugin_enabled', true ) ) {
			return $this->mcp_error( null, -32000, 'Service unavailable.', 503 );
		}

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return $this->mcp_error( null, -32700, 'Parse error.', 400 );
		}

		$method = isset( $body['method'] ) ? (string) $body['method'] : '';
		$id     = $body['id'] ?? null;
		$params = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();

		// All non-initialize requests may optionally carry a session ID.
		// We validate it when present so stale/forged sessions are rejected.
		if ( 'initialize' !== $method ) {
			$session_header = $request->get_header( 'mcp-session-id' );
			if ( $session_header && ! $this->validate_session( $session_header ) ) {
				return $this->mcp_error( $id, -32001, 'Invalid or expired session.', 401 );
			}
		}

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
				return $this->mcp_error( $id, -32601, 'Method not found.' );
		}
	}

	/**
	 * Handles session termination (DELETE /mcp).
	 *
	 * Only the user who created a session may delete it.
	 */
	public function handle_delete( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! is_user_logged_in() ) {
			return new \WP_REST_Response( null, 401 );
		}

		$session_id = $request->get_header( 'mcp-session-id' );
		if ( $session_id ) {
			$key      = self::SESSION_PREFIX . sanitize_key( $session_id );
			$session  = get_transient( $key );

			if ( false !== $session ) {
				$owner_id = (int) ( $session['user_id'] ?? 0 );

				// Only delete if the session belongs to the requesting user.
				if ( $owner_id === get_current_user_id() ) {
					delete_transient( $key );
				}
			}
		}

		return new \WP_REST_Response( null, 200 );
	}

	// -------------------------------------------------------------------------
	// MCP method handlers
	// -------------------------------------------------------------------------

	private function handle_initialize( mixed $id, array $params ): \WP_REST_Response {
		// Negotiate protocol version: use client's version if we support it,
		// otherwise fall back to our latest supported version.
		$client_version  = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '';
		$server_version  = in_array( $client_version, self::SUPPORTED_PROTOCOL_VERSIONS, true )
			? $client_version
			: self::SUPPORTED_PROTOCOL_VERSIONS[0];

		// Create session bound to the current user.
		$session_id = wp_generate_uuid4();
		set_transient(
			self::SESSION_PREFIX . $session_id,
			array(
				'user_id'    => get_current_user_id(),
				'created_at' => time(),
			),
			self::SESSION_TTL
		);

		$server_name = sanitize_text_field(
			$this->settings->get( 'mcp.server_name', get_bloginfo( 'name' ) . ' - TR AI Toolkit' )
		);

		$response = $this->mcp_success(
			$id,
			array(
				'protocolVersion' => $server_version,
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
		$name = isset( $params['name'] ) ? (string) $params['name'] : '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] )
			? $params['arguments']
			: array();

		if ( '' === $name ) {
			return $this->mcp_error( $id, -32602, 'Missing required parameter: name.' );
		}

		$result = $this->abilities_manager->execute_mcp_tool( $name, $args );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) ? (int) ( $error_data['status'] ?? 400 ) : 400;

			if ( 403 === $status ) {
				return $this->mcp_error( $id, -32001, 'Permission denied.', 403 );
			}

			if ( 404 === $status ) {
				return $this->mcp_error( $id, -32601, 'Tool not found.' );
			}

			// Generic server error — do not leak internal error details.
			return $this->mcp_error( $id, -32000, 'Tool execution failed.' );
		}

		return $this->mcp_success(
			$id,
			array(
				'content' => array(
					array( 'type' => 'text', 'text' => wp_json_encode( $result ) ),
				),
				'isError' => false,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Session helpers
	// -------------------------------------------------------------------------

	/**
	 * Validates a session ID: must exist as a transient and belong to the
	 * currently authenticated user.
	 */
	private function validate_session( string $session_id ): bool {
		$key     = self::SESSION_PREFIX . sanitize_key( $session_id );
		$session = get_transient( $key );

		if ( false === $session || ! is_array( $session ) ) {
			return false;
		}

		return ( (int) ( $session['user_id'] ?? 0 ) ) === get_current_user_id();
	}

	// -------------------------------------------------------------------------
	// Response builders
	// -------------------------------------------------------------------------

	private function mcp_success( mixed $id, mixed $result ): \WP_REST_Response {
		return new \WP_REST_Response(
			array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ),
			200
		);
	}

	private function mcp_error( mixed $id, int $code, string $message, int $http_status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => array( 'code' => $code, 'message' => $message ),
			),
			$http_status
		);
	}

	// -------------------------------------------------------------------------
	// Static helpers
	// -------------------------------------------------------------------------

	public static function get_endpoint_url(): string {
		return rest_url( 'tr-ai-toolkit/mcp' );
	}

	public static function get_stdio_command(): string {
		return 'wp mcp-adapter serve --server=tr-ai-toolkit --user=<your-admin-username>';
	}
}
