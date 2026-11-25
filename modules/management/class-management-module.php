<?php
/**
 * Management module.
 * @package TRWordPressAIToolkit
 */
defined( 'ABSPATH' ) || exit;

class TR_AI_Toolkit_Management_Module {

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
		$this->register_list_plugins();
		$this->register_activate_plugin();
		$this->register_deactivate_plugin();
		$this->register_get_plugin_update_status();
		$this->register_list_themes();
		$this->register_activate_theme();
		$this->register_get_site_settings();
		$this->register_update_site_settings();
	}

	// -------------------------------------------------------------------------
	// list-plugins
	// -------------------------------------------------------------------------

	private function register_list_plugins(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-plugins',
			array(
				'label'       => __( 'List Plugins', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a paginated list of all installed plugins with status and update information.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array(
							'type'        => 'string',
							'description' => __( 'Filter by status: all (default), active, inactive.', 'tr-wordpress-ai-toolkit' ),
							'enum'        => array( 'all', 'active', 'inactive' ),
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => __( 'Number of plugins per page. Default 100.', 'tr-wordpress-ai-toolkit' ),
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => __( 'Page number to retrieve. Default 1.', 'tr-wordpress-ai-toolkit' ),
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_plugins( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_list_plugins( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$status   = sanitize_text_field( $input['status'] ?? 'all' );
		$per_page = absint( $input['per_page'] ?? 100 );
		$page     = absint( $input['page'] ?? 1 );

		if ( $per_page < 1 ) {
			$per_page = 100;
		}
		if ( $page < 1 ) {
			$page = 1;
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$update_data    = get_site_transient( 'update_plugins' );

		$plugins = array();
		foreach ( $all_plugins as $file => $data ) {
			$is_active = in_array( $file, $active_plugins, true );
			$plugin_status = $is_active ? 'active' : 'inactive';

			if ( 'all' !== $status && $plugin_status !== $status ) {
				continue;
			}

			$update_available = false;
			$new_version      = null;
			if ( isset( $update_data->response[ $file ] ) ) {
				$update_available = true;
				$new_version      = $update_data->response[ $file ]->new_version ?? null;
			}

			$plugins[] = array(
				'file'             => $file,
				'name'             => $data['Name'] ?? '',
				'version'          => $data['Version'] ?? '',
				'author'           => wp_strip_all_tags( $data['Author'] ?? '' ),
				'description'      => wp_trim_words( wp_strip_all_tags( $data['Description'] ?? '' ), 20 ),
				'status'           => $plugin_status,
				'update_available' => $update_available,
				'new_version'      => $new_version,
			);
		}

		usort( $plugins, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

		$total  = count( $plugins );
		$offset = ( $page - 1 ) * $per_page;
		$paged  = array_slice( $plugins, $offset, $per_page );

		$this->logger->log( 'tr-ai-toolkit-list-plugins', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'plugins'  => $paged,
		);
	}

	// -------------------------------------------------------------------------
	// activate-plugin
	// -------------------------------------------------------------------------

	private function register_activate_plugin(): void {
		wp_register_ability(
			'tr-ai-toolkit-activate-plugin',
			array(
				'label'       => __( 'Activate Plugin', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Activates an installed plugin by its plugin file identifier.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'plugin_file' => array(
							'type'        => 'string',
							'description' => __( 'The plugin file identifier in folder/file.php format.', 'tr-wordpress-ai-toolkit' ),
						),
					),
					'required' => array( 'plugin_file' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_activate_plugin( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_activate_plugin( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_file = sanitize_text_field( $input['plugin_file'] ?? '' );

		if ( empty( $plugin_file ) ) {
			$this->logger->log( 'tr-ai-toolkit-activate-plugin', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return array(
				'success' => false,
				'message' => __( 'plugin_file is required.', 'tr-wordpress-ai-toolkit' ),
			);
		}

		$all_plugins = get_plugins();
		if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
			$this->logger->log( 'tr-ai-toolkit-activate-plugin', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return array(
				'success' => false,
				'message' => __( 'Plugin not found.', 'tr-wordpress-ai-toolkit' ),
				'code'    => 404,
			);
		}

		$active_plugins = get_option( 'active_plugins', array() );
		if ( in_array( $plugin_file, $active_plugins, true ) ) {
			return array(
				'success'     => true,
				'message'     => __( 'Plugin is already active.', 'tr-wordpress-ai-toolkit' ),
				'plugin_file' => $plugin_file,
			);
		}

		$result = activate_plugin( $plugin_file );
		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'tr-ai-toolkit-activate-plugin', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return array(
				'success'      => false,
				'message'      => $result->get_error_message(),
				'error_code'   => $result->get_error_code(),
				'plugin_file'  => $plugin_file,
			);
		}

		$this->logger->log( 'tr-ai-toolkit-activate-plugin', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success'     => true,
			'message'     => __( 'Plugin activated successfully.', 'tr-wordpress-ai-toolkit' ),
			'plugin_file' => $plugin_file,
		);
	}

	// -------------------------------------------------------------------------
	// deactivate-plugin
	// -------------------------------------------------------------------------

	private function register_deactivate_plugin(): void {
		wp_register_ability(
			'tr-ai-toolkit-deactivate-plugin',
			array(
				'label'       => __( 'Deactivate Plugin', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Deactivates an active plugin by its plugin file identifier.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'plugin_file' => array(
							'type'        => 'string',
							'description' => __( 'The plugin file identifier in folder/file.php format.', 'tr-wordpress-ai-toolkit' ),
						),
					),
					'required' => array( 'plugin_file' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_deactivate_plugin( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_deactivate_plugin( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_file = sanitize_text_field( $input['plugin_file'] ?? '' );

		if ( empty( $plugin_file ) ) {
			$this->logger->log( 'tr-ai-toolkit-deactivate-plugin', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return array(
				'success' => false,
				'message' => __( 'plugin_file is required.', 'tr-wordpress-ai-toolkit' ),
			);
		}

		$all_plugins = get_plugins();
		if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
			$this->logger->log( 'tr-ai-toolkit-deactivate-plugin', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return array(
				'success' => false,
				'message' => __( 'Plugin not found.', 'tr-wordpress-ai-toolkit' ),
				'code'    => 404,
			);
		}

		if ( false !== strpos( $plugin_file, 'tr-wordpress-ai-toolkit' ) ) {
			$this->logger->log( 'tr-ai-toolkit-deactivate-plugin', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return array(
				'success' => false,
				'message' => __( 'Cannot deactivate the TR WordPress AI Toolkit plugin via this tool.', 'tr-wordpress-ai-toolkit' ),
			);
		}

		$active_plugins = get_option( 'active_plugins', array() );
		if ( ! in_array( $plugin_file, $active_plugins, true ) ) {
			return array(
				'success'     => true,
				'message'     => __( 'Plugin is already inactive.', 'tr-wordpress-ai-toolkit' ),
				'plugin_file' => $plugin_file,
			);
		}

		deactivate_plugins( $plugin_file );

		$this->logger->log( 'tr-ai-toolkit-deactivate-plugin', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success'     => true,
			'message'     => __( 'Plugin deactivated successfully.', 'tr-wordpress-ai-toolkit' ),
			'plugin_file' => $plugin_file,
		);
	}

	// -------------------------------------------------------------------------
	// get-plugin-update-status
	// -------------------------------------------------------------------------

	private function register_get_plugin_update_status(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-plugin-update-status',
			array(
				'label'               => __( 'Get Plugin Update Status', 'tr-wordpress-ai-toolkit' ),
				'description'         => __( 'Returns a list of plugins that have available updates along with version details.', 'tr-wordpress-ai-toolkit' ),
				'category'            => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_plugin_update_status( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_get_plugin_update_status( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$update_data = get_site_transient( 'update_plugins' );
		$all_plugins = get_plugins();

		$plugins_with_updates = array();

		if ( isset( $update_data->response ) && is_array( $update_data->response ) ) {
			foreach ( $update_data->response as $file => $update_info ) {
				if ( ! isset( $all_plugins[ $file ] ) ) {
					continue;
				}
				$plugins_with_updates[] = array(
					'name'            => $all_plugins[ $file ]['Name'] ?? '',
					'file'            => $file,
					'current_version' => $all_plugins[ $file ]['Version'] ?? '',
					'new_version'     => $update_info->new_version ?? '',
					'update_uri'      => $update_info->url ?? '',
				);
			}
		}

		$last_checked = '';
		if ( isset( $update_data->last_checked ) ) {
			$last_checked = gmdate( 'Y-m-d H:i:s', $update_data->last_checked ) . ' UTC';
		}

		$this->logger->log( 'tr-ai-toolkit-get-plugin-update-status', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'last_checked'      => $last_checked,
			'updates_available' => count( $plugins_with_updates ),
			'total_plugins'     => count( $all_plugins ),
			'plugins'           => $plugins_with_updates,
		);
	}

	// -------------------------------------------------------------------------
	// list-themes
	// -------------------------------------------------------------------------

	private function register_list_themes(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-themes',
			array(
				'label'               => __( 'List Themes', 'tr-wordpress-ai-toolkit' ),
				'description'         => __( 'Returns all installed themes with status and update information.', 'tr-wordpress-ai-toolkit' ),
				'category'            => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_themes( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_list_themes( array $input ): array {
		$all_themes   = wp_get_themes();
		$active_theme = get_option( 'stylesheet' );
		$update_data  = get_site_transient( 'update_themes' );

		$themes = array();
		foreach ( $all_themes as $stylesheet => $theme ) {
			$is_active = ( $stylesheet === $active_theme );

			$update_available = false;
			$new_version      = null;
			if ( isset( $update_data->response[ $stylesheet ] ) ) {
				$update_available = true;
				$new_version      = $update_data->response[ $stylesheet ]['new_version'] ?? null;
			}

			$themes[] = array(
				'stylesheet'       => $stylesheet,
				'name'             => $theme->get( 'Name' ),
				'version'          => $theme->get( 'Version' ),
				'author'           => wp_strip_all_tags( $theme->get( 'Author' ) ),
				'description'      => wp_trim_words( wp_strip_all_tags( $theme->get( 'Description' ) ), 20 ),
				'status'           => $is_active ? 'active' : 'inactive',
				'update_available' => $update_available,
				'new_version'      => $new_version,
			);
		}

		$this->logger->log( 'tr-ai-toolkit-list-themes', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'total'        => count( $themes ),
			'active_theme' => $active_theme,
			'themes'       => $themes,
		);
	}

	// -------------------------------------------------------------------------
	// activate-theme
	// -------------------------------------------------------------------------

	private function register_activate_theme(): void {
		wp_register_ability(
			'tr-ai-toolkit-activate-theme',
			array(
				'label'       => __( 'Activate Theme', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Activates an installed theme by its stylesheet directory name.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'stylesheet' => array(
							'type'        => 'string',
							'description' => __( 'The theme stylesheet directory name.', 'tr-wordpress-ai-toolkit' ),
						),
					),
					'required' => array( 'stylesheet' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_activate_theme( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_activate_theme( array $input ): array {
		$stylesheet = sanitize_text_field( $input['stylesheet'] ?? '' );

		if ( empty( $stylesheet ) ) {
			$this->logger->log( 'tr-ai-toolkit-activate-theme', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return array(
				'success' => false,
				'message' => __( 'stylesheet is required.', 'tr-wordpress-ai-toolkit' ),
			);
		}

		$all_themes = wp_get_themes();
		if ( ! isset( $all_themes[ $stylesheet ] ) ) {
			$this->logger->log( 'tr-ai-toolkit-activate-theme', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return array(
				'success' => false,
				'message' => __( 'Theme not found.', 'tr-wordpress-ai-toolkit' ),
				'code'    => 404,
			);
		}

		$active_theme = get_option( 'stylesheet' );
		if ( $active_theme === $stylesheet ) {
			return array(
				'success'    => true,
				'message'    => __( 'Theme is already active.', 'tr-wordpress-ai-toolkit' ),
				'stylesheet' => $stylesheet,
				'theme_name' => $all_themes[ $stylesheet ]->get( 'Name' ),
			);
		}

		switch_theme( $stylesheet );

		$this->logger->log( 'tr-ai-toolkit-activate-theme', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success'    => true,
			'message'    => __( 'Theme activated successfully.', 'tr-wordpress-ai-toolkit' ),
			'stylesheet' => $stylesheet,
			'theme_name' => $all_themes[ $stylesheet ]->get( 'Name' ),
		);
	}

	// -------------------------------------------------------------------------
	// get-site-settings
	// -------------------------------------------------------------------------

	private function register_get_site_settings(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-site-settings',
			array(
				'label'               => __( 'Get Site Settings', 'tr-wordpress-ai-toolkit' ),
				'description'         => __( 'Returns current WordPress site settings grouped by category.', 'tr-wordpress-ai-toolkit' ),
				'category'            => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_site_settings( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_get_site_settings( array $input ): array {
		$this->logger->log( 'tr-ai-toolkit-get-site-settings', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'general' => array(
				'blogname'        => get_option( 'blogname' ),
				'blogdescription' => get_option( 'blogdescription' ),
				'admin_email'     => get_option( 'admin_email' ),
				'siteurl'         => get_option( 'siteurl' ),
				'home'            => get_option( 'home' ),
				'timezone_string' => get_option( 'timezone_string' ),
				'gmt_offset'      => get_option( 'gmt_offset' ),
				'date_format'     => get_option( 'date_format' ),
				'time_format'     => get_option( 'time_format' ),
				'start_of_week'   => (int) get_option( 'start_of_week' ),
				'language'        => get_locale(),
				'wp_version'      => get_bloginfo( 'version' ),
				'blog_charset'    => get_option( 'blog_charset' ),
			),
			'reading' => array(
				'blogpublic'    => (bool) get_option( 'blogpublic' ),
				'posts_per_page' => (int) get_option( 'posts_per_page' ),
				'posts_per_rss'  => (int) get_option( 'posts_per_rss' ),
			),
			'discussion' => array(
				'default_comment_status' => get_option( 'default_comment_status' ),
				'default_ping_status'    => get_option( 'default_ping_status' ),
				'comment_registration'   => (bool) get_option( 'comment_registration' ),
				'comment_moderation'     => (bool) get_option( 'comment_moderation' ),
			),
			'permalinks' => array(
				'permalink_structure' => get_option( 'permalink_structure' ),
			),
			'uploads' => array(
				'upload_path'     => get_option( 'upload_path' ),
				'upload_url_path' => get_option( 'upload_url_path' ),
			),
		);
	}

	// -------------------------------------------------------------------------
	// update-site-settings
	// -------------------------------------------------------------------------

	private function register_update_site_settings(): void {
		wp_register_ability(
			'tr-ai-toolkit-update-site-settings',
			array(
				'label'       => __( 'Update Site Settings', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates whitelisted WordPress site settings. Protected settings such as siteurl and home cannot be changed.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'        => 'object',
					'description' => __( 'An object where each key is a setting name and each value is the new setting value.', 'tr-wordpress-ai-toolkit' ),
					'properties'  => array(
						'blogname'               => array( 'type' => 'string' ),
						'blogdescription'        => array( 'type' => 'string' ),
						'admin_email'            => array( 'type' => 'string' ),
						'timezone_string'        => array( 'type' => 'string' ),
						'gmt_offset'             => array( 'type' => 'number' ),
						'date_format'            => array( 'type' => 'string' ),
						'time_format'            => array( 'type' => 'string' ),
						'start_of_week'          => array( 'type' => 'integer' ),
						'blogpublic'             => array( 'type' => 'integer' ),
						'blog_charset'           => array( 'type' => 'string' ),
						'posts_per_page'         => array( 'type' => 'integer' ),
						'posts_per_rss'          => array( 'type' => 'integer' ),
						'default_comment_status' => array( 'type' => 'string' ),
						'default_ping_status'    => array( 'type' => 'string' ),
						'comment_registration'   => array( 'type' => 'integer' ),
						'comment_moderation'     => array( 'type' => 'integer' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_update_site_settings( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_update_site_settings( array $input ): array {
		$whitelist = array(
			'blogname',
			'blogdescription',
			'admin_email',
			'timezone_string',
			'gmt_offset',
			'date_format',
			'time_format',
			'start_of_week',
			'blogpublic',
			'blog_charset',
			'posts_per_page',
			'posts_per_rss',
			'default_comment_status',
			'default_ping_status',
			'comment_registration',
			'comment_moderation',
		);

		$integer_settings = array( 'start_of_week', 'blogpublic', 'posts_per_page', 'posts_per_rss', 'comment_registration', 'comment_moderation' );
		$float_settings   = array( 'gmt_offset' );

		$updated = array();
		$skipped = array();

		foreach ( $input as $key => $value ) {
			if ( ! in_array( $key, $whitelist, true ) ) {
				$skipped[] = $key;
				continue;
			}

			if ( 'admin_email' === $key ) {
				$sanitized = sanitize_email( $value );
			} elseif ( in_array( $key, $integer_settings, true ) ) {
				$sanitized = absint( $value );
			} elseif ( in_array( $key, $float_settings, true ) ) {
				$sanitized = (float) $value;
			} else {
				$sanitized = sanitize_text_field( $value );
			}

			update_option( $key, $sanitized );
			$updated[] = $key;
		}

		$this->logger->log( 'tr-ai-toolkit-update-site-settings', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'updated' => $updated,
			'skipped' => $skipped,
		);
	}

	// -------------------------------------------------------------------------
	// Router
	// -------------------------------------------------------------------------

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-list-plugins',
			'tr-ai-toolkit-activate-plugin',
			'tr-ai-toolkit-deactivate-plugin',
			'tr-ai-toolkit-get-plugin-update-status',
			'tr-ai-toolkit-list-themes',
			'tr-ai-toolkit-activate-theme',
			'tr-ai-toolkit-get-site-settings',
			'tr-ai-toolkit-update-site-settings',
		), true );
	}

	public function execute( string $name, array $input ): array {
		return match ( $name ) {
			'tr-ai-toolkit-list-plugins'            => $this->execute_list_plugins( $input ),
			'tr-ai-toolkit-activate-plugin'         => $this->execute_activate_plugin( $input ),
			'tr-ai-toolkit-deactivate-plugin'       => $this->execute_deactivate_plugin( $input ),
			'tr-ai-toolkit-get-plugin-update-status' => $this->execute_get_plugin_update_status( $input ),
			'tr-ai-toolkit-list-themes'             => $this->execute_list_themes( $input ),
			'tr-ai-toolkit-activate-theme'          => $this->execute_activate_theme( $input ),
			'tr-ai-toolkit-get-site-settings'       => $this->execute_get_site_settings( $input ),
			'tr-ai-toolkit-update-site-settings'    => $this->execute_update_site_settings( $input ),
			default                                 => array( 'error' => 'Unknown tool.' ),
		};
	}

	// -------------------------------------------------------------------------
	// MCP tool definitions
	// -------------------------------------------------------------------------

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-list-plugins',
				'description' => __( 'Returns a paginated list of all installed plugins with status and update information.', 'tr-wordpress-ai-toolkit' ),
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array(
							'type'        => 'string',
							'description' => 'Filter by status: all (default), active, inactive.',
							'enum'        => array( 'all', 'active', 'inactive' ),
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Number of plugins per page. Default 100.',
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number to retrieve. Default 1.',
						),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-activate-plugin',
				'description' => __( 'Activates an installed plugin by its plugin file identifier.', 'tr-wordpress-ai-toolkit' ),
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'plugin_file' => array(
							'type'        => 'string',
							'description' => 'The plugin file identifier in folder/file.php format.',
						),
					),
					'required' => array( 'plugin_file' ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-deactivate-plugin',
				'description' => __( 'Deactivates an active plugin by its plugin file identifier.', 'tr-wordpress-ai-toolkit' ),
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'plugin_file' => array(
							'type'        => 'string',
							'description' => 'The plugin file identifier in folder/file.php format.',
						),
					),
					'required' => array( 'plugin_file' ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-plugin-update-status',
				'description' => __( 'Returns a list of plugins that have available updates along with version details.', 'tr-wordpress-ai-toolkit' ),
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-list-themes',
				'description' => __( 'Returns all installed themes with status and update information.', 'tr-wordpress-ai-toolkit' ),
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-activate-theme',
				'description' => __( 'Activates an installed theme by its stylesheet directory name.', 'tr-wordpress-ai-toolkit' ),
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'stylesheet' => array(
							'type'        => 'string',
							'description' => 'The theme stylesheet directory name.',
						),
					),
					'required' => array( 'stylesheet' ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-site-settings',
				'description' => __( 'Returns current WordPress site settings grouped by category.', 'tr-wordpress-ai-toolkit' ),
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-update-site-settings',
				'description' => __( 'Updates whitelisted WordPress site settings. Protected settings such as siteurl and home cannot be changed.', 'tr-wordpress-ai-toolkit' ),
				'inputSchema' => array(
					'type'        => 'object',
					'description' => 'An object where each key is a setting name and each value is the new setting value.',
					'properties'  => array(
						'blogname'               => array( 'type' => 'string', 'description' => 'Site title.' ),
						'blogdescription'        => array( 'type' => 'string', 'description' => 'Site tagline.' ),
						'admin_email'            => array( 'type' => 'string', 'description' => 'Administration email address.' ),
						'timezone_string'        => array( 'type' => 'string', 'description' => 'Timezone string, e.g. America/New_York.' ),
						'gmt_offset'             => array( 'type' => 'number', 'description' => 'UTC offset in hours.' ),
						'date_format'            => array( 'type' => 'string', 'description' => 'PHP date format string.' ),
						'time_format'            => array( 'type' => 'string', 'description' => 'PHP time format string.' ),
						'start_of_week'          => array( 'type' => 'integer', 'description' => 'Day the week starts on (0=Sunday, 1=Monday, etc.).' ),
						'blogpublic'             => array( 'type' => 'integer', 'description' => 'Search engine visibility: 1 to allow, 0 to discourage.' ),
						'blog_charset'           => array( 'type' => 'string', 'description' => 'Character encoding for pages and feeds.' ),
						'posts_per_page'         => array( 'type' => 'integer', 'description' => 'Maximum posts to show on blog pages.' ),
						'posts_per_rss'          => array( 'type' => 'integer', 'description' => 'Maximum posts to include in RSS feeds.' ),
						'default_comment_status' => array( 'type' => 'string', 'description' => 'Default comment status: open or closed.' ),
						'default_ping_status'    => array( 'type' => 'string', 'description' => 'Default ping status: open or closed.' ),
						'comment_registration'   => array( 'type' => 'integer', 'description' => 'Require registration to comment: 1 or 0.' ),
						'comment_moderation'     => array( 'type' => 'integer', 'description' => 'Hold comments for moderation: 1 or 0.' ),
					),
				),
			),
		);
	}
}
