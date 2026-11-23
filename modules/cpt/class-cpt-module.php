<?php
/**
 * Custom Post Types (CPT) module abilities.
 *
 * Provides tools to discover and manage any public custom post type registered
 * on the site — portfolio items, testimonials, events, products (non-WC), etc.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Custom Post Types abilities for TR WordPress AI Toolkit.
 */
class TR_AI_Toolkit_CPT_Module {

	/** Core built-in post types excluded from CPT tools. */
	private const EXCLUDED_TYPES = array(
		'post', 'page', 'attachment', 'revision',
		'nav_menu_item', 'custom_css', 'customize_changeset',
		'oembed_cache', 'user_request', 'wp_block',
		'wp_template', 'wp_template_part', 'wp_global_styles',
		'wp_navigation', 'wp_font_family', 'wp_font_face',
		'shop_order', 'shop_coupon', 'product', 'product_variation',
	);

	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	public function __construct(
		TR_AI_Toolkit_Permissions $permissions,
		TR_AI_Toolkit_Logger $logger
	) {
		$this->permissions = $permissions;
		$this->logger      = $logger;
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		$this->register_list_post_types();
		$this->register_list_cpt_posts();
		$this->register_get_cpt_post();
		$this->register_create_cpt_post();
		$this->register_update_cpt_post();
	}

	// -------------------------------------------------------------------------
	// List post types
	// -------------------------------------------------------------------------

	private function register_list_post_types(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-post-types',
			array(
				'label'       => __( 'List Custom Post Types', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all registered custom post types on this site with their labels and capabilities.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array( 'type' => 'object', 'properties' => array() ),
				'permission_callback' => function () {
					return current_user_can( 'read' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_list_post_types( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_list_post_types( array $input ): array {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		$result = array();
		foreach ( $post_types as $slug => $type ) {
			if ( in_array( $slug, self::EXCLUDED_TYPES, true ) ) {
				continue;
			}
			$result[] = array(
				'slug'          => $slug,
				'name'          => $type->labels->name,
				'singular_name' => $type->labels->singular_name,
				'has_archive'   => (bool) $type->has_archive,
				'hierarchical'  => (bool) $type->hierarchical,
				'supports'      => get_all_post_type_supports( $slug ),
				'count'         => wp_count_posts( $slug )->publish ?? 0,
			);
		}

		$this->logger->log( 'tr-ai-toolkit-list-post-types', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'post_types' => $result,
			'count'      => count( $result ),
		);
	}

	// -------------------------------------------------------------------------
	// List CPT posts
	// -------------------------------------------------------------------------

	private function register_list_cpt_posts(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-cpt-posts',
			array(
				'label'       => __( 'List Custom Post Type Posts', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a paginated list of posts for any custom post type.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'post_type' ),
					'properties' => array(
						'post_type' => array( 'type' => 'string', 'description' => 'The custom post type slug (e.g. "portfolio", "testimonial").' ),
						'status'    => array( 'type' => 'string', 'description' => 'Post status. Default: publish.' ),
						'per_page'  => array( 'type' => 'integer', 'description' => 'Results per page (1-100). Default: 20.' ),
						'page'      => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
						'search'    => array( 'type' => 'string', 'description' => 'Search term.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'read' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_list_cpt_posts( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_list_cpt_posts( array $input ): array|\WP_Error {
		$post_type = sanitize_key( $input['post_type'] ?? '' );

		if ( ! $this->is_valid_cpt( $post_type ) ) {
			return new \WP_Error( 'invalid_post_type', "'{$post_type}' is not a valid custom post type.", array( 'status' => 400 ) );
		}

		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$status   = sanitize_text_field( $input['status'] ?? 'publish' );

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $input['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $input['search'] );
		}

		$query = new \WP_Query( $query_args );

		$this->logger->log( 'tr-ai-toolkit-list-cpt-posts', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'post_type' => $post_type,
			'posts'     => array_map( array( $this, 'format_cpt_post' ), $query->posts ),
			'total'     => $query->found_posts,
			'pages'     => $query->max_num_pages,
			'page'      => $page,
			'per_page'  => $per_page,
		);
	}

	// -------------------------------------------------------------------------
	// Get single CPT post
	// -------------------------------------------------------------------------

	private function register_get_cpt_post(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-cpt-post',
			array(
				'label'       => __( 'Get Custom Post Type Post', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns full details for a single custom post type post including custom fields.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post ID.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'read' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_get_cpt_post( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_get_cpt_post( array $input ): array|\WP_Error {
		$post_id  = (int) ( $input['post_id'] ?? 0 );
		$post     = get_post( $post_id );

		if ( ! $post || in_array( $post->post_type, array( 'post', 'page', 'attachment' ), true ) ) {
			return new \WP_Error( 'not_found', 'CPT post not found.', array( 'status' => 404 ) );
		}

		if ( ! $this->is_valid_cpt( $post->post_type ) ) {
			return new \WP_Error( 'not_found', 'Post is not a custom post type.', array( 'status' => 404 ) );
		}

		$meta       = get_post_meta( $post_id );
		$clean_meta = array();
		foreach ( $meta as $key => $values ) {
			if ( str_starts_with( $key, '_' ) ) {
				continue;
			}
			$clean_meta[ $key ] = count( $values ) === 1 ? $values[0] : $values;
		}

		$this->logger->log( 'tr-ai-toolkit-get-cpt-post', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array_merge( $this->format_cpt_post( $post ), array(
			'content'       => wp_strip_all_tags( $post->post_content ),
			'content_html'  => $post->post_content,
			'excerpt'       => $post->post_excerpt,
			'custom_fields' => $clean_meta,
			'featured_image' => get_the_post_thumbnail_url( $post_id, 'full' ) ?: null,
			'permalink'      => get_permalink( $post_id ),
		) );
	}

	// -------------------------------------------------------------------------
	// Create CPT post
	// -------------------------------------------------------------------------

	private function register_create_cpt_post(): void {
		wp_register_ability(
			'tr-ai-toolkit-create-cpt-post',
			array(
				'label'       => __( 'Create Custom Post Type Post', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a new post for any registered custom post type.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'post_type', 'title' ),
					'properties' => array(
						'post_type' => array( 'type' => 'string', 'description' => 'The custom post type slug.' ),
						'title'     => array( 'type' => 'string', 'description' => 'Post title.' ),
						'content'   => array( 'type' => 'string', 'description' => 'Post content.' ),
						'excerpt'   => array( 'type' => 'string', 'description' => 'Post excerpt.' ),
						'status'    => array( 'type' => 'string', 'description' => 'publish or draft. Default: draft.' ),
						'meta'      => array( 'type' => 'object', 'description' => 'Key/value pairs of custom fields to set.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_create_cpt_post( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_create_cpt_post( array $input ): array|\WP_Error {
		$post_type = sanitize_key( $input['post_type'] ?? '' );

		if ( ! $this->is_valid_cpt( $post_type ) ) {
			return new \WP_Error( 'invalid_post_type', "'{$post_type}' is not a valid custom post type.", array( 'status' => 400 ) );
		}

		$post_data = array(
			'post_type'    => $post_type,
			'post_title'   => sanitize_text_field( $input['title'] ?? '' ),
			'post_status'  => sanitize_text_field( $input['status'] ?? 'draft' ),
			'post_content' => ! empty( $input['content'] ) ? wp_kses_post( $input['content'] ) : '',
			'post_excerpt' => ! empty( $input['excerpt'] ) ? sanitize_textarea_field( $input['excerpt'] ) : '',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$this->logger->log( 'tr-ai-toolkit-create-cpt-post', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, $post_id->get_error_message() );
			return $post_id;
		}

		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $key => $value ) {
				$safe_key = sanitize_key( $key );
				if ( $safe_key && ! str_starts_with( $safe_key, '_' ) ) {
					update_post_meta( $post_id, $safe_key, $this->sanitize_meta_value( $value ) );
				}
			}
		}

		$this->logger->log( 'tr-ai-toolkit-create-cpt-post', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS, "Created {$post_type} ID {$post_id}" );
		return $this->execute_get_cpt_post( array( 'post_id' => $post_id ) );
	}

	// -------------------------------------------------------------------------
	// Update CPT post
	// -------------------------------------------------------------------------

	private function register_update_cpt_post(): void {
		wp_register_ability(
			'tr-ai-toolkit-update-cpt-post',
			array(
				'label'       => __( 'Update Custom Post Type Post', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates an existing custom post type post — title, content, status or custom fields.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post ID to update.' ),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'excerpt' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'meta'    => array( 'type' => 'object', 'description' => 'Custom fields to update.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_update_cpt_post( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_update_cpt_post( array $input ): array|\WP_Error {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || ! $this->is_valid_cpt( $post->post_type ) ) {
			return new \WP_Error( 'not_found', 'CPT post not found.', array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->logger->log( 'tr-ai-toolkit-update-cpt-post', $input, TR_AI_Toolkit_Logger::STATUS_DENIED );
			return new \WP_Error( 'forbidden', 'Cannot edit this post.', array( 'status' => 403 ) );
		}

		$update = array( 'ID' => $post_id );
		if ( isset( $input['title'] ) )   $update['post_title']   = sanitize_text_field( $input['title'] );
		if ( isset( $input['content'] ) ) $update['post_content'] = wp_kses_post( $input['content'] );
		if ( isset( $input['excerpt'] ) ) $update['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
		if ( isset( $input['status'] ) )  $update['post_status']  = sanitize_text_field( $input['status'] );

		$result = wp_update_post( $update, true );

		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'tr-ai-toolkit-update-cpt-post', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, $result->get_error_message() );
			return $result;
		}

		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $key => $value ) {
				$safe_key = sanitize_key( $key );
				if ( $safe_key && ! str_starts_with( $safe_key, '_' ) ) {
					update_post_meta( $post_id, $safe_key, $this->sanitize_meta_value( $value ) );
				}
			}
		}

		$this->logger->log( 'tr-ai-toolkit-update-cpt-post', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return $this->execute_get_cpt_post( array( 'post_id' => $post_id ) );
	}

	// -------------------------------------------------------------------------
	// Formatter
	// -------------------------------------------------------------------------

	private function format_cpt_post( \WP_Post $post ): array {
		return array(
			'id'         => $post->ID,
			'post_type'  => $post->post_type,
			'title'      => $post->post_title,
			'status'     => $post->post_status,
			'date'       => $post->post_date,
			'modified'   => $post->post_modified,
			'author_id'  => (int) $post->post_author,
			'slug'       => $post->post_name,
			'permalink'  => get_permalink( $post->ID ),
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function is_valid_cpt( string $post_type ): bool {
		if ( empty( $post_type ) || in_array( $post_type, self::EXCLUDED_TYPES, true ) ) {
			return false;
		}
		return post_type_exists( $post_type );
	}

	/**
	 * Sanitizes a custom field value without coercing arrays to strings.
	 *
	 * - Scalars: sanitize_text_field (strips HTML/whitespace).
	 * - Arrays: recursively sanitize each element.
	 * - Anything else: cast to string and sanitize.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function sanitize_meta_value( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'sanitize_meta_value' ), $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}

	// -------------------------------------------------------------------------
	// MCP interface
	// -------------------------------------------------------------------------

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-list-post-types',
			'tr-ai-toolkit-list-cpt-posts',
			'tr-ai-toolkit-get-cpt-post',
			'tr-ai-toolkit-create-cpt-post',
			'tr-ai-toolkit-update-cpt-post',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-list-post-types'  => $this->execute_list_post_types( $args ),
			'tr-ai-toolkit-list-cpt-posts'   => $this->execute_list_cpt_posts( $args ),
			'tr-ai-toolkit-get-cpt-post'     => $this->execute_get_cpt_post( $args ),
			'tr-ai-toolkit-create-cpt-post'  => $this->execute_create_cpt_post( $args ),
			'tr-ai-toolkit-update-cpt-post'  => $this->execute_update_cpt_post( $args ),
			default                          => new \WP_Error( 'not_found', "Tool '{$name}' not found.", array( 'status' => 404 ) ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-list-post-types',
				'description' => 'Lists all public custom post types registered on this WordPress site with labels, archive info and published count.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-list-cpt-posts',
				'description' => 'Returns paginated posts for any custom post type (portfolio, testimonials, events, etc.). Provide the post_type slug.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_type' ), 'properties' => array(
					'post_type' => array( 'type' => 'string' ),
					'status'    => array( 'type' => 'string' ),
					'per_page'  => array( 'type' => 'integer' ),
					'page'      => array( 'type' => 'integer' ),
					'search'    => array( 'type' => 'string' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-cpt-post',
				'description' => 'Returns full details for a single custom post type post including all custom fields (non-private meta).',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id' ), 'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-create-cpt-post',
				'description' => 'Creates a new post for any registered custom post type with optional custom fields. Defaults to draft.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_type', 'title' ), 'properties' => array(
					'post_type' => array( 'type' => 'string' ),
					'title'     => array( 'type' => 'string' ),
					'content'   => array( 'type' => 'string' ),
					'excerpt'   => array( 'type' => 'string' ),
					'status'    => array( 'type' => 'string' ),
					'meta'      => array( 'type' => 'object' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-update-cpt-post',
				'description' => 'Updates an existing custom post type post — title, content, status or custom fields.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id' ), 'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
					'title'   => array( 'type' => 'string' ),
					'content' => array( 'type' => 'string' ),
					'excerpt' => array( 'type' => 'string' ),
					'status'  => array( 'type' => 'string' ),
					'meta'    => array( 'type' => 'object' ),
				) ),
			),
		);
	}
}
