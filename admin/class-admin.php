<?php
/**
 * Admin panel controller.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers WordPress admin menus, enqueues assets and handles AJAX/form submissions.
 */
class TR_AI_Toolkit_Admin {

	private TR_AI_Toolkit_Settings          $settings;
	private TR_AI_Toolkit_Abilities_Manager $abilities_manager;
	private TR_AI_Toolkit_Logger            $logger;

	public function __construct(
		TR_AI_Toolkit_Settings $settings,
		TR_AI_Toolkit_Abilities_Manager $abilities_manager,
		TR_AI_Toolkit_Logger $logger
	) {
		$this->settings          = $settings;
		$this->abilities_manager = $abilities_manager;
		$this->logger            = $logger;
	}

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_tr_ai_toolkit_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_notices', array( $this, 'show_mcp_adapter_notice' ) );
		add_action( 'wp_ajax_tr_ai_toolkit_generate_app_password', array( $this, 'ajax_generate_app_password' ) );
		add_action( 'wp_ajax_tr_ai_toolkit_revoke_app_password', array( $this, 'ajax_revoke_app_password' ) );
		add_action( 'wp_ajax_tr_ai_toolkit_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_tr_ai_toolkit_clear_logs', array( $this, 'ajax_clear_logs' ) );
	}

	/**
	 * Registers the top-level admin menu and sub-pages.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'TR AI Toolkit', 'tr-wordpress-ai-toolkit' ),
			__( 'TR AI Toolkit', 'tr-wordpress-ai-toolkit' ),
			'read',
			'tr-ai-toolkit',
			array( $this, 'render_dashboard' ),
			'dashicons-superhero',
			80
		);

		add_submenu_page(
			'tr-ai-toolkit',
			__( 'Dashboard', 'tr-wordpress-ai-toolkit' ),
			__( 'Dashboard', 'tr-wordpress-ai-toolkit' ),
			'read',
			'tr-ai-toolkit',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'tr-ai-toolkit',
			__( 'Settings', 'tr-wordpress-ai-toolkit' ),
			__( 'Settings', 'tr-wordpress-ai-toolkit' ),
			'manage_options',
			'tr-ai-toolkit-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'tr-ai-toolkit',
			__( 'Connection Guide', 'tr-wordpress-ai-toolkit' ),
			__( 'Connection Guide', 'tr-wordpress-ai-toolkit' ),
			'manage_options',
			'tr-ai-toolkit-connect',
			array( $this, 'render_connect' )
		);

		add_submenu_page(
			'tr-ai-toolkit',
			__( 'Prompt Library', 'tr-wordpress-ai-toolkit' ),
			__( '✦ Prompt Library', 'tr-wordpress-ai-toolkit' ),
			'read',
			'tr-ai-toolkit-prompts',
			array( $this, 'render_prompts' )
		);

		add_submenu_page(
			'tr-ai-toolkit',
			__( 'Activity Log', 'tr-wordpress-ai-toolkit' ),
			__( 'Activity Log', 'tr-wordpress-ai-toolkit' ),
			'manage_options',
			'tr-ai-toolkit-logs',
			array( $this, 'render_logs' )
		);
	}

	/**
	 * Enqueues admin-only CSS and JS on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		$plugin_pages = array(
			'toplevel_page_tr-ai-toolkit',
			'tr-ai-toolkit_page_tr-ai-toolkit-settings',
			'tr-ai-toolkit_page_tr-ai-toolkit-connect',
			'tr-ai-toolkit_page_tr-ai-toolkit-prompts',
			'tr-ai-toolkit_page_tr-ai-toolkit-logs',
		);

		if ( ! in_array( $hook, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'tr-ai-toolkit-admin',
			TR_AI_TOOLKIT_URL . 'admin/css/admin.css',
			array(),
			TR_AI_TOOLKIT_VERSION
		);

		wp_enqueue_script(
			'tr-ai-toolkit-admin',
			TR_AI_TOOLKIT_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			TR_AI_TOOLKIT_VERSION,
			true
		);

		wp_localize_script(
			'tr-ai-toolkit-admin',
			'trAIToolkit',
			array(
				'nonce'    => wp_create_nonce( 'tr_ai_toolkit_admin' ),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'strings'  => array(
					'copied'         => __( 'Copied!', 'tr-wordpress-ai-toolkit' ),
					'copyFail'       => __( 'Copy failed. Please copy manually.', 'tr-wordpress-ai-toolkit' ),
					'ajaxError'      => __( 'Request failed. Please try again.', 'tr-wordpress-ai-toolkit' ),
					'regenerate'     => __( 'Regenerate (creates new credentials)', 'tr-wordpress-ai-toolkit' ),
					'revokeAccess'   => __( 'Revoke Access', 'tr-wordpress-ai-toolkit' ),
					'revokeConfirm'  => __( 'Are you sure? This will disconnect all AI assistants using the Quick Connect credentials. Any manually added credentials will still work.', 'tr-wordpress-ai-toolkit' ),
					'revoking'       => __( 'Revoking…', 'tr-wordpress-ai-toolkit' ),
					'revokeReload'       => __( 'Reloading page…', 'tr-wordpress-ai-toolkit' ),
				'testingConnection'  => __( 'Testing…', 'tr-wordpress-ai-toolkit' ),
				'testConnection'     => __( 'Test MCP Connection', 'tr-wordpress-ai-toolkit' ),
				'clearLogs'          => __( 'Clear All Logs', 'tr-wordpress-ai-toolkit' ),
				'clearLogsConfirm'   => __( 'Delete all log entries? This cannot be undone.', 'tr-wordpress-ai-toolkit' ),
				'clearing'           => __( 'Clearing…', 'tr-wordpress-ai-toolkit' ),
				'noLogsLeft'         => __( 'All log entries have been cleared.', 'tr-wordpress-ai-toolkit' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	public function render_dashboard(): void {
		$this->check_capability( 'read' );
		$data = array(
			'settings'           => $this->settings,
			'abilities_manager'  => $this->abilities_manager,
			'logger'             => $this->logger,
			'mcp_adapter_active' => $this->is_mcp_adapter_active(),
			'abilities_api_active' => function_exists( 'wp_register_ability' ),
			'recent_logs'        => $this->logger->get_recent( 10 ),
		);
		$this->render_view( 'dashboard', $data );
	}

	public function render_settings(): void {
		$this->check_capability( 'manage_options' );
		$data = array(
			'settings'          => $this->settings,
			'abilities_manager' => $this->abilities_manager,
			'saved'             => isset( $_GET['settings-saved'] ) && '1' === $_GET['settings-saved'], // phpcs:ignore WordPress.Security.NonceVerification
		);
		$this->render_view( 'settings', $data );
	}

	public function render_connect(): void {
		$this->check_capability( 'manage_options' );
		$user_id      = get_current_user_id();
		$saved_uuid   = get_user_meta( $user_id, 'tr_ai_toolkit_app_password_uuid', true );
		$is_connected = false;

		if ( $saved_uuid && class_exists( 'WP_Application_Passwords' ) ) {
			$stored = WP_Application_Passwords::get_user_application_password( $user_id, $saved_uuid );
			$is_connected = ! empty( $stored );
			if ( ! $is_connected ) {
				delete_user_meta( $user_id, 'tr_ai_toolkit_app_password_uuid' );
				delete_user_meta( $user_id, 'tr_ai_toolkit_app_password_name' );
			}
		}

		$data = array(
			'mcp_endpoint'         => rest_url( 'tr-ai-toolkit/mcp' ),
			'default_endpoint'     => rest_url( 'mcp/mcp' ),
			'stdio_command'        => TR_AI_Toolkit_MCP_Server::get_stdio_command(),
			'mcp_adapter_active'   => $this->is_mcp_adapter_active(),
			'abilities_api_active' => function_exists( 'wp_register_ability' ),
			'is_connected'         => $is_connected,
			'connected_username'   => $is_connected ? get_userdata( $user_id )->user_login : '',
		);
		$this->render_view( 'connect', $data );
	}

	public function render_prompts(): void {
		$this->check_capability( 'read' );
		$this->render_view( 'prompts', array() );
	}

	public function render_logs(): void {
		$this->check_capability( 'manage_options' );
		$data = array(
			'logs' => $this->logger->get_recent( 100 ),
		);
		$this->render_view( 'logs', $data );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * Creates a WordPress Application Password for the current user and returns
	 * the ready-to-use Claude.ai connector configuration.
	 */
	public function ajax_generate_app_password(): void {
		if ( ! check_ajax_referer( 'tr_ai_toolkit_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Could not load user data.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( array( 'message' => __( 'Application Passwords require WordPress 5.6 or higher.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}

		$result = WP_Application_Passwords::create_new_application_password(
			$user_id,
			array( 'name' => 'Claude AI - TR AI Toolkit' )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return;
		}

		list( $plain_password, $item ) = $result;

		update_user_meta( $user_id, 'tr_ai_toolkit_app_password_uuid', $item['uuid'] );
		update_user_meta( $user_id, 'tr_ai_toolkit_app_password_name', $item['name'] );

		$username   = $user->user_login;
		$mcp_url    = rest_url( 'tr-ai-toolkit/mcp' );
		$auth_value = 'Basic ' . base64_encode( $username . ':' . $plain_password );

		$claude_desktop_config = json_encode(
			array(
				'mcpServers' => array(
					'tr-ai-toolkit' => array(
						'type'    => 'http',
						'url'     => $mcp_url,
						'headers' => array(
							'Authorization' => $auth_value,
						),
					),
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		wp_send_json_success(
			array(
				'username'              => $username,
				'app_password'          => $plain_password,
				'mcp_url'               => $mcp_url,
				'auth_header_name'      => 'Authorization',
				'auth_header_value'     => $auth_value,
				'claude_desktop_config' => $claude_desktop_config,
			)
		);
	}

	/**
	 * Revokes the Application Password created by Quick Connect.
	 */
	public function ajax_revoke_app_password(): void {
		if ( ! check_ajax_referer( 'tr_ai_toolkit_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}

		$user_id    = get_current_user_id();
		$saved_uuid = get_user_meta( $user_id, 'tr_ai_toolkit_app_password_uuid', true );

		if ( ! $saved_uuid ) {
			wp_send_json_error( array( 'message' => __( 'No Quick Connect password found to revoke.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}

		$result = WP_Application_Passwords::delete_application_password( $user_id, $saved_uuid );

		delete_user_meta( $user_id, 'tr_ai_toolkit_app_password_uuid' );
		delete_user_meta( $user_id, 'tr_ai_toolkit_app_password_name' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return;
		}

		wp_send_json_success( array( 'message' => __( 'Access revoked. The AI assistant can no longer connect to this site.', 'tr-wordpress-ai-toolkit' ) ) );
	}

	/**
	 * Runs a local MCP ping via the internal REST dispatcher and reports success/failure.
	 */
	public function ajax_test_connection(): void {
		if ( ! check_ajax_referer( 'tr_ai_toolkit_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}

		$rest_request = new \WP_REST_Request( 'POST', '/tr-ai-toolkit/mcp' );
		$rest_request->set_header( 'Content-Type', 'application/json' );
		$rest_request->set_body( wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping' ) ) );

		$response = rest_do_request( $rest_request );
		$status   = $response->get_status();
		$data     = rest_get_server()->response_to_data( $response, false );

		if ( 200 === $status && isset( $data['result'] ) ) {
			wp_send_json_success( array(
				'message' => __( 'MCP server is reachable and responding correctly.', 'tr-wordpress-ai-toolkit' ),
			) );
			return;
		}

		$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unexpected response.', 'tr-wordpress-ai-toolkit' );
		wp_send_json_error( array(
			'message'     => sprintf(
				/* translators: 1: HTTP status code, 2: error message */
				__( 'Connection test failed (HTTP %1$s): %2$s', 'tr-wordpress-ai-toolkit' ),
				$status,
				$error_msg
			),
		) );
	}

	/**
	 * Clears all activity log entries.
	 */
	public function ajax_clear_logs(): void {
		if ( ! check_ajax_referer( 'tr_ai_toolkit_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'tr-wordpress-ai-toolkit' ) ) );
			return;
		}

		$deleted = $this->logger->clear_all();
		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: number of log entries deleted */
				_n(
					'Cleared %d log entry.',
					'Cleared %d log entries.',
					$deleted,
					'tr-wordpress-ai-toolkit'
				),
				$deleted
			),
			'deleted' => $deleted,
		) );
	}

	// -------------------------------------------------------------------------
	// Settings form handler
	// -------------------------------------------------------------------------

	public function handle_save_settings(): void {
		check_admin_referer( 'tr_ai_toolkit_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'tr-wordpress-ai-toolkit' ) );
		}

		$general = array(
			'plugin_enabled' => isset( $_POST['plugin_enabled'] ),
			'log_level'      => sanitize_key( $_POST['log_level'] ?? 'info' ),
		);

		$allowed_caps = array( 'read', 'edit_posts', 'publish_posts', 'manage_options' );

		$permissions = array(
			'read_capability'  => in_array( $_POST['read_capability'] ?? 'read', $allowed_caps, true ) ? sanitize_key( $_POST['read_capability'] ) : 'read',
			'write_capability' => in_array( $_POST['write_capability'] ?? 'edit_posts', $allowed_caps, true ) ? sanitize_key( $_POST['write_capability'] ) : 'edit_posts',
			'admin_capability' => in_array( $_POST['admin_capability'] ?? 'manage_options', $allowed_caps, true ) ? sanitize_key( $_POST['admin_capability'] ) : 'manage_options',
		);

		$valid_module_slugs = array(
			'content', 'media', 'seo', 'health', 'users',
			'woocommerce', 'comments', 'cpt',
			'redirects', 'taxonomy', 'performance',
			'management', 'diagnostics', 'menus',
			'database', 'security', 'backup',
		);

		// Form posts enabled_tools[] for each checked (enabled) tool.
		// Compute disabled = all known tools minus the enabled ones submitted.
		$all_tool_names = array_map(
			fn( $t ) => $t['name'],
			$this->abilities_manager->get_mcp_tools()
		);

		$posted_enabled = array_filter(
			(array) ( $_POST['enabled_tools'] ?? array() ),
			fn( $name ) => (bool) preg_match( '/^tr-ai-toolkit-[a-z0-9-]+$/', $name )
		);
		$posted_enabled = array_map( 'sanitize_key', $posted_enabled );

		$computed_disabled = array_values( array_diff( $all_tool_names, $posted_enabled ) );

		$mcp = array(
			'server_name'     => sanitize_text_field( $_POST['server_name'] ?? '' ),
			'exposed_modules' => array_values( array_intersect(
				(array) ( $_POST['exposed_modules'] ?? array() ),
				$valid_module_slugs
			) ),
			'disabled_tools'  => $computed_disabled,
		);

		$logging = array(
			'enabled'        => isset( $_POST['logging_enabled'] ),
			'retention_days' => max( 1, min( 365, (int) ( $_POST['retention_days'] ?? 30 ) ) ),
		);

		$this->settings->update_group( 'general', $general );
		$this->settings->update_group( 'permissions', $permissions );
		$this->settings->update_group( 'mcp', $mcp );
		$this->settings->update_group( 'logging', $logging );

		wp_safe_redirect( add_query_arg( 'settings-saved', '1', admin_url( 'admin.php?page=tr-ai-toolkit-settings' ) ) );
		exit;
	}

	/**
	 * Shows a notice if the MCP Adapter plugin is not installed/active.
	 */
	public function show_mcp_adapter_notice(): void {
		// Show auto-install success notice (any admin screen, one-time).
		if ( get_option( 'tr_ai_toolkit_mcp_adapter_auto_installed' ) ) {
			delete_option( 'tr_ai_toolkit_mcp_adapter_auto_installed' );
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo '<strong>' . esc_html__( 'TR AI Toolkit:', 'tr-wordpress-ai-toolkit' ) . '</strong> ';
			echo esc_html__( 'MCP Adapter plugin was automatically installed and activated. Your site is now ready to connect AI assistants.', 'tr-wordpress-ai-toolkit' );
			echo '</p></div>';
			return;
		}

		// Show auto-activate success notice.
		if ( get_option( 'tr_ai_toolkit_mcp_adapter_auto_activated' ) ) {
			delete_option( 'tr_ai_toolkit_mcp_adapter_auto_activated' );
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo '<strong>' . esc_html__( 'TR AI Toolkit:', 'tr-wordpress-ai-toolkit' ) . '</strong> ';
			echo esc_html__( 'MCP Adapter plugin was found and activated automatically.', 'tr-wordpress-ai-toolkit' );
			echo '</p></div>';
			return;
		}

		// Show install error notice.
		$install_error = get_option( 'tr_ai_toolkit_mcp_adapter_install_error' );
		if ( $install_error ) {
			echo '<div class="notice notice-error is-dismissible"><p>';
			echo '<strong>' . esc_html__( 'TR AI Toolkit:', 'tr-wordpress-ai-toolkit' ) . '</strong> ';
			echo wp_kses_post(
				sprintf(
					/* translators: %1$s: error message, %2$s: manual install URL */
					__( 'Could not auto-install MCP Adapter: %1$s. Please <a href="%2$s">install it manually</a>.', 'tr-wordpress-ai-toolkit' ),
					esc_html( $install_error ),
					esc_url( admin_url( 'plugin-install.php?s=mcp-adapter&tab=search&type=term' ) )
				)
			);
			echo '</p></div>';
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'tr-ai-toolkit' ) === false ) {
			return;
		}

		if ( $this->is_mcp_adapter_active() ) {
			return;
		}

		// MCP Adapter missing — show install prompt.
		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo wp_kses_post(
			sprintf(
				/* translators: %s: link to plugin installation page */
				__( '<strong>TR AI Toolkit:</strong> The <a href="%s">MCP Adapter plugin</a> is not active. Install and activate it to connect AI assistants to this site.', 'tr-wordpress-ai-toolkit' ),
				esc_url( admin_url( 'plugin-install.php?s=mcp-adapter&tab=search&type=term' ) )
			)
		);
		echo '</p></div>';
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function check_capability( string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'tr-wordpress-ai-toolkit' ) );
		}
	}

	/**
	 * Loads a view file, extracting $data into local variables.
	 *
	 * @param string               $view View filename without extension.
	 * @param array<string, mixed> $data Variables to extract.
	 */
	private function render_view( string $view, array $data = array() ): void {
		$file = TR_AI_TOOLKIT_PATH . 'admin/views/' . sanitize_file_name( $view ) . '.php';

		if ( ! file_exists( $file ) ) {
			wp_die( esc_html( "View not found: {$view}" ) );
		}

		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		require $file;
	}

	/**
	 * Returns true when the MCP Adapter plugin is active.
	 */
	private function is_mcp_adapter_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return defined( 'WP_MCP_VERSION' )
			|| is_plugin_active( 'mcp-adapter/mcp-adapter.php' )
			|| function_exists( 'mcp_adapter_init' );
	}
}
