<?php
/**
 * Menus module.
 *
 * Covers two areas:
 *  1. Navigation menus — list, inspect, create, add/update/delete items,
 *     and assign menus to theme locations.
 *  2. Widget areas — list registered sidebars and their active widgets.
 *
 * Security notes:
 *  - All write operations require admin capability.
 *  - Inputs are sanitized with sanitize_text_field(), sanitize_key(), absint(),
 *    esc_url_raw() before use.
 *  - Theme location slugs are validated against get_registered_nav_menus().
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Navigation menu and widget-area MCP tools.
 */
class TR_AI_Toolkit_Menus_Module {

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
		// Navigation menus.
		$this->register_list_nav_menus();
		$this->register_get_nav_menu_items();
		$this->register_create_nav_menu();
		$this->register_add_menu_item();
		$this->register_update_menu_item();
		$this->register_delete_menu_item();
		$this->register_assign_menu_to_location();
		// Widget areas.
		$this->register_list_widget_areas();
		$this->register_list_widgets();
	}

	// =========================================================================
	// NAVIGATION MENUS
	// =========================================================================

	private function register_list_nav_menus(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-nav-menus',
			array(
				'label'       => __( 'List Navigation Menus', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all registered navigation menus with their IDs, slugs, item counts, and assigned theme locations. Also lists registered theme locations and which ones have no menu assigned.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_nav_menus( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_nav_menus( array $input ): array {
		$menus            = wp_get_nav_menus();
		$locations        = get_nav_menu_locations();
		$reg_locations    = get_registered_nav_menus();

		$result = array();
		foreach ( $menus as $menu ) {
			$assigned = array();
			foreach ( $locations as $loc_slug => $menu_id ) {
				if ( (int) $menu_id === (int) $menu->term_id ) {
					$assigned[] = array(
						'slug'  => $loc_slug,
						'label' => $reg_locations[ $loc_slug ] ?? $loc_slug,
					);
				}
			}
			$result[] = array(
				'id'         => $menu->term_id,
				'name'       => $menu->name,
				'slug'       => $menu->slug,
				'item_count' => (int) $menu->count,
				'locations'  => $assigned,
			);
		}

		$unassigned_locations = array();
		foreach ( $reg_locations as $loc_slug => $label ) {
			if ( ! isset( $locations[ $loc_slug ] ) || empty( $locations[ $loc_slug ] ) ) {
				$unassigned_locations[] = array( 'slug' => $loc_slug, 'label' => $label );
			}
		}

		$this->logger->log( 'tr-ai-toolkit-list-nav-menus', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'total_menus'          => count( $result ),
			'menus'                => $result,
			'registered_locations' => array_map(
				fn( $s, $l ) => array( 'slug' => $s, 'label' => $l ),
				array_keys( $reg_locations ),
				array_values( $reg_locations )
			),
			'unassigned_locations' => $unassigned_locations,
		);
	}

	private function register_get_nav_menu_items(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-nav-menu-items',
			array(
				'label'       => __( 'Get Navigation Menu Items', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all items in a navigation menu, sorted by position. Each item includes its title, URL, type, parent, CSS classes, and linked object details. Accepts menu ID, slug, or name.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'menu' ),
					'properties' => array(
						'menu' => array( 'type' => array( 'string', 'integer' ), 'description' => 'Menu ID, slug, or name.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_nav_menu_items( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_nav_menu_items( array $input ): array|\WP_Error {
		$menu_obj = wp_get_nav_menu_object( $input['menu'] ?? '' );
		if ( ! $menu_obj || is_wp_error( $menu_obj ) ) {
			$this->logger->log( 'tr-ai-toolkit-get-nav-menu-items', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'not_found', 'Menu not found.', array( 'status' => 404 ) );
		}

		$items = wp_get_nav_menu_items( $menu_obj->term_id );
		if ( false === $items ) {
			$items = array();
		}

		$result = array();
		foreach ( $items as $item ) {
			$result[] = array(
				'id'          => $item->ID,
				'title'       => $item->title,
				'url'         => $item->url,
				'type'        => $item->type,
				'type_label'  => $item->type_label,
				'object'      => $item->object,
				'object_id'   => (int) $item->object_id,
				'parent_id'   => (int) $item->menu_item_parent,
				'position'    => (int) $item->menu_order,
				'target'      => $item->target,
				'classes'     => implode( ' ', (array) $item->classes ),
				'description' => $item->description,
				'attr_title'  => $item->attr_title,
				'status'      => $item->post_status,
			);
		}
		usort( $result, fn( $a, $b ) => $a['position'] <=> $b['position'] );

		$this->logger->log( 'tr-ai-toolkit-get-nav-menu-items', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'menu_id'    => $menu_obj->term_id,
			'menu_name'  => $menu_obj->name,
			'item_count' => count( $result ),
			'items'      => $result,
		);
	}

	private function register_create_nav_menu(): void {
		wp_register_ability(
			'tr-ai-toolkit-create-nav-menu',
			array(
				'label'       => __( 'Create Navigation Menu', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a new navigation menu with the given name. Optionally assigns it to a registered theme location slug immediately after creation.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name'     => array( 'type' => 'string', 'description' => 'Menu name.' ),
						'location' => array( 'type' => 'string', 'description' => 'Theme location slug to assign the menu to after creation. Optional.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_create_nav_menu( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_create_nav_menu( array $input ): array|\WP_Error {
		$name = sanitize_text_field( $input['name'] ?? '' );
		if ( empty( $name ) ) {
			$this->logger->log( 'tr-ai-toolkit-create-nav-menu', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'missing_param', 'name is required.', array( 'status' => 400 ) );
		}

		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			$this->logger->log( 'tr-ai-toolkit-create-nav-menu', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return $menu_id;
		}

		$location = sanitize_text_field( $input['location'] ?? '' );
		if ( ! empty( $location ) ) {
			$reg = get_registered_nav_menus();
			if ( isset( $reg[ $location ] ) ) {
				$locations             = get_nav_menu_locations();
				$locations[ $location ] = $menu_id;
				set_theme_mod( 'nav_menu_locations', $locations );
			}
		}

		$this->logger->log( 'tr-ai-toolkit-create-nav-menu', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success'  => true,
			'menu_id'  => $menu_id,
			'name'     => $name,
			'location' => $location ?: null,
			'message'  => __( 'Navigation menu created successfully.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	private function register_add_menu_item(): void {
		wp_register_ability(
			'tr-ai-toolkit-add-menu-item',
			array(
				'label'       => __( 'Add Menu Item', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Adds a new item to a navigation menu. For custom links supply a URL. For post_type or taxonomy items supply object and object_id. Accepts menu ID, slug, or name.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'menu', 'title' ),
					'properties' => array(
						'menu'      => array( 'type' => array( 'string', 'integer' ), 'description' => 'Menu ID, slug, or name.' ),
						'title'     => array( 'type' => 'string', 'description' => 'Item label.' ),
						'url'       => array( 'type' => 'string',  'description' => 'URL for custom type items.' ),
						'type'      => array( 'type' => 'string',  'description' => 'custom, post_type, or taxonomy. Default: custom.' ),
						'object'    => array( 'type' => 'string',  'description' => 'Object type: page, post, category, etc. Required for post_type/taxonomy.' ),
						'object_id' => array( 'type' => 'integer', 'description' => 'Object ID. Required for post_type/taxonomy.' ),
						'parent_id' => array( 'type' => 'integer', 'description' => 'Parent menu item ID for nested items. Default: 0.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_add_menu_item( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_add_menu_item( array $input ): array|\WP_Error {
		$menu_obj = wp_get_nav_menu_object( $input['menu'] ?? '' );
		if ( ! $menu_obj ) {
			$this->logger->log( 'tr-ai-toolkit-add-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'not_found', 'Menu not found.', array( 'status' => 404 ) );
		}

		$type      = sanitize_key( $input['type'] ?? 'custom' );
		$item_args = array(
			'menu-item-title'     => sanitize_text_field( $input['title'] ?? '' ),
			'menu-item-type'      => $type,
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => absint( $input['parent_id'] ?? 0 ),
		);

		if ( 'custom' === $type ) {
			$item_args['menu-item-url'] = esc_url_raw( $input['url'] ?? '#' );
		} else {
			$item_args['menu-item-object']    = sanitize_key( $input['object'] ?? '' );
			$item_args['menu-item-object-id'] = absint( $input['object_id'] ?? 0 );
		}

		$item_id = wp_update_nav_menu_item( $menu_obj->term_id, 0, $item_args );
		if ( is_wp_error( $item_id ) ) {
			$this->logger->log( 'tr-ai-toolkit-add-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return $item_id;
		}

		$this->logger->log( 'tr-ai-toolkit-add-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success' => true,
			'item_id' => $item_id,
			'menu_id' => $menu_obj->term_id,
			'message' => __( 'Menu item added.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	private function register_update_menu_item(): void {
		wp_register_ability(
			'tr-ai-toolkit-update-menu-item',
			array(
				'label'       => __( 'Update Menu Item', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates a navigation menu item\'s title, URL, parent, or position. Only the fields you supply are changed; all others retain their current values.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'item_id' ),
					'properties' => array(
						'item_id'   => array( 'type' => 'integer', 'description' => 'Menu item post ID.' ),
						'title'     => array( 'type' => 'string',  'description' => 'New label.' ),
						'url'       => array( 'type' => 'string',  'description' => 'New URL (custom items only).' ),
						'parent_id' => array( 'type' => 'integer', 'description' => 'New parent item ID.' ),
						'position'  => array( 'type' => 'integer', 'description' => 'New menu order position.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_update_menu_item( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_update_menu_item( array $input ): array|\WP_Error {
		$item_id = absint( $input['item_id'] ?? 0 );
		if ( ! $item_id ) {
			$this->logger->log( 'tr-ai-toolkit-update-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'missing_param', 'item_id is required.', array( 'status' => 400 ) );
		}

		$post = get_post( $item_id );
		if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
			$this->logger->log( 'tr-ai-toolkit-update-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'not_found', 'Menu item not found.', array( 'status' => 404 ) );
		}

		$existing_args = array(
			'menu-item-title'     => $post->post_title,
			'menu-item-url'       => get_post_meta( $item_id, '_menu_item_url', true ),
			'menu-item-parent-id' => (int) get_post_meta( $item_id, '_menu_item_menu_item_parent', true ),
			'menu-item-position'  => $post->menu_order,
			'menu-item-status'    => 'publish',
		);

		if ( isset( $input['title'] ) )     { $existing_args['menu-item-title']     = sanitize_text_field( $input['title'] ); }
		if ( isset( $input['url'] ) )       { $existing_args['menu-item-url']       = esc_url_raw( $input['url'] ); }
		if ( isset( $input['parent_id'] ) ) { $existing_args['menu-item-parent-id'] = absint( $input['parent_id'] ); }
		if ( isset( $input['position'] ) )  { $existing_args['menu-item-position']  = absint( $input['position'] ); }

		$menus   = wp_get_post_terms( $item_id, 'nav_menu' );
		$menu_id = ! empty( $menus ) ? $menus[0]->term_id : 0;

		$updated = wp_update_nav_menu_item( $menu_id, $item_id, $existing_args );
		if ( is_wp_error( $updated ) ) {
			$this->logger->log( 'tr-ai-toolkit-update-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return $updated;
		}

		$this->logger->log( 'tr-ai-toolkit-update-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success' => true,
			'item_id' => $item_id,
			'message' => __( 'Menu item updated.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	private function register_delete_menu_item(): void {
		wp_register_ability(
			'tr-ai-toolkit-delete-menu-item',
			array(
				'label'       => __( 'Delete Menu Item', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Permanently deletes a navigation menu item by its post ID. Menu items are not moved to trash — this is an immediate hard delete.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'item_id' ),
					'properties' => array(
						'item_id' => array( 'type' => 'integer', 'description' => 'Menu item post ID to delete.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_delete_menu_item( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_delete_menu_item( array $input ): array|\WP_Error {
		$item_id = absint( $input['item_id'] ?? 0 );
		if ( ! $item_id ) {
			$this->logger->log( 'tr-ai-toolkit-delete-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'missing_param', 'item_id is required.', array( 'status' => 400 ) );
		}

		$post = get_post( $item_id );
		if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
			$this->logger->log( 'tr-ai-toolkit-delete-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'not_found', 'Menu item not found.', array( 'status' => 404 ) );
		}

		$result = wp_delete_post( $item_id, true );
		if ( ! $result ) {
			$this->logger->log( 'tr-ai-toolkit-delete-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'delete_failed', 'Failed to delete menu item.', array( 'status' => 500 ) );
		}

		$this->logger->log( 'tr-ai-toolkit-delete-menu-item', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success' => true,
			'item_id' => $item_id,
			'message' => __( 'Menu item deleted.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	private function register_assign_menu_to_location(): void {
		wp_register_ability(
			'tr-ai-toolkit-assign-menu-to-location',
			array(
				'label'       => __( 'Assign Menu to Location', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Assigns a navigation menu to a registered theme location, or clears the location by passing menu as 0 or an empty string. Returns an error if the location slug is not registered by the active theme.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'location' ),
					'properties' => array(
						'menu'     => array( 'type' => array( 'string', 'integer' ), 'description' => 'Menu ID, slug, or name. Pass 0 or empty string to unassign.' ),
						'location' => array( 'type' => 'string', 'description' => 'Registered theme location slug.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_assign_menu_to_location( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_assign_menu_to_location( array $input ): array|\WP_Error {
		$location = sanitize_key( $input['location'] ?? '' );
		if ( empty( $location ) ) {
			$this->logger->log( 'tr-ai-toolkit-assign-menu-to-location', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'missing_param', 'location is required.', array( 'status' => 400 ) );
		}

		$reg = get_registered_nav_menus();
		if ( ! isset( $reg[ $location ] ) ) {
			$this->logger->log( 'tr-ai-toolkit-assign-menu-to-location', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'invalid_location', "Theme location '{$location}' is not registered.", array( 'status' => 400 ) );
		}

		$menu_input = $input['menu'] ?? '';
		$menu_id    = 0;
		if ( ! empty( $menu_input ) ) {
			$menu_obj = wp_get_nav_menu_object( $menu_input );
			if ( ! $menu_obj ) {
				$this->logger->log( 'tr-ai-toolkit-assign-menu-to-location', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
				return new \WP_Error( 'not_found', 'Menu not found.', array( 'status' => 404 ) );
			}
			$menu_id = $menu_obj->term_id;
		}

		$locations             = get_nav_menu_locations();
		$locations[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );

		$this->logger->log( 'tr-ai-toolkit-assign-menu-to-location', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success'  => true,
			'location' => $location,
			'menu_id'  => $menu_id,
			'message'  => $menu_id
				? sprintf( __( "Menu assigned to location '%s'.", 'tr-wordpress-ai-toolkit' ), $location )
				: sprintf( __( "Location '%s' cleared.", 'tr-wordpress-ai-toolkit' ), $location ),
		);
	}

	// =========================================================================
	// WIDGET AREAS
	// =========================================================================

	private function register_list_widget_areas(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-widget-areas',
			array(
				'label'       => __( 'List Widget Areas', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all registered widget areas (sidebars) with their IDs, names, descriptions, and active widget counts.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_widget_areas( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_widget_areas( array $input ): array {
		global $wp_registered_sidebars;

		$sidebars_widgets = wp_get_sidebars_widgets();
		$result           = array();

		foreach ( $wp_registered_sidebars as $id => $sidebar ) {
			$widget_ids = $sidebars_widgets[ $id ] ?? array();
			$result[]   = array(
				'id'           => $id,
				'name'         => $sidebar['name'],
				'description'  => $sidebar['description'] ?? '',
				'widget_count' => count( $widget_ids ),
			);
		}

		$this->logger->log( 'tr-ai-toolkit-list-widget-areas', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'total'        => count( $result ),
			'widget_areas' => $result,
		);
	}

	private function register_list_widgets(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-widgets',
			array(
				'label'       => __( 'List Widgets in Sidebar', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all active widgets in a widget area, including their IDs, names, positions, titles, and setting keys. Sensitive setting values are not returned — only key names.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'sidebar_id' ),
					'properties' => array(
						'sidebar_id' => array( 'type' => 'string', 'description' => 'Widget area ID.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_widgets( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_widgets( array $input ): array|\WP_Error {
		global $wp_registered_sidebars, $wp_registered_widgets;

		$sidebar_id = sanitize_key( $input['sidebar_id'] ?? '' );
		if ( empty( $sidebar_id ) ) {
			$this->logger->log( 'tr-ai-toolkit-list-widgets', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'missing_param', 'sidebar_id is required.', array( 'status' => 400 ) );
		}

		if ( ! isset( $wp_registered_sidebars[ $sidebar_id ] ) ) {
			$this->logger->log( 'tr-ai-toolkit-list-widgets', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'not_found', "Widget area '{$sidebar_id}' not found.", array( 'status' => 404 ) );
		}

		$sidebars_widgets = wp_get_sidebars_widgets();
		$widget_ids       = $sidebars_widgets[ $sidebar_id ] ?? array();
		$result           = array();

		foreach ( $widget_ids as $position => $widget_id ) {
			$last_dash     = strrpos( $widget_id, '-' );
			$widget_base   = $last_dash !== false ? substr( $widget_id, 0, $last_dash ) : $widget_id;
			$widget_number = $last_dash !== false ? (int) substr( $widget_id, $last_dash + 1 ) : null;

			$option_name   = 'widget_' . $widget_base;
			$all_instances = get_option( $option_name, array() );
			$instance      = ( $widget_number && isset( $all_instances[ $widget_number ] ) ) ? $all_instances[ $widget_number ] : array();

			$widget_name = isset( $wp_registered_widgets[ $widget_id ] ) ? $wp_registered_widgets[ $widget_id ]['name'] : $widget_base;

			$result[] = array(
				'id'            => $widget_id,
				'widget_base'   => $widget_base,
				'position'      => $position + 1,
				'name'          => $widget_name,
				'title'         => sanitize_text_field( $instance['title'] ?? '' ),
				'settings_keys' => array_keys( $instance ),
			);
		}

		$this->logger->log( 'tr-ai-toolkit-list-widgets', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'sidebar_id'   => $sidebar_id,
			'sidebar_name' => $wp_registered_sidebars[ $sidebar_id ]['name'],
			'widget_count' => count( $result ),
			'widgets'      => $result,
		);
	}

	// =========================================================================
	// Module interface
	// =========================================================================

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-list-nav-menus',
			'tr-ai-toolkit-get-nav-menu-items',
			'tr-ai-toolkit-create-nav-menu',
			'tr-ai-toolkit-add-menu-item',
			'tr-ai-toolkit-update-menu-item',
			'tr-ai-toolkit-delete-menu-item',
			'tr-ai-toolkit-assign-menu-to-location',
			'tr-ai-toolkit-list-widget-areas',
			'tr-ai-toolkit-list-widgets',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-list-nav-menus'          => $this->execute_list_nav_menus( $args ),
			'tr-ai-toolkit-get-nav-menu-items'       => $this->execute_get_nav_menu_items( $args ),
			'tr-ai-toolkit-create-nav-menu'          => $this->execute_create_nav_menu( $args ),
			'tr-ai-toolkit-add-menu-item'            => $this->execute_add_menu_item( $args ),
			'tr-ai-toolkit-update-menu-item'         => $this->execute_update_menu_item( $args ),
			'tr-ai-toolkit-delete-menu-item'         => $this->execute_delete_menu_item( $args ),
			'tr-ai-toolkit-assign-menu-to-location'  => $this->execute_assign_menu_to_location( $args ),
			'tr-ai-toolkit-list-widget-areas'        => $this->execute_list_widget_areas( $args ),
			'tr-ai-toolkit-list-widgets'             => $this->execute_list_widgets( $args ),
			default                                  => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-list-nav-menus',
				'description' => 'Returns all navigation menus with IDs, slugs, item counts, and assigned theme locations. Also lists registered locations and which are unassigned.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-nav-menu-items',
				'description' => 'Returns all items in a navigation menu sorted by position. Includes title, URL, type, parent, CSS classes, and linked object details.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'menu' ),
					'properties' => array(
						'menu' => array( 'type' => array( 'string', 'integer' ), 'description' => 'Menu ID, slug, or name.' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-create-nav-menu',
				'description' => 'Creates a new navigation menu. Optionally assigns it to a theme location slug after creation.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'name' ),
					'properties' => array(
						'name'     => array( 'type' => 'string' ),
						'location' => array( 'type' => 'string' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-add-menu-item',
				'description' => 'Adds a new item to a navigation menu. Supports custom links, post_type items, and taxonomy items.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'menu', 'title' ),
					'properties' => array(
						'menu'      => array( 'type' => array( 'string', 'integer' ) ),
						'title'     => array( 'type' => 'string' ),
						'url'       => array( 'type' => 'string' ),
						'type'      => array( 'type' => 'string', 'enum' => array( 'custom', 'post_type', 'taxonomy' ) ),
						'object'    => array( 'type' => 'string' ),
						'object_id' => array( 'type' => 'integer' ),
						'parent_id' => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-update-menu-item',
				'description' => 'Updates a navigation menu item. Only supplied fields are changed; others retain their current values.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'item_id' ),
					'properties' => array(
						'item_id'   => array( 'type' => 'integer' ),
						'title'     => array( 'type' => 'string' ),
						'url'       => array( 'type' => 'string' ),
						'parent_id' => array( 'type' => 'integer' ),
						'position'  => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-delete-menu-item',
				'description' => 'Permanently deletes a navigation menu item. This is a hard delete — the item is not moved to trash.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'item_id' ),
					'properties' => array(
						'item_id' => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-assign-menu-to-location',
				'description' => 'Assigns a navigation menu to a registered theme location. Pass menu as 0 or empty string to clear the location.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'location' ),
					'properties' => array(
						'menu'     => array( 'type' => array( 'string', 'integer' ) ),
						'location' => array( 'type' => 'string' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-list-widget-areas',
				'description' => 'Returns all registered widget areas (sidebars) with IDs, names, descriptions, and active widget counts.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-list-widgets',
				'description' => 'Returns active widgets in a widget area with their IDs, names, positions, titles, and setting key names.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'sidebar_id' ),
					'properties' => array(
						'sidebar_id' => array( 'type' => 'string' ),
					),
				),
			),
		);
	}
}
