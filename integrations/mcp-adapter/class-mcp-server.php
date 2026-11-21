<?php
/**
 * MCP Server registration.
 *
 * Uses the official WordPress MCP Adapter plugin (mcp-adapter) hook to register
 * a dedicated MCP server that exposes the TR AI Toolkit abilities.
 *
 * The default MCP Adapter server also exposes any ability with meta.mcp.public = true,
 * so abilities are always reachable on the default server without this class.
 * This class creates an *additional* named server for cleaner AI client configuration.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers a named MCP server via the mcp_adapter_init hook.
 */
class TR_AI_Toolkit_MCP_Server {

	private TR_AI_Toolkit_Settings $settings;

	public function __construct( TR_AI_Toolkit_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Attaches the mcp_adapter_init hook when the MCP Adapter plugin is active.
	 */
	public function init(): void {
		add_action( 'mcp_adapter_init', array( $this, 'register_server' ) );
	}

	/**
	 * Creates the TR AI Toolkit MCP server.
	 *
	 * @param object $adapter The MCP Adapter instance passed by mcp_adapter_init.
	 */
	public function register_server( object $adapter ): void {
		if ( ! method_exists( $adapter, 'create_server' ) ) {
			return;
		}

		if ( ! $this->settings->get( 'general.plugin_enabled', true ) ) {
			return;
		}

		$server_name = sanitize_text_field(
			$this->settings->get( 'mcp.server_name', get_bloginfo( 'name' ) . ' - TR AI Toolkit' )
		);

		/**
		 * The mcp_adapter_init hook fires after all abilities are registered.
		 * We pass an empty abilities array here because abilities are auto-discovered
		 * via the meta.mcp.public flag on each registered ability. The dedicated
		 * server provides a clean endpoint URL for AI client configuration.
		 *
		 * Transport: WP\MCP\Transport\HttpTransport provides the REST API endpoint.
		 * Endpoint format: /wp-json/tr-ai-toolkit/mcp
		 *
		 * See: https://github.com/WordPress/mcp-adapter/blob/trunk/docs/getting-started/README.md
		 */
		try {
			$adapter->create_server(
				'tr-ai-toolkit',          // Server ID.
				'tr-ai-toolkit',          // REST namespace.
				'mcp',                    // REST route slug → /wp-json/tr-ai-toolkit/mcp
				$server_name,             // Human-readable name.
				__( 'TR WordPress AI Toolkit — manage content, media, SEO and site health using AI.', 'tr-wordpress-ai-toolkit' ),
				TR_AI_TOOLKIT_VERSION,
				array( 'WP\\MCP\\Transport\\HttpTransport' ),
				null,                     // Error handler: null = default WP error log.
				null                      // Observability: null = no-op.
			);
		} catch ( \Throwable $e ) {
			// The dedicated server is optional — the default server always works.
			error_log( 'TR AI Toolkit: Could not register dedicated MCP server: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Returns the HTTP endpoint URL for the dedicated MCP server.
	 *
	 * Falls back to the default MCP Adapter endpoint if our server
	 * registration failed or the adapter version does not support it.
	 *
	 * @return string
	 */
	public static function get_endpoint_url(): string {
		$dedicated = rest_url( 'tr-ai-toolkit/mcp' );
		$default   = rest_url( 'mcp/mcp' );

		return $dedicated ?: $default;
	}

	/**
	 * Returns the STDIO WP-CLI command for use in Claude Desktop configuration.
	 *
	 * @return string
	 */
	public static function get_stdio_command(): string {
		return 'wp mcp-adapter serve --server=tr-ai-toolkit --user=<your-admin-username>';
	}
}
