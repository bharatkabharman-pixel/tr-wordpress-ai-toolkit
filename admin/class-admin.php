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
					'copied'      => __( 'Copied!', 'tr-wordpress-ai-toolkit' ),
					'copyFail'    => __( 'Copy failed. Please copy manually.', 'tr-wordpress-ai-toolkit' ),
					'ajaxError'   => __( 'Request failed. Please try again.', 'tr-wordpress-ai-toolkit' ),
					'regenerate'  => __( 'Regenerate (creates new credentials)', 'tr-wordpress-ai-toolkit' ),
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
			'settings' => $this->settings,
			'saved'    => isset( $_GET['settings-saved'] ) && '1' === $_GET['settings-saved'], // phpcs:ignore WordPress.Security.NonceVerification
		);
		$this->render_view( 'settings', $data );
	}

	public function render_connect(): void {
		$this->check_capability( 'manage_options' );
		$data = array(
			'mcp_endpoint'    => rest_url( 'tr-ai-toolkit/mcp' ),
			'default_endpoint' => rest_url( 'mcp/mcp' ),
			'stdio_command'   => TR_AI_Toolkit_MCP_Server::get_stdio_command(),
			'mcp_adapter_active' => $this->is_mcp_adapter_active(),
			'abilities_api_active' => function_exists( 'wp_register_ability' ),
		);
		$this->render_view( 'connect', $data );
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
		check_ajax_referer( 'tr_ai_toolkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'tr-wordpress-ai-toolkit' ) ) );
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			wp_send_json_error( array( 'message' => __( 'Application Passwords are not available on this site. Requires WordPress 5.6+.', 'tr-wordpress-ai-toolkit' ) ) );
		}

		$user_id = get_current_user_id();

		if ( ! WP_Application_Passwords::is_application_passwords_available_for_user( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Application Passwords are disabled for your account. Check your site settings.', 'tr-wordpress-ai-toolkit' ) ) );
		}

		$result = WP_Application_Passwords::create_new_application_password(
			$user_id,
			array( 'name' => 'Claude AI — TR AI Toolkit' )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		list( $plain_password, $item ) = $result;

		$user       = get_userdata( $user_id );
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

		$mcp = array(
			'server_name'     => sanitize_text_field( $_POST['server_name'] ?? '' ),
			'exposed_modules' => array_intersect(
				(array) ( $_POST['exposed_modules'] ?? array() ),
				array( 'content', 'media', 'seo', 'health', 'users' )
			),
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
