<?php
/**
 * Website health module abilities.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers abilities for inspecting WordPress environment and site health.
 *
 * Security note: sensitive values (DB credentials, auth keys, salts, file paths)
 * are intentionally excluded from every ability response.
 */
class TR_AI_Toolkit_Health_Module {

	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	public function __construct(
		TR_AI_Toolkit_Permissions $permissions,
		TR_AI_Toolkit_Logger $logger
	) {
		$this->permissions = $permissions;
		$this->logger      = $logger;
	}

	public function register(): void {
		$this->register_get_wordpress_info();
		$this->register_get_php_version();
		$this->register_get_active_plugins();
		$this->register_get_active_theme();
		$this->register_get_site_health_summary();
		$this->register_get_permalink_structure();
		$this->register_get_cron_status();
	}

	private function register_get_wordpress_info(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-wordpress-info',
			array(
				'label'       => __( 'Get WordPress Info', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns WordPress version, site URL, language, timezone, admin email (masked), multisite status and memory limit. No credentials or file paths are exposed.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_wordpress_info( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_wordpress_info( array $input ): array {
		global $wp_version;

		$admin_email = get_bloginfo( 'admin_email' );
		$at_pos      = strpos( $admin_email, '@' );
		$masked_email = $at_pos !== false
			? '***@' . substr( $admin_email, $at_pos + 1 )
			: '***';

		$this->logger->log( 'tr-ai-toolkit/get-wordpress-info', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'wordpress_version' => $wp_version,
			'site_url'          => get_site_url(),
			'home_url'          => get_home_url(),
			'site_name'         => get_bloginfo( 'name' ),
			'site_description'  => get_bloginfo( 'description' ),
			'language'          => get_locale(),
			'timezone'          => wp_timezone_string(),
			'admin_email'       => $masked_email,
			'is_multisite'      => is_multisite(),
			'wp_memory_limit'   => WP_MEMORY_LIMIT,
			'wp_debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'https_enabled'     => is_ssl(),
		);
	}

	private function register_get_php_version(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-php-version',
			array(
				'label'       => __( 'Get PHP Version', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns the server PHP version, extensions relevant to WordPress, and whether the version meets current WordPress requirements.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_php_version( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_php_version( array $input ): array {
		$required_extensions = array(
			'curl', 'dom', 'exif', 'fileinfo', 'hash', 'imagick', 'intl',
			'json', 'mbstring', 'mysqli', 'openssl', 'pcre', 'xml', 'zip',
		);

		$ext_status = array();
		foreach ( $required_extensions as $ext ) {
			$ext_status[ $ext ] = extension_loaded( $ext );
		}

		$this->logger->log( 'tr-ai-toolkit/get-php-version', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'php_version'       => PHP_VERSION,
			'php_major_minor'   => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'meets_wp_minimum'  => version_compare( PHP_VERSION, '7.4', '>=' ),
			'meets_recommended' => version_compare( PHP_VERSION, '8.1', '>=' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'max_upload_size'   => wp_max_upload_size(),
			'extensions'        => $ext_status,
		);
	}

	private function register_get_active_plugins(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-active-plugins',
			array(
				'label'       => __( 'Get Active Plugins', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all currently active plugins with name, version, author and update availability. Does not expose file paths or plugin source code.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_active_plugins( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_active_plugins( array $input ): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = get_option( 'active_plugins', array() );
		$plugins        = array();

		foreach ( $active_plugins as $plugin_file ) {
			$full_path   = WP_PLUGIN_DIR . '/' . $plugin_file;
			$plugin_data = file_exists( $full_path ) ? get_plugin_data( $full_path, false, false ) : array();

			$plugins[] = array(
				'slug'    => dirname( $plugin_file ),
				'file'    => basename( $plugin_file ),
				'name'    => $plugin_data['Name'] ?? $plugin_file,
				'version' => $plugin_data['Version'] ?? '',
				'author'  => wp_strip_all_tags( $plugin_data['Author'] ?? '' ),
				'url'     => esc_url( $plugin_data['PluginURI'] ?? '' ),
			);
		}

		$this->logger->log( 'tr-ai-toolkit/get-active-plugins', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'plugins' => $plugins,
			'total'   => count( $plugins ),
		);
	}

	private function register_get_active_theme(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-active-theme',
			array(
				'label'       => __( 'Get Active Theme', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns the active theme name, version, author, parent theme (if any) and whether it supports key WordPress features.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_active_theme( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_active_theme( array $input ): array {
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		$features = array(
			'post-thumbnails'    => current_theme_supports( 'post-thumbnails' ),
			'custom-header'      => current_theme_supports( 'custom-header' ),
			'custom-background'  => current_theme_supports( 'custom-background' ),
			'menus'              => current_theme_supports( 'menus' ),
			'editor-styles'      => current_theme_supports( 'editor-styles' ),
			'wp-block-styles'    => current_theme_supports( 'wp-block-styles' ),
			'responsive-embeds'  => current_theme_supports( 'responsive-embeds' ),
			'align-wide'         => current_theme_supports( 'align-wide' ),
		);

		$this->logger->log( 'tr-ai-toolkit/get-active-theme', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'name'        => $theme->get( 'Name' ),
			'version'     => $theme->get( 'Version' ),
			'author'      => wp_strip_all_tags( $theme->get( 'Author' ) ),
			'author_url'  => esc_url( $theme->get( 'AuthorURI' ) ),
			'description' => wp_strip_all_tags( $theme->get( 'Description' ) ),
			'theme_url'   => esc_url( $theme->get( 'ThemeURI' ) ),
			'is_block_theme' => $theme->is_block_theme(),
			'parent_theme'   => $parent ? array(
				'name'    => $parent->get( 'Name' ),
				'version' => $parent->get( 'Version' ),
			) : null,
			'features'    => $features,
		);
	}

	private function register_get_site_health_summary(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-site-health-summary',
			array(
				'label'       => __( 'Get Site Health Summary', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a site health overview: post/page/media counts, WordPress and PHP versions, debug mode status, HTTPS status, and whether automatic updates are enabled.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_site_health_summary( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_site_health_summary( array $input ): array {
		global $wp_version;

		$post_counts  = wp_count_posts();
		$page_counts  = wp_count_posts( 'page' );
		$media_count  = wp_count_posts( 'attachment' );
		$user_count   = count_users();

		$this->logger->log( 'tr-ai-toolkit/get-site-health-summary', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'wordpress_version'    => $wp_version,
			'php_version'          => PHP_VERSION,
			'https_enabled'        => is_ssl(),
			'wp_debug'             => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'auto_updates_core'    => defined( 'WP_AUTO_UPDATE_CORE' ) ? WP_AUTO_UPDATE_CORE : 'minor',
			'counts'               => array(
				'published_posts'  => (int) ( $post_counts->publish ?? 0 ),
				'draft_posts'      => (int) ( $post_counts->draft ?? 0 ),
				'published_pages'  => (int) ( $page_counts->publish ?? 0 ),
				'media_items'      => (int) ( $media_count->inherit ?? 0 ),
				'total_users'      => (int) ( $user_count['total_users'] ?? 0 ),
			),
			'memory'               => array(
				'limit'       => WP_MEMORY_LIMIT,
				'max_limit'   => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : 'unknown',
				'current_mb'  => round( memory_get_usage( true ) / 1024 / 1024, 1 ),
			),
		);
	}

	private function register_get_permalink_structure(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-permalink-structure',
			array(
				'label'       => __( 'Get Permalink Structure', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns the current permalink structure, category and tag base prefixes, and whether pretty permalinks are enabled.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_permalink_structure( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_permalink_structure( array $input ): array {
		$structure = get_option( 'permalink_structure' );

		$this->logger->log( 'tr-ai-toolkit/get-permalink-structure', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'structure'          => $structure ?: '/?p=%post_id%',
			'pretty_permalinks'  => ! empty( $structure ),
			'category_base'      => get_option( 'category_base' ) ?: 'category',
			'tag_base'           => get_option( 'tag_base' ) ?: 'tag',
			'example_post_url'   => get_permalink( get_option( 'page_on_front' ) ?: 1 ) ?: get_site_url(),
		);
	}

	private function register_get_cron_status(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-cron-status',
			array(
				'label'       => __( 'Get Cron Status', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns the WP-Cron status, whether it is disabled, and the next scheduled events (up to 20). Does not expose cron hook arguments or callback details.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_cron_status( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit/get-wordpress-info', 'tr-ai-toolkit/get-php-version',
			'tr-ai-toolkit/get-active-plugins', 'tr-ai-toolkit/get-active-theme',
			'tr-ai-toolkit/get-site-health-summary', 'tr-ai-toolkit/get-permalink-structure',
			'tr-ai-toolkit/get-cron-status',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit/get-wordpress-info'      => $this->execute_get_wordpress_info( $args ),
			'tr-ai-toolkit/get-php-version'         => $this->execute_get_php_version( $args ),
			'tr-ai-toolkit/get-active-plugins'      => $this->execute_get_active_plugins( $args ),
			'tr-ai-toolkit/get-active-theme'        => $this->execute_get_active_theme( $args ),
			'tr-ai-toolkit/get-site-health-summary' => $this->execute_get_site_health_summary( $args ),
			'tr-ai-toolkit/get-permalink-structure' => $this->execute_get_permalink_structure( $args ),
			'tr-ai-toolkit/get-cron-status'         => $this->execute_get_cron_status( $args ),
			default                                 => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array( 'name' => 'tr-ai-toolkit/get-wordpress-info', 'description' => 'Returns WordPress version, site URL, admin URL, timezone, language and multisite status.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
			array( 'name' => 'tr-ai-toolkit/get-php-version', 'description' => 'Returns PHP version, loaded extensions and key configuration values.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
			array( 'name' => 'tr-ai-toolkit/get-active-plugins', 'description' => 'Returns the list of active plugins with name, version, author and description.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
			array( 'name' => 'tr-ai-toolkit/get-active-theme', 'description' => 'Returns the active theme name, version, author and template hierarchy.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
			array( 'name' => 'tr-ai-toolkit/get-site-health-summary', 'description' => 'Returns post/page/media counts, memory limit, debug mode and update status.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
			array( 'name' => 'tr-ai-toolkit/get-permalink-structure', 'description' => 'Returns permalink structure, category and tag base URLs.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
			array( 'name' => 'tr-ai-toolkit/get-cron-status', 'description' => 'Returns scheduled WP-Cron events with next run times.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_cron_status( array $input ): array {
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$cron_jobs     = _get_cron_array();
		$events        = array();
		$count         = 0;

		if ( is_array( $cron_jobs ) ) {
			foreach ( $cron_jobs as $timestamp => $hooks ) {
				foreach ( array_keys( $hooks ) as $hook ) {
					if ( $count >= 20 ) {
						break 2;
					}
					$events[] = array(
						'hook'     => $hook,
						'next_run' => gmdate( 'Y-m-d H:i:s', (int) $timestamp ),
						'overdue'  => $timestamp < time(),
					);
					++$count;
				}
			}
		}

		$overdue_count = count( array_filter( $events, fn( $e ) => $e['overdue'] ) );

		$this->logger->log( 'tr-ai-toolkit/get-cron-status', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'wp_cron_disabled' => $cron_disabled,
			'total_events'     => is_array( $cron_jobs ) ? array_sum( array_map( 'count', $cron_jobs ) ) : 0,
			'overdue_events'   => $overdue_count,
			'next_events'      => $events,
		);
	}
}
