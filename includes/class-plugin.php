<?php
/**
 * Plugin bootstrap (singleton).
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Central plugin class — wires all components together.
 */
class TR_AI_Toolkit_Plugin {

	private static ?self $instance = null;

	private TR_AI_Toolkit_Settings          $settings;
	private TR_AI_Toolkit_Permissions       $permissions;
	private TR_AI_Toolkit_Logger            $logger;
	private TR_AI_Toolkit_Abilities_Manager $abilities_manager;

	private function __construct() {
		$this->load_dependencies();
		$this->settings          = new TR_AI_Toolkit_Settings();
		$this->permissions       = new TR_AI_Toolkit_Permissions( $this->settings );
		$this->logger            = new TR_AI_Toolkit_Logger( $this->settings );
		$this->abilities_manager = new TR_AI_Toolkit_Abilities_Manager(
			$this->settings,
			$this->permissions,
			$this->logger
		);
	}

	/**
	 * Returns the singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Attaches all hooks.
	 */
	public function run(): void {
		$this->abilities_manager->init();
		$this->load_text_domain();
		$this->schedule_log_pruning();

		if ( is_admin() ) {
			require_once TR_AI_TOOLKIT_PATH . 'admin/class-admin.php';
			( new TR_AI_Toolkit_Admin( $this->settings, $this->abilities_manager, $this->logger ) )->init();
		}

		// MCP server registration (only when MCP Adapter is active).
		if ( function_exists( 'wp_register_ability' ) ) {
			require_once TR_AI_TOOLKIT_PATH . 'integrations/mcp-adapter/class-mcp-server.php';
			( new TR_AI_Toolkit_MCP_Server( $this->settings ) )->init();
		}

		do_action( 'tr_ai_toolkit_loaded', $this );
	}

	/**
	 * Requires all shared include files.
	 */
	private function load_dependencies(): void {
		require_once TR_AI_TOOLKIT_PATH . 'includes/class-settings.php';
		require_once TR_AI_TOOLKIT_PATH . 'includes/class-permissions.php';
		require_once TR_AI_TOOLKIT_PATH . 'includes/class-logger.php';
		require_once TR_AI_TOOLKIT_PATH . 'includes/class-abilities-manager.php';
	}

	/**
	 * Loads plugin translations.
	 */
	private function load_text_domain(): void {
		load_plugin_textdomain(
			'tr-wordpress-ai-toolkit',
			false,
			dirname( TR_AI_TOOLKIT_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Registers a daily WP-Cron event to prune old audit log entries.
	 */
	private function schedule_log_pruning(): void {
		if ( ! wp_next_scheduled( 'tr_ai_toolkit_prune_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'tr_ai_toolkit_prune_logs' );
		}

		add_action( 'tr_ai_toolkit_prune_logs', array( $this->logger, 'prune' ) );
	}

	public function get_settings(): TR_AI_Toolkit_Settings {
		return $this->settings;
	}

	public function get_abilities_manager(): TR_AI_Toolkit_Abilities_Manager {
		return $this->abilities_manager;
	}

	public function get_logger(): TR_AI_Toolkit_Logger {
		return $this->logger;
	}
}
