<?php
/**
 * Taxonomy management module.
 *
 * Provides MCP tools for listing, reading, updating, deleting, and merging
 * WordPress categories and tags. SEO metadata is read from the active SEO
 * plugin (Yoast, Rank Math, AIOSEO) where supported.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * MCP tools for category and tag management.
 */
class TR_AI_Toolkit_Taxonomy_Module {

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
		$this->register_list_categories();
		$this->register_get_category();
		$this->register_update_category();
		$this->register_delete_category();
		$this->register_merge_categories();
		$this->register_list_tags();
		$this->register_get_tag();
		$this->register_update_tag();
		$this->register_delete_tag();
		$this->register_merge_tags();
	}

	// =========================================================================
	// CATEGORIES
	// =========================================================================

	private function register_list_categories(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-categories',
			array(
				'label'       => __( 'List Categories', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns WordPress categories with post count, slug, description, parent, and URL.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page'   => array( 'type' => 'integer', 'description' => 'Max results (1–200). Default: 50.' ),
						'page'       => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
						'search'     => array( 'type' => 'string',  'description' => 'Filter by name.' ),
						'parent'     => array( 'type' => 'integer', 'description' => 'Filter by parent category ID. Use 0 for top-level.' ),
						'hide_empty' => array( 'type' => 'boolean', 'description' => 'Hide categories with no posts. Default: false.' ),
						'orderby'    => array( 'type' => 'string',  'description' => 'name, count, id. Default: name.' ),
						'order'      => array( 'type' => 'string',  'description' => 'ASC or DESC. Default: ASC.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_terms( $input, 'category' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function register_get_category(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-category',
			array(
				'label'       => __( 'Get Category', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns full details for a category by ID or slug, including SEO metadata from the active SEO plugin.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'category_id' => array( 'type' => 'integer', 'description' => 'Category ID.' ),
						'slug'        => array( 'type' => 'string',  'description' => 'Category slug. Used if category_id not supplied.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_term( $input, 'category' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function register_update_category(): void {
		wp_register_ability(
			'tr-ai-toolkit-update-category',
			array(
				'label'       => __( 'Update Category', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates a category name, slug, description, or parent. Requires manage_categories capability.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'category_id' ),
					'properties' => array(
						'category_id' => array( 'type' => 'integer', 'description' => 'Category ID to update.' ),
						'name'        => array( 'type' => 'string',  'description' => 'New category name. Omit to leave unchanged.' ),
						'slug'        => array( 'type' => 'string',  'description' => 'New slug. Omit to leave unchanged.' ),
						'description' => array( 'type' => 'string',  'description' => 'New description. Omit to leave unchanged.' ),
						'parent_id'   => array( 'type' => 'integer', 'description' => 'New parent category ID. Use 0 for top-level.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_manage_terms() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_update_term( $input, 'category' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function register_delete_category(): void {
		wp_register_ability(
			'tr-ai-toolkit-delete-category',
			array(
				'label'       => __( 'Delete Category', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Permanently deletes a category. Requires confirm: true. Posts can be reassigned to another category.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'category_id', 'confirm' ),
					'properties' => array(
						'category_id'  => array( 'type' => 'integer', 'description' => 'Category ID to delete.' ),
						'confirm'      => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
						'reassign_to'  => array( 'type' => 'integer', 'description' => 'Category ID to move posts to after deletion. Default: uncategorised.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_manage_terms() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_delete_term( $input, 'category' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function register_merge_categories(): void {
		wp_register_ability(
			'tr-ai-toolkit-merge-categories',
			array(
				'label'       => __( 'Merge Categories', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Moves all posts from a source category to a target category, then deletes the source. Requires confirm: true. The target category is never deleted.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'source_id', 'target_id', 'confirm' ),
					'properties' => array(
						'source_id' => array( 'type' => 'integer', 'description' => 'Category to merge FROM (will be deleted).' ),
						'target_id' => array( 'type' => 'integer', 'description' => 'Category to merge INTO (kept).' ),
						'confirm'   => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_manage_terms() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_merge_terms( $input, 'category' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	// =========================================================================
	// TAGS
	// =========================================================================

	private function register_list_tags(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-tags',
			array(
				'label'       => __( 'List Tags', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns WordPress post tags with post count, slug, description, and URL.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page'   => array( 'type' => 'integer', 'description' => 'Max results (1–200). Default: 50.' ),
						'page'       => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
						'search'     => array( 'type' => 'string',  'description' => 'Filter by name.' ),
						'hide_empty' => array( 'type' => 'boolean', 'description' => 'Hide tags with no posts. Default: false.' ),
						'orderby'    => array( 'type' => 'string',  'description' => 'name, count, id. Default: name.' ),
						'order'      => array( 'type' => 'string',  'description' => 'ASC or DESC. Default: ASC.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_terms( $input, 'post_tag' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function register_get_tag(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-tag',
			array(
				'label'       => __( 'Get Tag', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns full details for a tag by ID or slug, including SEO metadata.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'tag_id' => array( 'type' => 'integer', 'description' => 'Tag ID.' ),
						'slug'   => array( 'type' => 'string',  'description' => 'Tag slug. Used if tag_id not supplied.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_term( array( 'category_id' => $input['tag_id'] ?? null, 'slug' => $input['slug'] ?? null ), 'post_tag' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function register_update_tag(): void {
		wp_register_ability(
			'tr-ai-toolkit-update-tag',
			array(
				'label'       => __( 'Update Tag', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates a tag name, slug, or description. Requires manage_categories capability.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'tag_id' ),
					'properties' => array(
						'tag_id'      => array( 'type' => 'integer', 'description' => 'Tag ID.' ),
						'name'        => array( 'type' => 'string' ),
						'slug'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_manage_terms() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_update_term( array_merge( $input, array( 'category_id' => $input['tag_id'] ?? null ) ), 'post_tag' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function register_delete_tag(): void {
		wp_register_ability(
			'tr-ai-toolkit-delete-tag',
			array(
				'label'       => __( 'Delete Tag', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Permanently deletes a tag. Requires confirm: true.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'tag_id', 'confirm' ),
					'properties' => array(
						'tag_id'  => array( 'type' => 'integer' ),
						'confirm' => array( 'type' => 'boolean' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_manage_terms() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_delete_term( array_merge( $input, array( 'category_id' => $input['tag_id'] ?? null ) ), 'post_tag' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function register_merge_tags(): void {
		wp_register_ability(
			'tr-ai-toolkit-merge-tags',
			array(
				'label'       => __( 'Merge Tags', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Moves all posts from a source tag to a target tag, then deletes the source tag. Requires confirm: true.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'source_id', 'target_id', 'confirm' ),
					'properties' => array(
						'source_id' => array( 'type' => 'integer', 'description' => 'Tag to merge FROM (deleted after).' ),
						'target_id' => array( 'type' => 'integer', 'description' => 'Tag to merge INTO (kept).' ),
						'confirm'   => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_manage_terms() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_merge_terms( $input, 'post_tag' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	// =========================================================================
	// Shared executors
	// =========================================================================

	/** @param array<string, mixed> $input */
	private function execute_list_terms( array $input, string $taxonomy ): array|\WP_Error {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'invalid_taxonomy', sprintf( __( 'Taxonomy "%s" does not exist.', 'tr-wordpress-ai-toolkit' ), $taxonomy ), array( 'status' => 400 ) );
		}

		$per_page   = min( 200, max( 1, (int) ( $input['per_page'] ?? 50 ) ) );
		$page       = max( 1, (int) ( $input['page'] ?? 1 ) );
		$offset     = ( $page - 1 ) * $per_page;
		$valid_orderby = array( 'name', 'count', 'id', 'slug' );
		$orderby    = in_array( $input['orderby'] ?? 'name', $valid_orderby, true ) ? ( $input['orderby'] ?? 'name' ) : 'name';
		$order      = 'DESC' === strtoupper( $input['order'] ?? 'ASC' ) ? 'DESC' : 'ASC';
		$hide_empty = ! empty( $input['hide_empty'] );

		$args = array(
			'taxonomy'   => $taxonomy,
			'number'     => $per_page,
			'offset'     => $offset,
			'orderby'    => $orderby,
			'order'      => $order,
			'hide_empty' => $hide_empty,
		);

		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}
		if ( 'category' === $taxonomy && isset( $input['parent'] ) ) {
			$args['parent'] = absint( $input['parent'] );
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$total_args           = $args;
		$total_args['fields'] = 'count';
		unset( $total_args['number'], $total_args['offset'] );
		$total = (int) wp_count_terms( $taxonomy, $total_args );

		$ability = 'category' === $taxonomy ? 'tr-ai-toolkit-list-categories' : 'tr-ai-toolkit-list-tags';
		$this->logger->log( $ability, $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'terms'       => array_map( fn( $t ) => $this->format_term( $t, $taxonomy, false ), $terms ),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
			'taxonomy'    => $taxonomy,
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_term( array $input, string $taxonomy ): array|\WP_Error {
		$term = $this->resolve_term( $input, $taxonomy );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$ability = 'category' === $taxonomy ? 'tr-ai-toolkit-get-category' : 'tr-ai-toolkit-get-tag';
		$this->logger->log( $ability, $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return $this->format_term( $term, $taxonomy, true );
	}

	/** @param array<string, mixed> $input */
	private function execute_update_term( array $input, string $taxonomy ): array|\WP_Error {
		$term = $this->resolve_term( $input, $taxonomy );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to edit terms.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$update_args = array();
		if ( isset( $input['name'] ) ) {
			$update_args['name'] = sanitize_text_field( $input['name'] );
		}
		if ( isset( $input['slug'] ) ) {
			$update_args['slug'] = sanitize_title( $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$update_args['description'] = wp_kses_post( $input['description'] );
		}
		if ( 'category' === $taxonomy && isset( $input['parent_id'] ) ) {
			$update_args['parent'] = absint( $input['parent_id'] );
		}

		if ( empty( $update_args ) ) {
			return new \WP_Error( 'no_fields', __( 'Provide at least one field to update.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$result = wp_update_term( $term->term_id, $taxonomy, $update_args );
		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'tr-ai-toolkit-update-' . ( 'category' === $taxonomy ? 'category' : 'tag' ), $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return $result;
		}

		$updated_term = get_term( $result['term_id'], $taxonomy );
		$ability      = 'category' === $taxonomy ? 'tr-ai-toolkit-update-category' : 'tr-ai-toolkit-update-tag';
		$this->logger->log( $ability, $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'updated'  => true,
			'term_id'  => $result['term_id'],
			'term'     => $updated_term ? $this->format_term( $updated_term, $taxonomy, false ) : array(),
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_delete_term( array $input, string $taxonomy ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to permanently delete this term.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$term = $this->resolve_term( $input, $taxonomy );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to delete terms.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		// Prevent deleting the default WordPress category.
		if ( 'category' === $taxonomy && (int) get_option( 'default_category' ) === $term->term_id ) {
			return new \WP_Error( 'default_category', __( 'The default category cannot be deleted. Change the default category in Settings → Writing first.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$reassign_to = isset( $input['reassign_to'] ) ? absint( $input['reassign_to'] ) : null;
		$term_name   = $term->name;
		$term_id     = $term->term_id;
		$post_count  = $term->count;

		// Reassign posts if requested (categories).
		if ( $reassign_to && 'category' === $taxonomy ) {
			$object_ids = get_objects_in_term( $term_id, $taxonomy );
			if ( ! is_wp_error( $object_ids ) ) {
				foreach ( $object_ids as $object_id ) {
					wp_set_post_terms( (int) $object_id, array( $reassign_to ), $taxonomy, true );
				}
			}
		}

		$result = wp_delete_term( $term_id, $taxonomy );
		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'tr-ai-toolkit-delete-' . ( 'category' === $taxonomy ? 'category' : 'tag' ), $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return $result;
		}

		$ability = 'category' === $taxonomy ? 'tr-ai-toolkit-delete-category' : 'tr-ai-toolkit-delete-tag';
		$this->logger->log( $ability, $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'deleted'       => true,
			'term_id'       => $term_id,
			'name'          => $term_name,
			'posts_affected' => $post_count,
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_merge_terms( array $input, string $taxonomy ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to merge terms.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$source_id = absint( $input['source_id'] ?? 0 );
		$target_id = absint( $input['target_id'] ?? 0 );

		if ( ! $source_id || ! $target_id ) {
			return new \WP_Error( 'invalid_param', __( 'source_id and target_id are required.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}
		if ( $source_id === $target_id ) {
			return new \WP_Error( 'invalid_param', __( 'source_id and target_id must be different.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$source = get_term( $source_id, $taxonomy );
		$target = get_term( $target_id, $taxonomy );

		if ( ! $source || is_wp_error( $source ) ) {
			return new \WP_Error( 'not_found', __( 'Source term not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}
		if ( ! $target || is_wp_error( $target ) ) {
			return new \WP_Error( 'not_found', __( 'Target term not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to merge terms.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$object_ids = get_objects_in_term( $source_id, $taxonomy );
		if ( is_wp_error( $object_ids ) ) {
			return $object_ids;
		}

		$moved = 0;
		foreach ( $object_ids as $object_id ) {
			wp_remove_object_terms( (int) $object_id, $source_id, $taxonomy );
			wp_set_post_terms( (int) $object_id, array( $target_id ), $taxonomy, true );
			++$moved;
		}

		wp_delete_term( $source_id, $taxonomy );

		$ability = 'category' === $taxonomy ? 'tr-ai-toolkit-merge-categories' : 'tr-ai-toolkit-merge-tags';
		$this->logger->log( $ability, $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'merged'            => true,
			'source_id'         => $source_id,
			'source_name'       => $source->name,
			'target_id'         => $target_id,
			'target_name'       => $target->name,
			'posts_reassigned'  => $moved,
			'source_deleted'    => true,
		);
	}

	// =========================================================================
	// Module interface
	// =========================================================================

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-list-categories',
			'tr-ai-toolkit-get-category',
			'tr-ai-toolkit-update-category',
			'tr-ai-toolkit-delete-category',
			'tr-ai-toolkit-merge-categories',
			'tr-ai-toolkit-list-tags',
			'tr-ai-toolkit-get-tag',
			'tr-ai-toolkit-update-tag',
			'tr-ai-toolkit-delete-tag',
			'tr-ai-toolkit-merge-tags',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-list-categories'  => $this->execute_list_terms( $args, 'category' ),
			'tr-ai-toolkit-get-category'     => $this->execute_get_term( $args, 'category' ),
			'tr-ai-toolkit-update-category'  => $this->execute_update_term( $args, 'category' ),
			'tr-ai-toolkit-delete-category'  => $this->execute_delete_term( $args, 'category' ),
			'tr-ai-toolkit-merge-categories' => $this->execute_merge_terms( $args, 'category' ),
			'tr-ai-toolkit-list-tags'        => $this->execute_list_terms( $args, 'post_tag' ),
			'tr-ai-toolkit-get-tag'          => $this->execute_get_term( array( 'category_id' => $args['tag_id'] ?? null, 'slug' => $args['slug'] ?? null ), 'post_tag' ),
			'tr-ai-toolkit-update-tag'       => $this->execute_update_term( array_merge( $args, array( 'category_id' => $args['tag_id'] ?? null ) ), 'post_tag' ),
			'tr-ai-toolkit-delete-tag'       => $this->execute_delete_term( array_merge( $args, array( 'category_id' => $args['tag_id'] ?? null ) ), 'post_tag' ),
			'tr-ai-toolkit-merge-tags'       => $this->execute_merge_terms( $args, 'post_tag' ),
			default                          => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-list-categories',
				'description' => 'Returns WordPress categories with post count, slug, description, parent, URL. Supports pagination and search.',
				'inputSchema' => array(
					'type' => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer' ), 'page' => array( 'type' => 'integer' ),
						'search'   => array( 'type' => 'string' ),  'parent' => array( 'type' => 'integer' ),
						'hide_empty' => array( 'type' => 'boolean' ), 'orderby' => array( 'type' => 'string' ), 'order' => array( 'type' => 'string' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-category',
				'description' => 'Returns full details for a category by ID or slug, including SEO metadata.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'category_id' => array( 'type' => 'integer' ), 'slug' => array( 'type' => 'string' ) ) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-update-category',
				'description' => 'Updates a category (name, slug, description, parent). Requires manage_categories.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'category_id' ),
					'properties' => array(
						'category_id' => array( 'type' => 'integer' ), 'name' => array( 'type' => 'string' ),
						'slug'        => array( 'type' => 'string' ),  'description' => array( 'type' => 'string' ),
						'parent_id'   => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-delete-category',
				'description' => 'Permanently deletes a category. Requires confirm: true. The default category cannot be deleted.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'category_id', 'confirm' ),
					'properties' => array( 'category_id' => array( 'type' => 'integer' ), 'confirm' => array( 'type' => 'boolean' ), 'reassign_to' => array( 'type' => 'integer' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-merge-categories',
				'description' => 'Moves all posts from source category to target, then deletes source. Requires confirm: true.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'source_id', 'target_id', 'confirm' ),
					'properties' => array( 'source_id' => array( 'type' => 'integer' ), 'target_id' => array( 'type' => 'integer' ), 'confirm' => array( 'type' => 'boolean' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-list-tags',
				'description' => 'Returns WordPress post tags with post count, slug, description, and URL.',
				'inputSchema' => array(
					'type' => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer' ), 'page' => array( 'type' => 'integer' ),
						'search'   => array( 'type' => 'string' ),  'hide_empty' => array( 'type' => 'boolean' ),
						'orderby'  => array( 'type' => 'string' ),  'order' => array( 'type' => 'string' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-tag',
				'description' => 'Returns full details for a tag by ID or slug, including SEO metadata.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'tag_id' => array( 'type' => 'integer' ), 'slug' => array( 'type' => 'string' ) ) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-update-tag',
				'description' => 'Updates a tag (name, slug, description). Requires manage_categories.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'tag_id' ),
					'properties' => array( 'tag_id' => array( 'type' => 'integer' ), 'name' => array( 'type' => 'string' ), 'slug' => array( 'type' => 'string' ), 'description' => array( 'type' => 'string' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-delete-tag',
				'description' => 'Permanently deletes a tag. Requires confirm: true.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'tag_id', 'confirm' ),
					'properties' => array( 'tag_id' => array( 'type' => 'integer' ), 'confirm' => array( 'type' => 'boolean' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-merge-tags',
				'description' => 'Moves all posts from source tag to target, then deletes source. Requires confirm: true.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'source_id', 'target_id', 'confirm' ),
					'properties' => array( 'source_id' => array( 'type' => 'integer' ), 'target_id' => array( 'type' => 'integer' ), 'confirm' => array( 'type' => 'boolean' ) ),
				),
			),
		);
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Resolves a term from input that may contain category_id or slug.
	 */
	private function resolve_term( array $input, string $taxonomy ): \WP_Term|\WP_Error {
		$term_id = absint( $input['category_id'] ?? 0 );
		$slug    = sanitize_title( $input['slug'] ?? '' );

		if ( $term_id ) {
			$term = get_term( $term_id, $taxonomy );
		} elseif ( '' !== $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
		} else {
			return new \WP_Error( 'invalid_param', __( 'Provide category_id or slug.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error( 'not_found', __( 'Term not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		return $term;
	}

	/**
	 * Formats a WP_Term into a consistent response array.
	 *
	 * @param \WP_Term $term
	 * @param string   $taxonomy
	 * @param bool     $include_seo Whether to include SEO metadata.
	 * @return array<string, mixed>
	 */
	private function format_term( \WP_Term $term, string $taxonomy, bool $include_seo ): array {
		$data = array(
			'id'          => $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'count'       => $term->count,
			'url'         => get_term_link( $term ),
			'taxonomy'    => $taxonomy,
		);

		if ( 'category' === $taxonomy ) {
			$data['parent_id'] = $term->parent;
		}

		if ( $include_seo ) {
			$data['seo'] = $this->get_term_seo( $term->term_id, $taxonomy );
		}

		return $data;
	}

	/**
	 * Reads SEO metadata for a term from the active SEO plugin.
	 *
	 * @return array<string, string|null>
	 */
	private function get_term_seo( int $term_id, string $taxonomy ): array {
		$default = array( 'title' => null, 'description' => null, 'focus_keyword' => null, 'seo_provider' => 'none' );

		if ( defined( 'WPSEO_VERSION' ) ) {
			$yoast_meta = get_option( 'wpseo_taxonomy_meta', array() );
			$meta       = $yoast_meta[ $taxonomy ][ $term_id ] ?? array();
			return array(
				'title'         => $meta['wpseo_title'] ?? null,
				'description'   => $meta['wpseo_desc'] ?? null,
				'focus_keyword' => $meta['wpseo_focuskw'] ?? null,
				'seo_provider'  => 'yoast',
			);
		}

		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return array(
				'title'         => get_term_meta( $term_id, 'rank_math_title', true ) ?: null,
				'description'   => get_term_meta( $term_id, 'rank_math_description', true ) ?: null,
				'focus_keyword' => get_term_meta( $term_id, 'rank_math_focus_keyword', true ) ?: null,
				'seo_provider'  => 'rankmath',
			);
		}

		if ( defined( 'AIOSEO_VERSION' ) ) {
			return array(
				'title'         => get_term_meta( $term_id, '_aioseo_title', true ) ?: null,
				'description'   => get_term_meta( $term_id, '_aioseo_description', true ) ?: null,
				'focus_keyword' => null,
				'seo_provider'  => 'aioseo',
			);
		}

		return $default;
	}
}
