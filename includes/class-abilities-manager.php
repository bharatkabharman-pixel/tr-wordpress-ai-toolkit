<?php
/**
 * Abilities registration manager.
 *
 * Registers the plugin's ability category and loads each module so it can
 * register its own abilities against the WordPress Abilities API.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates ability registration for the entire plugin.
 */
class TR_AI_Toolkit_Abilities_Manager {

	public const CATEGORY = 'tr-ai-toolkit';

	private TR_AI_Toolkit_Settings    $settings;
	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	/** @var array<string, object> Loaded module instances */
	private array $modules = array();

	public function __construct(
		TR_AI_Toolkit_Settings $settings,
		TR_AI_Toolkit_Permissions $permissions,
		TR_AI_Toolkit_Logger $logger
	) {
		$this->settings    = $settings;
		$this->permissions = $permissions;
		$this->logger      = $logger;
	}

	/**
	 * Attaches hooks for ability and category registration.
	 */
	public function init(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		if ( ! $this->settings->get( 'general.plugin_enabled', true ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Registers the plugin's ability category.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'TR AI Toolkit', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Abilities registered by the TR WordPress AI Toolkit plugin.', 'tr-wordpress-ai-toolkit' ),
			)
		);
	}

	/**
	 * Loads module files and triggers ability registration on each active module.
	 */
	public function register_abilities(): void {
		$module_map = array(
			'content'     => array(
				'file'  => TR_AI_TOOLKIT_PATH . 'modules/content/class-content-module.php',
				'class' => 'TR_AI_Toolkit_Content_Module',
			),
			'media'       => array(
				'file'  => TR_AI_TOOLKIT_PATH . 'modules/media/class-media-module.php',
				'class' => 'TR_AI_Toolkit_Media_Module',
			),
			'seo'         => array(
				'file'  => TR_AI_TOOLKIT_PATH . 'modules/seo/class-seo-module.php',
				'class' => 'TR_AI_Toolkit_SEO_Module',
			),
			'health'      => array(
				'file'  => TR_AI_TOOLKIT_PATH . 'modules/health/class-health-module.php',
				'class' => 'TR_AI_Toolkit_Health_Module',
			),
			'users'       => array(
				'file'  => TR_AI_TOOLKIT_PATH . 'modules/users/class-users-module.php',
				'class' => 'TR_AI_Toolkit_Users_Module',
			),
			'woocommerce' => array(
				'file'  => TR_AI_TOOLKIT_PATH . 'modules/woocommerce/class-woocommerce-module.php',
				'class' => 'TR_AI_Toolkit_WooCommerce_Module',
			),
			'comments'    => array(
				'file'  => TR_AI_TOOLKIT_PATH . 'modules/comments/class-comments-module.php',
				'class' => 'TR_AI_Toolkit_Comments_Module',
			),
			'cpt'         => array(
				'file'  => TR_AI_TOOLKIT_PATH . 'modules/cpt/class-cpt-module.php',
				'class' => 'TR_AI_Toolkit_CPT_Module',
			),
			'redirects'   => array(
				'file'  => TR_AI_TOOLKIT_PATH . 'modules/redirects/class-redirects-module.php',
				'class' => 'TR_AI_Toolkit_Redirects_Module',
			),
			'taxonomy'    => array(
				'file'  => TR_AI_TOOLKIT_PATH . 'modules/taxonomy/class-taxonomy-module.php',
				'class' => 'TR_AI_Toolkit_Taxonomy_Module',
			),
		);

		// Auto-expose any module that is not yet in the saved setting so that
		// existing installs automatically gain new modules on update.
		$auto_modules = array( 'woocommerce', 'comments', 'cpt', 'redirects', 'taxonomy' );
		$saved        = (array) $this->settings->get( 'mcp.exposed_modules', array() );
		$exposed      = array_unique( array_merge( $saved, $auto_modules ) );

		if ( empty( $exposed ) ) {
			$exposed = array_keys( $module_map );
		}

		foreach ( $module_map as $slug => $spec ) {
			if ( ! in_array( $slug, $exposed, true ) ) {
				continue;
			}

			if ( ! file_exists( $spec['file'] ) ) {
				continue;
			}

			require_once $spec['file'];

			$class = $spec['class'];
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$module = new $class( $this->permissions, $this->logger );
			$module->register();
			$this->modules[ $slug ] = $module;
		}
	}

	/**
	 * Returns the count of loaded modules.
	 */
	public function module_count(): int {
		return count( $this->modules );
	}

	/**
	 * Returns the loaded module instances keyed by slug.
	 *
	 * @return array<string, object>
	 */
	public function get_modules(): array {
		return $this->modules;
	}

	/**
	 * Returns MCP tool definitions for all active modules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_mcp_tools(): array {
		$this->ensure_modules_loaded();

		$tools = array();
		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'get_mcp_tools' ) ) {
				foreach ( $module->get_mcp_tools() as $tool ) {
					$tools[] = $tool;
				}
			}
		}

		return $tools;
	}

	/**
	 * Executes an MCP tool by name, delegating to the responsible module.
	 *
	 * @param string               $name Tool name.
	 * @param array<string, mixed> $args Tool arguments.
	 * @return mixed
	 */
	public function execute_mcp_tool( string $name, array $args ): mixed {
		$this->ensure_modules_loaded();

		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'handles' ) && $module->handles( $name ) ) {
				return $module->execute( $name, $args );
			}
		}

		return new \WP_Error( 'not_found', "Tool '{$name}' not found.", array( 'status' => 404 ) );
	}

	/**
	 * Loads modules if they haven't been loaded yet.
	 */
	private function ensure_modules_loaded(): void {
		if ( empty( $this->modules ) ) {
			$this->register_abilities();
		}
	}
}
