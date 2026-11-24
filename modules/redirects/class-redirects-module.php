<?php
/**
 * Redirect management module.
 *
 * Integrates with the Redirection plugin (John Godley) using its PHP API
 * when available, with a direct-SQL fallback against known stable table
 * columns. Returns a clear "not installed" response when no provider exists.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * MCP tools for reading and managing URL redirects.
 */
class TR_AI_Toolkit_Redirects_Module {

	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	/** Redirection plugin table name (populated on first access). */
	private ?string $table = null;

	public function __construct(
		TR_AI_Toolkit_Permissions $permissions,
		TR_AI_Toolkit_Logger $logger
	) {
		$this->permissions = $permissions;
		$this->logger      = $logger;
	}

	public function register(): void {
		$this->register_list_redirects();
		$this->register_get_redirect();
		$this->register_create_redirect();
		$this->register_update_redirect();
		$this->register_delete_redirect();
		$this->register_check_redirect_loops();
	}

	// =========================================================================
	// Tool: list-redirects
	// =========================================================================

	private function register_list_redirects(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-redirects',
			array(
				'label'       => __( 'List Redirects', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns URL redirects managed by the Redirection plugin. Returns an error if Redirection is not installed.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'Max results (1–100). Default: 20.' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
						'search'   => array( 'type' => 'string',  'description' => 'Filter by source URL substring.' ),
						'status'   => array( 'type' => 'string',  'description' => 'enabled, disabled, or all. Default: all.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_redirects( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_redirects( array $input ): array|\WP_Error {
		$check = $this->ensure_provider();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		global $wpdb;
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;
		$search   = sanitize_text_field( $input['search'] ?? '' );
		$status   = in_array( $input['status'] ?? 'all', array( 'enabled', 'disabled', 'all' ), true )
			? ( $input['status'] ?? 'all' )
			: 'all';

		$where  = array( '1=1' );
		$params = array();

		if ( 'all' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		if ( '' !== $search ) {
			$where[]  = 'url LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}",
			...$params
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, url, action_code, action_data, regex, status, hits, title, created, updated
			 FROM {$this->table} WHERE {$where_sql}
			 ORDER BY id DESC LIMIT %d OFFSET %d",
			...array_merge( $params, array( $per_page, $offset ) )
		), ARRAY_A );
		// phpcs:enable

		$redirects = array_map( fn( $row ) => $this->normalise_row( $row ), $rows ?: array() );

		$this->logger->log( 'tr-ai-toolkit-list-redirects', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'redirects'   => $redirects,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	// =========================================================================
	// Tool: get-redirect
	// =========================================================================

	private function register_get_redirect(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-redirect',
			array(
				'label'       => __( 'Get Redirect', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns details for a single redirect by ID.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'redirect_id' ),
					'properties' => array(
						'redirect_id' => array( 'type' => 'integer', 'description' => 'Redirect record ID.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_redirect( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_redirect( array $input ): array|\WP_Error {
		$check = $this->ensure_provider();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$redirect_id = absint( $input['redirect_id'] ?? 0 );
		if ( ! $redirect_id ) {
			return new \WP_Error( 'invalid_param', __( 'redirect_id must be a positive integer.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $redirect_id ), ARRAY_A );

		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'Redirect not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		$this->logger->log( 'tr-ai-toolkit-get-redirect', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return $this->normalise_row( $row );
	}

	// =========================================================================
	// Tool: create-redirect
	// =========================================================================

	private function register_create_redirect(): void {
		wp_register_ability(
			'tr-ai-toolkit-create-redirect',
			array(
				'label'       => __( 'Create Redirect', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a new URL redirect via the Redirection plugin. Validates for redirect loops and duplicate source URLs before saving.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'source_url', 'destination_url' ),
					'properties' => array(
						'source_url'      => array( 'type' => 'string',  'description' => 'Source URL path (relative, e.g. /old-page) or absolute URL.' ),
						'destination_url' => array( 'type' => 'string',  'description' => 'Destination URL path or absolute URL.' ),
						'redirect_type'   => array( 'type' => 'integer', 'description' => 'HTTP status code: 301, 302, 307, 308. Default: 301.' ),
						'title'           => array( 'type' => 'string',  'description' => 'Optional label for the redirect.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_create_redirect( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_create_redirect( array $input ): array|\WP_Error {
		$check = $this->ensure_provider();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$source      = $this->normalise_url( (string) ( $input['source_url'] ?? '' ) );
		$destination = $this->normalise_url( (string) ( $input['destination_url'] ?? '' ) );
		$type        = in_array( (int) ( $input['redirect_type'] ?? 301 ), array( 301, 302, 307, 308 ), true )
			? (int) $input['redirect_type']
			: 301;
		$title       = sanitize_text_field( $input['title'] ?? '' );

		if ( '' === $source || '' === $destination ) {
			return new \WP_Error( 'invalid_param', __( 'source_url and destination_url are required.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}
		if ( $source === $destination ) {
			return new \WP_Error( 'redirect_loop', __( 'Source and destination URLs are identical — this would cause an immediate redirect loop.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		// Duplicate source check.
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table} WHERE url = %s AND status = 'enabled' LIMIT 1", $source ) );
		if ( $existing_id ) {
			return new \WP_Error(
				'duplicate_source',
				sprintf( __( 'An enabled redirect already exists for this source URL (ID %d). Update or disable it first.', 'tr-wordpress-ai-toolkit' ), (int) $existing_id ),
				array( 'status' => 409 )
			);
		}

		// Loop detection: check if destination is itself a redirect source.
		$loop = $this->detect_chain_loop( $source, $destination );
		if ( ! empty( $loop ) ) {
			return new \WP_Error(
				'redirect_loop',
				sprintf( __( 'Creating this redirect would form a loop: %s', 'tr-wordpress-ai-toolkit' ), implode( ' → ', $loop ) ),
				array( 'status' => 400 )
			);
		}

		// Use Red_Item::create() if available; otherwise insert directly.
		if ( class_exists( 'Red_Item' ) ) {
			$item = \Red_Item::create( array(
				'url'         => $source,
				'action_data' => array( 'url' => $destination ),
				'action_type' => 'url',
				'action_code' => $type,
				'match_type'  => 'url',
				'group_id'    => $this->get_default_group_id(),
				'title'       => $title,
			) );

			if ( is_wp_error( $item ) ) {
				$this->logger->log( 'tr-ai-toolkit-create-redirect', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
				return $item;
			}

			$new_id = is_object( $item ) && isset( $item->id ) ? (int) $item->id : 0;
		} else {
			$wpdb->insert(
				$this->table, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				array(
					'url'         => $source,
					'action_type' => 'url',
					'action_code' => $type,
					'action_data' => wp_json_encode( array( 'url' => $destination ) ),
					'regex'       => 0,
					'group_id'    => $this->get_default_group_id(),
					'status'      => 'enabled',
					'hits'        => 0,
					'title'       => $title,
					'created'     => current_time( 'mysql' ),
					'updated'     => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
			);
			$new_id = (int) $wpdb->insert_id;
		}

		$this->logger->log( 'tr-ai-toolkit-create-redirect', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'created'         => true,
			'redirect_id'     => $new_id,
			'source_url'      => $source,
			'destination_url' => $destination,
			'redirect_type'   => $type,
		);
	}

	// =========================================================================
	// Tool: update-redirect
	// =========================================================================

	private function register_update_redirect(): void {
		wp_register_ability(
			'tr-ai-toolkit-update-redirect',
			array(
				'label'       => __( 'Update Redirect', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates an existing redirect. Only supplied fields are changed. Validates for loops and duplicate source URLs.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'redirect_id' ),
					'properties' => array(
						'redirect_id'     => array( 'type' => 'integer', 'description' => 'Redirect ID to update.' ),
						'source_url'      => array( 'type' => 'string' ),
						'destination_url' => array( 'type' => 'string' ),
						'redirect_type'   => array( 'type' => 'integer' ),
						'status'          => array( 'type' => 'string', 'description' => 'enabled or disabled.' ),
						'title'           => array( 'type' => 'string' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_update_redirect( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_update_redirect( array $input ): array|\WP_Error {
		$check = $this->ensure_provider();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$redirect_id = absint( $input['redirect_id'] ?? 0 );
		if ( ! $redirect_id ) {
			return new \WP_Error( 'invalid_param', __( 'redirect_id is required.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $redirect_id ), ARRAY_A );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'Redirect not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		$source      = isset( $input['source_url'] )
			? $this->normalise_url( (string) $input['source_url'] )
			: $existing['url'];
		$destination = isset( $input['destination_url'] )
			? $this->normalise_url( (string) $input['destination_url'] )
			: $this->extract_destination( $existing );

		if ( $source === $destination ) {
			return new \WP_Error( 'redirect_loop', __( 'Source and destination URLs are identical.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		if ( isset( $input['source_url'] ) && $source !== $existing['url'] ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$dup = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table} WHERE url = %s AND status = 'enabled' AND id != %d LIMIT 1", $source, $redirect_id ) );
			if ( $dup ) {
				return new \WP_Error( 'duplicate_source', __( 'Another enabled redirect already uses this source URL.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 409 ) );
			}
		}

		$update_data   = array( 'url' => $source, 'action_data' => wp_json_encode( array( 'url' => $destination ) ), 'updated' => current_time( 'mysql' ) );
		$update_format = array( '%s', '%s', '%s' );

		if ( isset( $input['redirect_type'] ) && in_array( (int) $input['redirect_type'], array( 301, 302, 307, 308 ), true ) ) {
			$update_data['action_code']  = (int) $input['redirect_type'];
			$update_format[]             = '%d';
		}
		if ( isset( $input['status'] ) && in_array( $input['status'], array( 'enabled', 'disabled' ), true ) ) {
			$update_data['status']  = $input['status'];
			$update_format[]        = '%s';
		}
		if ( isset( $input['title'] ) ) {
			$update_data['title'] = sanitize_text_field( $input['title'] );
			$update_format[]      = '%s';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->update( $this->table, $update_data, array( 'id' => $redirect_id ), $update_format, array( '%d' ) );

		$this->logger->log( 'tr-ai-toolkit-update-redirect', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'updated'         => true,
			'redirect_id'     => $redirect_id,
			'source_url'      => $source,
			'destination_url' => $destination,
		);
	}

	// =========================================================================
	// Tool: delete-redirect
	// =========================================================================

	private function register_delete_redirect(): void {
		wp_register_ability(
			'tr-ai-toolkit-delete-redirect',
			array(
				'label'       => __( 'Delete Redirect', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Permanently deletes a redirect by ID. Requires confirm: true.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'redirect_id', 'confirm' ),
					'properties' => array(
						'redirect_id' => array( 'type' => 'integer', 'description' => 'Redirect ID to delete.' ),
						'confirm'     => array( 'type' => 'boolean', 'description' => 'Must be true to execute deletion.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_delete_redirect( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_delete_redirect( array $input ): array|\WP_Error {
		$check = $this->ensure_provider();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to permanently delete this redirect.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$redirect_id = absint( $input['redirect_id'] ?? 0 );
		if ( ! $redirect_id ) {
			return new \WP_Error( 'invalid_param', __( 'redirect_id is required.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, url FROM {$this->table} WHERE id = %d", $redirect_id ), ARRAY_A );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'Redirect not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->delete( $this->table, array( 'id' => $redirect_id ), array( '%d' ) );

		$this->logger->log( 'tr-ai-toolkit-delete-redirect', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'deleted'     => true,
			'redirect_id' => $redirect_id,
			'source_url'  => $existing['url'],
		);
	}

	// =========================================================================
	// Tool: check-redirect-loops
	// =========================================================================

	private function register_check_redirect_loops(): void {
		wp_register_ability(
			'tr-ai-toolkit-check-redirect-loops',
			array(
				'label'       => __( 'Check Redirect Loops', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Scans all enabled redirects for loops and chains. Optionally checks a specific source URL.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'source_url' => array( 'type' => 'string', 'description' => 'Check only this source URL. Omit to scan all.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_check_redirect_loops( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_check_redirect_loops( array $input ): array|\WP_Error {
		$check = $this->ensure_provider();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$all_rows = $wpdb->get_results( "SELECT url, action_data FROM {$this->table} WHERE status = 'enabled'", ARRAY_A );

		// Build source → destination map.
		$map = array();
		foreach ( $all_rows as $row ) {
			$dest = $this->extract_destination( $row );
			if ( '' !== $dest ) {
				$map[ $row['url'] ] = $dest;
			}
		}

		$specific_source = isset( $input['source_url'] ) ? $this->normalise_url( (string) $input['source_url'] ) : '';
		$sources_to_check = $specific_source !== '' ? array( $specific_source ) : array_keys( $map );

		$loops  = array();
		$chains = array();

		foreach ( $sources_to_check as $start ) {
			$visited = array();
			$path    = array( $start );
			$current = $start;

			while ( isset( $map[ $current ] ) ) {
				$next = $map[ $current ];
				if ( in_array( $next, $visited, true ) || $next === $start ) {
					$path[]  = $next;
					$loops[] = array(
						'type'  => 'loop',
						'chain' => $path,
					);
					break;
				}
				$visited[] = $current;
				$path[]    = $next;
				$current   = $next;
			}

			if ( count( $path ) > 2 && ! in_array( $start, $loops, true ) ) {
				$chains[] = array(
					'type'  => 'chain',
					'chain' => $path,
					'hops'  => count( $path ) - 1,
				);
			}
		}

		$this->logger->log( 'tr-ai-toolkit-check-redirect-loops', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'loops_found'      => count( $loops ),
			'chains_found'     => count( $chains ),
			'loops'            => $loops,
			'chains'           => $chains,
			'total_redirects'  => count( $map ),
		);
	}

	// =========================================================================
	// Module interface
	// =========================================================================

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-list-redirects',
			'tr-ai-toolkit-get-redirect',
			'tr-ai-toolkit-create-redirect',
			'tr-ai-toolkit-update-redirect',
			'tr-ai-toolkit-delete-redirect',
			'tr-ai-toolkit-check-redirect-loops',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-list-redirects'       => $this->execute_list_redirects( $args ),
			'tr-ai-toolkit-get-redirect'         => $this->execute_get_redirect( $args ),
			'tr-ai-toolkit-create-redirect'      => $this->execute_create_redirect( $args ),
			'tr-ai-toolkit-update-redirect'      => $this->execute_update_redirect( $args ),
			'tr-ai-toolkit-delete-redirect'      => $this->execute_delete_redirect( $args ),
			'tr-ai-toolkit-check-redirect-loops' => $this->execute_check_redirect_loops( $args ),
			default                              => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-list-redirects',
				'description' => 'Returns URL redirects from the Redirection plugin with pagination and search. Returns error if Redirection is not installed.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
						'search'   => array( 'type' => 'string' ),
						'status'   => array( 'type' => 'string', 'enum' => array( 'enabled', 'disabled', 'all' ) ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-redirect',
				'description' => 'Returns full details of a single redirect by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'redirect_id' ),
					'properties' => array( 'redirect_id' => array( 'type' => 'integer' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-create-redirect',
				'description' => 'Creates a URL redirect. Validates for loops and duplicates. Requires admin capability.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'source_url', 'destination_url' ),
					'properties' => array(
						'source_url'      => array( 'type' => 'string' ),
						'destination_url' => array( 'type' => 'string' ),
						'redirect_type'   => array( 'type' => 'integer', 'enum' => array( 301, 302, 307, 308 ) ),
						'title'           => array( 'type' => 'string' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-update-redirect',
				'description' => 'Updates an existing redirect by ID. Only supplied fields are changed.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'redirect_id' ),
					'properties' => array(
						'redirect_id'     => array( 'type' => 'integer' ),
						'source_url'      => array( 'type' => 'string' ),
						'destination_url' => array( 'type' => 'string' ),
						'redirect_type'   => array( 'type' => 'integer' ),
						'status'          => array( 'type' => 'string', 'enum' => array( 'enabled', 'disabled' ) ),
						'title'           => array( 'type' => 'string' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-delete-redirect',
				'description' => 'Permanently deletes a redirect. Requires confirm: true.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'redirect_id', 'confirm' ),
					'properties' => array(
						'redirect_id' => array( 'type' => 'integer' ),
						'confirm'     => array( 'type' => 'boolean' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-check-redirect-loops',
				'description' => 'Scans enabled redirects for loops and multi-hop chains. Optionally check a specific source URL.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'source_url' => array( 'type' => 'string' ) ),
				),
			),
		);
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Checks whether a supported redirect provider is available.
	 * Returns WP_Error if not installed.
	 */
	private function ensure_provider(): bool|\WP_Error {
		global $wpdb;

		if ( null === $this->table ) {
			$this->table = $wpdb->prefix . 'redirection_items';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->table}'" );
		if ( $exists !== $this->table ) {
			return new \WP_Error(
				'provider_not_installed',
				__( 'The Redirection plugin is not installed or its database tables are missing. Install the free "Redirection" plugin by John Godley to use redirect management tools.', 'tr-wordpress-ai-toolkit' ),
				array( 'status' => 503 )
			);
		}

		return true;
	}

	/**
	 * Returns the first enabled group ID from the Redirection plugin,
	 * falling back to 1 if the groups table is unavailable.
	 */
	private function get_default_group_id(): int {
		global $wpdb;
		$groups_table = $wpdb->prefix . 'redirection_groups';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id = $wpdb->get_var( "SELECT id FROM {$groups_table} WHERE status = 'enabled' ORDER BY id ASC LIMIT 1" );
		return $id ? (int) $id : 1;
	}

	/**
	 * Normalises a URL for storage: strips the scheme/host for local paths,
	 * keeps absolute for external destinations.
	 */
	private function normalise_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		$site   = wp_parse_url( home_url() );

		// Same host → store as path only.
		if ( isset( $parsed['host'], $site['host'] ) && $parsed['host'] === $site['host'] ) {
			return $parsed['path'] ?? '/';
		}

		// No host → already a path.
		if ( ! isset( $parsed['host'] ) ) {
			return $parsed['path'] ?? $url;
		}

		return esc_url_raw( $url );
	}

	/**
	 * Extracts the destination URL from an action_data column value,
	 * handling both JSON-encoded (new Redirection) and plain string (old) formats.
	 *
	 * @param array<string, mixed> $row DB row.
	 */
	private function extract_destination( array $row ): string {
		$action_data = $row['action_data'] ?? '';
		if ( '' === $action_data ) {
			return '';
		}
		$decoded = json_decode( (string) $action_data, true );
		if ( is_array( $decoded ) && isset( $decoded['url'] ) ) {
			return (string) $decoded['url'];
		}
		// Plain URL string (older Redirection versions).
		return (string) $action_data;
	}

	/**
	 * Normalises a DB row into a consistent redirect record.
	 *
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function normalise_row( array $row ): array {
		return array(
			'id'              => (int) $row['id'],
			'source_url'      => $row['url'] ?? '',
			'destination_url' => $this->extract_destination( $row ),
			'redirect_type'   => (int) ( $row['action_code'] ?? 301 ),
			'status'          => $row['status'] ?? 'enabled',
			'hits'            => (int) ( $row['hits'] ?? 0 ),
			'title'           => $row['title'] ?? '',
			'created'         => $row['created'] ?? '',
			'updated'         => $row['updated'] ?? '',
		);
	}

	/**
	 * Traverses the redirect map from $source to detect if adding
	 * source → $new_destination would create a loop.
	 *
	 * Returns the loop chain array if a loop is detected, empty array otherwise.
	 *
	 * @return string[]
	 */
	private function detect_chain_loop( string $source, string $new_destination ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$all_rows = $wpdb->get_results( "SELECT url, action_data FROM {$this->table} WHERE status = 'enabled'", ARRAY_A );

		$map = array( $source => $new_destination );
		foreach ( $all_rows as $row ) {
			$dest = $this->extract_destination( $row );
			if ( '' !== $dest ) {
				$map[ $row['url'] ] = $dest;
			}
		}

		$path    = array( $source );
		$current = $new_destination;
		$visited = array( $source );
		$limit   = 20; // prevent infinite traversal

		while ( isset( $map[ $current ] ) && $limit-- > 0 ) {
			if ( in_array( $current, $visited, true ) ) {
				$path[] = $current;
				return $path;
			}
			$visited[] = $current;
			$path[]    = $current;
			$current   = $map[ $current ];
		}

		return array();
	}
}
