<?php
/**
 * Content module — posts and pages abilities.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers abilities for creating, reading, updating and trashing posts/pages.
 */
class TR_AI_Toolkit_Content_Module {

	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	public function __construct(
		TR_AI_Toolkit_Permissions $permissions,
		TR_AI_Toolkit_Logger $logger
	) {
		$this->permissions = $permissions;
		$this->logger      = $logger;
	}

	/**
	 * Registers all content abilities.
	 */
	public function register(): void {
		$this->register_list_posts();
		$this->register_get_post();
		$this->register_search_posts();
		$this->register_create_post();
		$this->register_update_post();
		$this->register_delete_post();
		$this->register_list_pages();
		$this->register_get_page();
		$this->register_create_page();
		$this->register_update_page();
		$this->register_list_categories();
		$this->register_create_category();
		$this->register_list_tags();
		$this->register_create_tag();
	}

	// -------------------------------------------------------------------------
	// Posts
	// -------------------------------------------------------------------------

	private function register_list_posts(): void {
		wp_register_ability(
			'tr-ai-toolkit/list-posts',
			array(
				'label'       => __( 'List Posts', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a paginated list of posts. Supports filtering by status, category ID, tag ID and author ID. Returns ID, title, status, date, author, excerpt, categories and tags for each post.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page'    => array( 'type' => 'integer', 'description' => 'Posts per page (1–100). Default 10.' ),
						'page'        => array( 'type' => 'integer', 'description' => 'Page number. Default 1.' ),
						'status'      => array( 'type' => 'string',  'description' => 'Post status: publish, draft, pending, private, any. Default publish.' ),
						'category_id' => array( 'type' => 'integer', 'description' => 'Filter by category ID.' ),
						'tag_id'      => array( 'type' => 'integer', 'description' => 'Filter by tag ID.' ),
						'author_id'   => array( 'type' => 'integer', 'description' => 'Filter by author user ID.' ),
						'orderby'     => array( 'type' => 'string',  'description' => 'Sort field: date, title, ID, modified. Default date.' ),
						'order'       => array( 'type' => 'string',  'description' => 'Sort direction: ASC or DESC. Default DESC.' ),
					),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'posts'       => array( 'type' => 'array' ),
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer' ),
						'per_page'    => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => function () {
					return $this->permissions->can_read() ?: new \WP_Error( 'forbidden', __( 'Insufficient permissions.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_list_posts( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function execute_list_posts( array $input ): array {
		$allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'any' );
		$allowed_orderby  = array( 'date', 'title', 'ID', 'modified' );

		$per_page  = min( 100, max( 1, (int) ( $input['per_page'] ?? 10 ) ) );
		$page      = max( 1, (int) ( $input['page'] ?? 1 ) );
		$status    = in_array( $input['status'] ?? 'publish', $allowed_statuses, true ) ? $input['status'] : 'publish';
		$orderby   = in_array( $input['orderby'] ?? 'date', $allowed_orderby, true ) ? $input['orderby'] : 'date';
		$order     = strtoupper( $input['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		// Non-public statuses require write capability.
		if ( 'publish' !== $status && ! $this->permissions->can_write() ) {
			return new \WP_Error( 'forbidden', __( 'Viewing non-published posts requires edit_posts capability.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$args = array(
			'post_type'      => 'post',
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		if ( ! empty( $input['category_id'] ) ) {
			$args['cat'] = absint( $input['category_id'] );
		}

		if ( ! empty( $input['tag_id'] ) ) {
			$args['tag_id'] = absint( $input['tag_id'] );
		}

		if ( ! empty( $input['author_id'] ) ) {
			$args['author'] = absint( $input['author_id'] );
		}

		$query = new \WP_Query( $args );
		$posts = array();

		foreach ( $query->posts as $post ) {
			$posts[] = $this->format_post( $post );
		}

		$result = array(
			'posts'       => $posts,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		);

		$this->logger->log( 'tr-ai-toolkit/list-posts', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return $result;
	}

	private function register_get_post(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-post',
			array(
				'label'       => __( 'Get Post', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns full details of a single post by ID, including content, metadata, categories, tags and featured image.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'The post ID.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_post( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_post( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return new \WP_Error( 'invalid_input', __( 'post_id is required and must be a positive integer.', 'tr-wordpress-ai-toolkit' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		if ( 'publish' !== $post->post_status && ! $this->permissions->can_write() ) {
			return new \WP_Error( 'forbidden', __( 'Insufficient permissions to view this post.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$result = $this->format_post( $post, true );
		$this->logger->log( 'tr-ai-toolkit/get-post', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return $result;
	}

	private function register_search_posts(): void {
		wp_register_ability(
			'tr-ai-toolkit/search-posts',
			array(
				'label'       => __( 'Search Posts', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Full-text search across post titles and content. Returns matching posts with relevance. Use list-posts for browsing all posts.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'query' ),
					'properties' => array(
						'query'    => array( 'type' => 'string', 'description' => 'Search term.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Results per page (1–50). Default 10.' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number.' ),
						'status'   => array( 'type' => 'string',  'description' => 'Post status. Default publish.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_search_posts( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_search_posts( array $input ): array|\WP_Error {
		$query = sanitize_text_field( $input['query'] ?? '' );

		if ( '' === $query ) {
			return new \WP_Error( 'invalid_input', __( 'query is required.', 'tr-wordpress-ai-toolkit' ) );
		}

		$per_page = min( 50, max( 1, (int) ( $input['per_page'] ?? 10 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$status   = 'publish';

		if ( ! empty( $input['status'] ) && $this->permissions->can_write() ) {
			$status = sanitize_key( $input['status'] );
		}

		$wp_query = new \WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => $status,
			's'              => $query,
			'posts_per_page' => $per_page,
			'paged'          => $page,
		) );

		$posts = array();
		foreach ( $wp_query->posts as $post ) {
			$posts[] = $this->format_post( $post );
		}

		$this->logger->log( 'tr-ai-toolkit/search-posts', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'posts'       => $posts,
			'total'       => (int) $wp_query->found_posts,
			'total_pages' => (int) $wp_query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	private function register_create_post(): void {
		wp_register_ability(
			'tr-ai-toolkit/create-post',
			array(
				'label'       => __( 'Create Post', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a new WordPress post. Returns the new post ID and URL. Status defaults to draft for safety.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'title' ),
					'properties' => array(
						'title'        => array( 'type' => 'string', 'description' => 'Post title.' ),
						'content'      => array( 'type' => 'string', 'description' => 'Post content (HTML or block markup).' ),
						'excerpt'      => array( 'type' => 'string', 'description' => 'Post excerpt.' ),
						'status'       => array( 'type' => 'string', 'description' => 'draft, pending, or publish. Default draft.' ),
						'category_ids' => array( 'type' => 'array',  'description' => 'Array of category IDs.' ),
						'tag_ids'      => array( 'type' => 'array',  'description' => 'Array of tag IDs.' ),
						'author_id'    => array( 'type' => 'integer','description' => 'Author user ID. Defaults to current user.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_write() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_create_post( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_create_post( array $input ): array|\WP_Error {
		$title = sanitize_text_field( $input['title'] ?? '' );

		if ( '' === $title ) {
			return new \WP_Error( 'invalid_input', __( 'title is required.', 'tr-wordpress-ai-toolkit' ) );
		}

		$allowed_statuses = array( 'draft', 'pending', 'publish' );
		$status           = in_array( $input['status'] ?? 'draft', $allowed_statuses, true ) ? $input['status'] : 'draft';

		// Only admins/editors can publish directly; contributors must draft.
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			$status = 'pending';
		}

		$post_data = array(
			'post_title'   => $title,
			'post_content' => wp_kses_post( $input['content'] ?? '' ),
			'post_excerpt' => sanitize_textarea_field( $input['excerpt'] ?? '' ),
			'post_status'  => $status,
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		);

		if ( ! empty( $input['author_id'] ) && current_user_can( 'edit_others_posts' ) ) {
			$post_data['post_author'] = absint( $input['author_id'] );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$this->logger->log( 'tr-ai-toolkit/create-post', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, $post_id->get_error_message() );
			return $post_id;
		}

		if ( ! empty( $input['category_ids'] ) && is_array( $input['category_ids'] ) ) {
			wp_set_post_categories( $post_id, array_map( 'absint', $input['category_ids'] ) );
		}

		if ( ! empty( $input['tag_ids'] ) && is_array( $input['tag_ids'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'absint', $input['tag_ids'] ), false );
		}

		$this->logger->log( 'tr-ai-toolkit/create-post', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS, "Created post ID {$post_id}" );

		return array(
			'post_id'    => $post_id,
			'url'        => get_permalink( $post_id ),
			'edit_url'   => get_edit_post_link( $post_id, 'raw' ),
			'status'     => get_post_status( $post_id ),
		);
	}

	private function register_update_post(): void {
		wp_register_ability(
			'tr-ai-toolkit/update-post',
			array(
				'label'       => __( 'Update Post', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates an existing post. Only the fields you supply will change. Returns updated post summary.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id'      => array( 'type' => 'integer', 'description' => 'ID of the post to update.' ),
						'title'        => array( 'type' => 'string',  'description' => 'New post title.' ),
						'content'      => array( 'type' => 'string',  'description' => 'New post content.' ),
						'excerpt'      => array( 'type' => 'string',  'description' => 'New excerpt.' ),
						'status'       => array( 'type' => 'string',  'description' => 'New status: draft, pending, publish, private.' ),
						'category_ids' => array( 'type' => 'array',   'description' => 'Replace category assignments.' ),
						'tag_ids'      => array( 'type' => 'array',   'description' => 'Replace tag assignments.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_write() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_update_post( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_update_post( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || 'post' !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->logger->log( 'tr-ai-toolkit/update-post', $input, TR_AI_Toolkit_Logger::STATUS_DENIED );
			return new \WP_Error( 'forbidden', __( 'You cannot edit this post.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$update = array( 'ID' => $post_id );

		if ( isset( $input['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$update['post_content'] = wp_kses_post( $input['content'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$update['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
		}
		if ( isset( $input['status'] ) ) {
			$allowed = array( 'draft', 'pending', 'publish', 'private' );
			if ( in_array( $input['status'], $allowed, true ) ) {
				if ( 'publish' === $input['status'] && ! current_user_can( 'publish_posts' ) ) {
					$update['post_status'] = 'pending';
				} else {
					$update['post_status'] = $input['status'];
				}
			}
		}

		$result = wp_update_post( $update, true );

		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'tr-ai-toolkit/update-post', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, $result->get_error_message() );
			return $result;
		}

		if ( isset( $input['category_ids'] ) && is_array( $input['category_ids'] ) ) {
			wp_set_post_categories( $post_id, array_map( 'absint', $input['category_ids'] ) );
		}
		if ( isset( $input['tag_ids'] ) && is_array( $input['tag_ids'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'absint', $input['tag_ids'] ), false );
		}

		$this->logger->log( 'tr-ai-toolkit/update-post', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'post_id' => $post_id,
			'status'  => get_post_status( $post_id ),
			'url'     => get_permalink( $post_id ),
		);
	}

	private function register_delete_post(): void {
		wp_register_ability(
			'tr-ai-toolkit/delete-post',
			array(
				'label'       => __( 'Delete Post (Trash)', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Moves a post to the trash. The post is NOT permanently deleted. Use WordPress admin to empty the trash. Returns confirmation.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'The post ID to trash.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_write() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_delete_post( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_delete_post( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || 'post' !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			$this->logger->log( 'tr-ai-toolkit/delete-post', $input, TR_AI_Toolkit_Logger::STATUS_DENIED );
			return new \WP_Error( 'forbidden', __( 'You cannot delete this post.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$trashed = wp_trash_post( $post_id );

		if ( ! $trashed ) {
			$this->logger->log( 'tr-ai-toolkit/delete-post', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, 'wp_trash_post returned false' );
			return new \WP_Error( 'trash_failed', __( 'Could not trash the post.', 'tr-wordpress-ai-toolkit' ) );
		}

		$this->logger->log( 'tr-ai-toolkit/delete-post', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS, "Trashed post ID {$post_id}" );

		return array(
			'post_id' => $post_id,
			'status'  => 'trash',
			'message' => __( 'Post moved to trash successfully.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	// -------------------------------------------------------------------------
	// Pages
	// -------------------------------------------------------------------------

	private function register_list_pages(): void {
		wp_register_ability(
			'tr-ai-toolkit/list-pages',
			array(
				'label'       => __( 'List Pages', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a paginated list of WordPress pages. Supports filtering by status. Returns ID, title, status, URL, parent ID and menu order.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page'  => array( 'type' => 'integer', 'description' => 'Pages per page (1–100). Default 10.' ),
						'page'      => array( 'type' => 'integer', 'description' => 'Page number. Default 1.' ),
						'status'    => array( 'type' => 'string',  'description' => 'publish, draft, private, any. Default publish.' ),
						'parent_id' => array( 'type' => 'integer', 'description' => 'Filter by parent page ID. Use 0 for top-level pages.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_pages( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_pages( array $input ): array {
		$allowed_statuses = array( 'publish', 'draft', 'private', 'any' );
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 10 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$status   = in_array( $input['status'] ?? 'publish', $allowed_statuses, true ) ? ( $input['status'] ?? 'publish' ) : 'publish';

		if ( 'publish' !== $status && ! $this->permissions->can_write() ) {
			$status = 'publish';
		}

		$args = array(
			'post_type'      => 'page',
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		if ( isset( $input['parent_id'] ) ) {
			$args['post_parent'] = absint( $input['parent_id'] );
		}

		$query = new \WP_Query( $args );
		$pages = array();

		foreach ( $query->posts as $p ) {
			$pages[] = array(
				'id'         => $p->ID,
				'title'      => $p->post_title,
				'status'     => $p->post_status,
				'url'        => get_permalink( $p->ID ),
				'parent_id'  => $p->post_parent,
				'menu_order' => $p->menu_order,
				'date'       => $p->post_date_gmt,
				'modified'   => $p->post_modified_gmt,
			);
		}

		$this->logger->log( 'tr-ai-toolkit/list-pages', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'pages'       => $pages,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	private function register_get_page(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-page',
			array(
				'label'       => __( 'Get Page', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns full details of a single page by ID, including content and metadata.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'page_id' ),
					'properties' => array(
						'page_id' => array( 'type' => 'integer', 'description' => 'The page ID.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_page( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_page( array $input ): array|\WP_Error {
		$page_id = absint( $input['page_id'] ?? 0 );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page || 'page' !== $page->post_type ) {
			return new \WP_Error( 'not_found', __( 'Page not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		if ( 'publish' !== $page->post_status && ! $this->permissions->can_write() ) {
			return new \WP_Error( 'forbidden', __( 'Insufficient permissions.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$this->logger->log( 'tr-ai-toolkit/get-page', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'id'             => $page->ID,
			'title'          => $page->post_title,
			'content'        => $page->post_content,
			'status'         => $page->post_status,
			'url'            => get_permalink( $page->ID ),
			'parent_id'      => $page->post_parent,
			'menu_order'     => $page->menu_order,
			'date'           => $page->post_date_gmt,
			'modified'       => $page->post_modified_gmt,
			'featured_image' => get_the_post_thumbnail_url( $page->ID, 'full' ) ?: null,
			'template'       => get_page_template_slug( $page->ID ),
		);
	}

	private function register_create_page(): void {
		wp_register_ability(
			'tr-ai-toolkit/create-page',
			array(
				'label'       => __( 'Create Page', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a new WordPress page. Status defaults to draft. Returns the new page ID and URL.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'title' ),
					'properties' => array(
						'title'     => array( 'type' => 'string',  'description' => 'Page title.' ),
						'content'   => array( 'type' => 'string',  'description' => 'Page content.' ),
						'status'    => array( 'type' => 'string',  'description' => 'draft or publish. Default draft.' ),
						'parent_id' => array( 'type' => 'integer', 'description' => 'Parent page ID.' ),
						'template'  => array( 'type' => 'string',  'description' => 'Page template filename.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_write() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_create_page( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_create_page( array $input ): array|\WP_Error {
		$title = sanitize_text_field( $input['title'] ?? '' );

		if ( '' === $title ) {
			return new \WP_Error( 'invalid_input', __( 'title is required.', 'tr-wordpress-ai-toolkit' ) );
		}

		$status = ( isset( $input['status'] ) && 'publish' === $input['status'] && current_user_can( 'publish_pages' ) )
			? 'publish'
			: 'draft';

		$page_data = array(
			'post_title'   => $title,
			'post_content' => wp_kses_post( $input['content'] ?? '' ),
			'post_status'  => $status,
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
			'post_parent'  => absint( $input['parent_id'] ?? 0 ),
		);

		$page_id = wp_insert_post( $page_data, true );

		if ( is_wp_error( $page_id ) ) {
			$this->logger->log( 'tr-ai-toolkit/create-page', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, $page_id->get_error_message() );
			return $page_id;
		}

		if ( ! empty( $input['template'] ) ) {
			update_post_meta( $page_id, '_wp_page_template', sanitize_file_name( $input['template'] ) );
		}

		$this->logger->log( 'tr-ai-toolkit/create-page', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS, "Created page ID {$page_id}" );

		return array(
			'page_id'  => $page_id,
			'url'      => get_permalink( $page_id ),
			'edit_url' => get_edit_post_link( $page_id, 'raw' ),
			'status'   => get_post_status( $page_id ),
		);
	}

	private function register_update_page(): void {
		wp_register_ability(
			'tr-ai-toolkit/update-page',
			array(
				'label'       => __( 'Update Page', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates an existing page. Only supplied fields are changed. Returns updated page summary.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'page_id' ),
					'properties' => array(
						'page_id'   => array( 'type' => 'integer', 'description' => 'ID of the page to update.' ),
						'title'     => array( 'type' => 'string',  'description' => 'New page title.' ),
						'content'   => array( 'type' => 'string',  'description' => 'New page content.' ),
						'status'    => array( 'type' => 'string',  'description' => 'New status: draft, publish, private.' ),
						'parent_id' => array( 'type' => 'integer', 'description' => 'New parent page ID.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_write() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_update_page( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_update_page( array $input ): array|\WP_Error {
		$page_id = absint( $input['page_id'] ?? 0 );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page || 'page' !== $page->post_type ) {
			return new \WP_Error( 'not_found', __( 'Page not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		if ( ! current_user_can( 'edit_page', $page_id ) ) {
			$this->logger->log( 'tr-ai-toolkit/update-page', $input, TR_AI_Toolkit_Logger::STATUS_DENIED );
			return new \WP_Error( 'forbidden', __( 'You cannot edit this page.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$update = array( 'ID' => $page_id );

		if ( isset( $input['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$update['post_content'] = wp_kses_post( $input['content'] );
		}
		if ( isset( $input['status'] ) ) {
			$allowed = array( 'draft', 'publish', 'private' );
			if ( in_array( $input['status'], $allowed, true ) ) {
				$update['post_status'] = $input['status'];
			}
		}
		if ( isset( $input['parent_id'] ) ) {
			$update['post_parent'] = absint( $input['parent_id'] );
		}

		$result = wp_update_post( $update, true );

		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'tr-ai-toolkit/update-page', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, $result->get_error_message() );
			return $result;
		}

		$this->logger->log( 'tr-ai-toolkit/update-page', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'page_id' => $page_id,
			'status'  => get_post_status( $page_id ),
			'url'     => get_permalink( $page_id ),
		);
	}

	// -------------------------------------------------------------------------
	// Categories and Tags
	// -------------------------------------------------------------------------

	private function register_list_categories(): void {
		wp_register_ability(
			'tr-ai-toolkit/list-categories',
			array(
				'label'       => __( 'List Categories', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all post categories with ID, name, slug, description and post count.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'hide_empty' => array( 'type' => 'boolean', 'description' => 'Exclude empty categories. Default false.' ),
						'search'     => array( 'type' => 'string',  'description' => 'Filter by name.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_categories( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_categories( array $input ): array {
		$args = array(
			'taxonomy'   => 'category',
			'hide_empty' => (bool) ( $input['hide_empty'] ?? false ),
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return array( 'categories' => array(), 'total' => 0 );
		}

		$categories = array();
		foreach ( $terms as $term ) {
			$categories[] = array(
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent_id'   => $term->parent,
				'post_count'  => $term->count,
			);
		}

		$this->logger->log( 'tr-ai-toolkit/list-categories', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array( 'categories' => $categories, 'total' => count( $categories ) );
	}

	private function register_create_category(): void {
		wp_register_ability(
			'tr-ai-toolkit/create-category',
			array(
				'label'       => __( 'Create Category', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a new post category. Returns the new category ID, name and slug.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name'        => array( 'type' => 'string',  'description' => 'Category name.' ),
						'slug'        => array( 'type' => 'string',  'description' => 'URL slug. Auto-generated from name if omitted.' ),
						'description' => array( 'type' => 'string',  'description' => 'Optional description.' ),
						'parent_id'   => array( 'type' => 'integer', 'description' => 'Parent category ID.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_write() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_create_category( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_create_category( array $input ): array|\WP_Error {
		$name = sanitize_text_field( $input['name'] ?? '' );

		if ( '' === $name ) {
			return new \WP_Error( 'invalid_input', __( 'name is required.', 'tr-wordpress-ai-toolkit' ) );
		}

		$args = array(
			'description' => sanitize_textarea_field( $input['description'] ?? '' ),
			'parent'      => absint( $input['parent_id'] ?? 0 ),
		);

		if ( ! empty( $input['slug'] ) ) {
			$args['slug'] = sanitize_title( $input['slug'] );
		}

		$result = wp_insert_term( $name, 'category', $args );

		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'tr-ai-toolkit/create-category', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, $result->get_error_message() );
			return $result;
		}

		$term = get_term( $result['term_id'], 'category' );
		$this->logger->log( 'tr-ai-toolkit/create-category', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'category_id' => $result['term_id'],
			'name'        => $term->name,
			'slug'        => $term->slug,
		);
	}

	private function register_list_tags(): void {
		wp_register_ability(
			'tr-ai-toolkit/list-tags',
			array(
				'label'       => __( 'List Tags', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all post tags with ID, name, slug and post count.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'hide_empty' => array( 'type' => 'boolean', 'description' => 'Exclude unused tags. Default false.' ),
						'search'     => array( 'type' => 'string',  'description' => 'Filter by name.' ),
						'per_page'   => array( 'type' => 'integer', 'description' => 'Max results (1–500). Default 100.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_tags( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_tags( array $input ): array {
		$args = array(
			'taxonomy'   => 'post_tag',
			'hide_empty' => (bool) ( $input['hide_empty'] ?? false ),
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => min( 500, max( 1, (int) ( $input['per_page'] ?? 100 ) ) ),
		);

		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return array( 'tags' => array(), 'total' => 0 );
		}

		$tags = array();
		foreach ( $terms as $term ) {
			$tags[] = array(
				'id'         => $term->term_id,
				'name'       => $term->name,
				'slug'       => $term->slug,
				'post_count' => $term->count,
			);
		}

		$this->logger->log( 'tr-ai-toolkit/list-tags', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array( 'tags' => $tags, 'total' => count( $tags ) );
	}

	private function register_create_tag(): void {
		wp_register_ability(
			'tr-ai-toolkit/create-tag',
			array(
				'label'       => __( 'Create Tag', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a new post tag. Returns the new tag ID, name and slug.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name'        => array( 'type' => 'string', 'description' => 'Tag name.' ),
						'slug'        => array( 'type' => 'string', 'description' => 'URL slug. Auto-generated if omitted.' ),
						'description' => array( 'type' => 'string', 'description' => 'Optional description.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_write() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_create_tag( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_create_tag( array $input ): array|\WP_Error {
		$name = sanitize_text_field( $input['name'] ?? '' );

		if ( '' === $name ) {
			return new \WP_Error( 'invalid_input', __( 'name is required.', 'tr-wordpress-ai-toolkit' ) );
		}

		$args = array(
			'description' => sanitize_textarea_field( $input['description'] ?? '' ),
		);

		if ( ! empty( $input['slug'] ) ) {
			$args['slug'] = sanitize_title( $input['slug'] );
		}

		$result = wp_insert_term( $name, 'post_tag', $args );

		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'tr-ai-toolkit/create-tag', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, $result->get_error_message() );
			return $result;
		}

		$term = get_term( $result['term_id'], 'post_tag' );
		$this->logger->log( 'tr-ai-toolkit/create-tag', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'tag_id' => $result['term_id'],
			'name'   => $term->name,
			'slug'   => $term->slug,
		);
	}

	// -------------------------------------------------------------------------
	// Shared formatter
	// -------------------------------------------------------------------------

	/**
	 * Converts a WP_Post into the standard response format.
	 *
	 * @param \WP_Post $post
	 * @param bool     $full Include content and full metadata.
	 * @return array<string, mixed>
	 */
	private function format_post( \WP_Post $post, bool $full = false ): array {
		$categories = get_the_category( $post->ID );
		$tags       = get_the_tags( $post->ID );

		$data = array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'status'   => $post->post_status,
			'url'      => get_permalink( $post->ID ),
			'date'     => $post->post_date_gmt,
			'modified' => $post->post_modified_gmt,
			'author'   => array(
				'id'   => (int) $post->post_author,
				'name' => get_the_author_meta( 'display_name', $post->post_author ),
			),
			'excerpt'    => wp_trim_excerpt( '', $post ),
			'categories' => array_map( fn( $t ) => array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ), $categories ),
			'tags'       => is_array( $tags ) ? array_map( fn( $t ) => array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ), $tags ) : array(),
			'featured_image' => get_the_post_thumbnail_url( $post->ID, 'full' ) ?: null,
			'comment_count'  => (int) $post->comment_count,
		);

		if ( $full ) {
			$data['content'] = $post->post_content;
			$data['slug']    = $post->post_name;
			$data['edit_url'] = get_edit_post_link( $post->ID, 'raw' );
		}

		return $data;
	}
}
