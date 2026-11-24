<?php
/**
 * Media module abilities.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers abilities for browsing and managing WordPress media library items.
 */
class TR_AI_Toolkit_Media_Module {

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
		$this->register_list_media();
		$this->register_get_media_details();
		$this->register_search_media();
		$this->register_update_media_metadata();
		$this->register_find_unused_media();
		$this->register_upload_media_from_url();
		$this->register_find_media_without_alt();
	}

	private function register_list_media(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-media',
			array(
				'label'       => __( 'List Media', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a paginated list of media library items. Supports filtering by MIME type. Returns ID, title, URL, type, file size, dimensions and upload date.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page'  => array( 'type' => 'integer', 'description' => 'Items per page (1–100). Default 20.' ),
						'page'      => array( 'type' => 'integer', 'description' => 'Page number. Default 1.' ),
						'mime_type' => array( 'type' => 'string',  'description' => 'Filter by MIME type prefix, e.g. image, video, application/pdf.' ),
						'search'    => array( 'type' => 'string',  'description' => 'Search by title or filename.' ),
						'orderby'   => array( 'type' => 'string',  'description' => 'Sort field: date, title, ID. Default date.' ),
						'order'     => array( 'type' => 'string',  'description' => 'ASC or DESC. Default DESC.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_media( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_media( array $input ): array {
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$orderby  = in_array( $input['orderby'] ?? 'date', array( 'date', 'title', 'ID' ), true ) ? ( $input['orderby'] ?? 'date' ) : 'date';
		$order    = strtoupper( $input['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = sanitize_text_field( $input['mime_type'] );
		}

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $attachment ) {
			$items[] = $this->format_attachment( $attachment );
		}

		$this->logger->log( 'tr-ai-toolkit-list-media', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	private function register_get_media_details(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-media-details',
			array(
				'label'       => __( 'Get Media Details', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns full details for a single media attachment by ID, including all available image sizes, alt text, caption and file metadata.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'attachment_id' ),
					'properties' => array(
						'attachment_id' => array( 'type' => 'integer', 'description' => 'The attachment post ID.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_media_details( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_media_details( array $input ): array|\WP_Error {
		$id         = absint( $input['attachment_id'] ?? 0 );
		$attachment = $id ? get_post( $id ) : null;

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new \WP_Error( 'not_found', __( 'Attachment not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		$meta       = wp_get_attachment_metadata( $id );
		$image_data = array();

		if ( wp_attachment_is_image( $id ) ) {
			foreach ( get_intermediate_image_sizes() as $size ) {
				$src = wp_get_attachment_image_src( $id, $size );
				if ( $src ) {
					$image_data[ $size ] = array(
						'url'    => $src[0],
						'width'  => $src[1],
						'height' => $src[2],
					);
				}
			}
		}

		$this->logger->log( 'tr-ai-toolkit-get-media-details', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array_merge(
			$this->format_attachment( $attachment ),
			array(
				'alt_text'    => get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'caption'     => $attachment->post_excerpt,
				'description' => $attachment->post_content,
				'image_sizes' => $image_data,
				'file_meta'   => is_array( $meta ) ? array_intersect_key( $meta, array_flip( array( 'width', 'height', 'file', 'sizes', 'image_meta' ) ) ) : array(),
			)
		);
	}

	private function register_search_media(): void {
		wp_register_ability(
			'tr-ai-toolkit-search-media',
			array(
				'label'       => __( 'Search Media', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Searches the media library by title, filename or alt text. Returns matching items with URLs.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'query' ),
					'properties' => array(
						'query'     => array( 'type' => 'string',  'description' => 'Search term.' ),
						'per_page'  => array( 'type' => 'integer', 'description' => 'Max results (1–50). Default 10.' ),
						'mime_type' => array( 'type' => 'string',  'description' => 'Filter by MIME type prefix.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_search_media( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_search_media( array $input ): array|\WP_Error {
		$query = sanitize_text_field( $input['query'] ?? '' );

		if ( '' === $query ) {
			return new \WP_Error( 'invalid_input', __( 'query is required.', 'tr-wordpress-ai-toolkit' ) );
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			's'              => $query,
			'posts_per_page' => min( 50, max( 1, (int) ( $input['per_page'] ?? 10 ) ) ),
		);

		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = sanitize_text_field( $input['mime_type'] );
		}

		$wp_query = new \WP_Query( $args );
		$items    = array();

		foreach ( $wp_query->posts as $attachment ) {
			$items[] = $this->format_attachment( $attachment );
		}

		$this->logger->log( 'tr-ai-toolkit-search-media', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array( 'items' => $items, 'total' => (int) $wp_query->found_posts );
	}

	private function register_update_media_metadata(): void {
		wp_register_ability(
			'tr-ai-toolkit-update-media-metadata',
			array(
				'label'       => __( 'Update Media Metadata', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates the title, alt text, caption or description for a media attachment. Returns updated attachment summary.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'attachment_id' ),
					'properties' => array(
						'attachment_id' => array( 'type' => 'integer', 'description' => 'The attachment post ID.' ),
						'title'         => array( 'type' => 'string',  'description' => 'New attachment title.' ),
						'alt_text'      => array( 'type' => 'string',  'description' => 'New alt text for images.' ),
						'caption'       => array( 'type' => 'string',  'description' => 'New caption.' ),
						'description'   => array( 'type' => 'string',  'description' => 'New description.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_upload() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_update_media_metadata( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_update_media_metadata( array $input ): array|\WP_Error {
		$id         = absint( $input['attachment_id'] ?? 0 );
		$attachment = $id ? get_post( $id ) : null;

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new \WP_Error( 'not_found', __( 'Attachment not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			$this->logger->log( 'tr-ai-toolkit-update-media-metadata', $input, TR_AI_Toolkit_Logger::STATUS_DENIED );
			return new \WP_Error( 'forbidden', __( 'You cannot edit this attachment.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$update = array( 'ID' => $id );

		if ( isset( $input['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['caption'] ) ) {
			$update['post_excerpt'] = sanitize_textarea_field( $input['caption'] );
		}
		if ( isset( $input['description'] ) ) {
			$update['post_content'] = wp_kses_post( $input['description'] );
		}

		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}

		if ( isset( $input['alt_text'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
		}

		$this->logger->log( 'tr-ai-toolkit-update-media-metadata', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return $this->format_attachment( get_post( $id ) );
	}

	private function register_find_unused_media(): void {
		wp_register_ability(
			'tr-ai-toolkit-find-unused-media',
			array(
				'label'       => __( 'Find Unused Media', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns media attachments that are not set as a featured image and have no parent post. Useful for cleaning up orphaned uploads. Returns IDs and URLs only — does not delete anything.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page'  => array( 'type' => 'integer', 'description' => 'Max results (1–100). Default 20.' ),
						'mime_type' => array( 'type' => 'string',  'description' => 'Filter by MIME type prefix.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_upload() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_find_unused_media( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_find_unused_media( array $input ): array {
		global $wpdb;

		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );

		// Collect attachment IDs currently used as featured images anywhere on the site.
		// _thumbnail_id is stored as postmeta on the POST that uses the image, not on the
		// attachment itself — so we cannot use a WP_Query meta_query on the attachment.
		$used_as_featured = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT CAST(meta_value AS UNSIGNED) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
				'_thumbnail_id'
			)
		);

		$used_as_featured = array_map( 'absint', (array) $used_as_featured );

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => 0,            // Not inserted via a specific post's upload
			'posts_per_page' => $per_page,
		);

		if ( ! empty( $used_as_featured ) ) {
			$args['post__not_in'] = $used_as_featured;
		}

		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = sanitize_text_field( $input['mime_type'] );
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $attachment ) {
			$items[] = array(
				'id'    => $attachment->ID,
				'title' => $attachment->post_title,
				'url'   => wp_get_attachment_url( $attachment->ID ),
				'type'  => $attachment->post_mime_type,
				'date'  => $attachment->post_date_gmt,
			);
		}

		$this->logger->log( 'tr-ai-toolkit-find-unused-media', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'note'  => 'These attachments have no parent post and are not set as featured images. They may still be used in post content. Review before deleting.',
		);
	}

	private function register_upload_media_from_url(): void {
		wp_register_ability(
			'tr-ai-toolkit-upload-media-from-url',
			array(
				'label'       => __( 'Upload Media From URL', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Downloads a file from a URL and adds it to the media library. Supports images and PDF only.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'url' ),
					'properties' => array(
						'url'       => array( 'type' => 'string',  'description' => 'Public URL of the file to download.' ),
						'title'     => array( 'type' => 'string',  'description' => 'Optional title for the attachment.' ),
						'alt_text'  => array( 'type' => 'string',  'description' => 'Optional alt text (images only).' ),
						'post_id'   => array( 'type' => 'integer', 'description' => 'Optional post to attach the media to.' ),
					),
				),
				'permission_callback' => fn() => $this->permissions->can_upload() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_upload_media_from_url( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_upload_media_from_url( array $input ): array|\WP_Error {
		$url = esc_url_raw( $input['url'] ?? '' );
		if ( empty( $url ) ) {
			return new \WP_Error( 'invalid_url', __( 'url is required.', 'tr-wordpress-ai-toolkit' ) );
		}

		$parsed = wp_parse_url( $url );
		if ( ! isset( $parsed['host'] ) || $this->is_private_host( $parsed['host'] ) ) {
			return new \WP_Error( 'forbidden_host', __( 'Downloading from private or local addresses is not allowed.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		if ( function_exists( 'finfo_open' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = (string) finfo_file( $finfo, $tmp );
			finfo_close( $finfo );

			$allowed = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml', 'application/pdf' );
			if ( ! in_array( $mime_type, $allowed, true ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return new \WP_Error( 'invalid_mime', sprintf( __( 'File type %s is not allowed. Only images and PDF are accepted.', 'tr-wordpress-ai-toolkit' ), $mime_type ), array( 'status' => 400 ) );
			}
		}

		$filename = basename( $parsed['path'] ?? 'upload' );
		$file_array = array(
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $tmp,
		);

		$post_id = absint( $input['post_id'] ?? 0 );
		$id      = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $id;
		}

		if ( isset( $input['title'] ) ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $input['title'] ) ) );
		}
		if ( isset( $input['alt_text'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
		}

		$this->logger->log( 'tr-ai-toolkit-upload-media-from-url', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS, "Attachment ID {$id}" );
		return $this->format_attachment( get_post( $id ) );
	}

	private function register_find_media_without_alt(): void {
		wp_register_ability(
			'tr-ai-toolkit-find-media-without-alt',
			array(
				'label'       => __( 'Find Media Without Alt Text', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns images in the media library that are missing alt text. Useful for accessibility and SEO audits.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'Max results (1-100). Default 20.' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default 1.' ),
					),
				),
				'permission_callback' => fn() => $this->permissions->can_upload() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_find_media_without_alt( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_find_media_without_alt( array $input ): array {
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );

		$query = new \WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				),
			),
		) );

		$items = array();
		foreach ( $query->posts as $attachment ) {
			$items[] = array(
				'id'    => $attachment->ID,
				'title' => $attachment->post_title,
				'url'   => wp_get_attachment_url( $attachment->ID ),
				'date'  => $attachment->post_date_gmt,
			);
		}

		$this->logger->log( 'tr-ai-toolkit-find-media-without-alt', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-list-media', 'tr-ai-toolkit-get-media-details',
			'tr-ai-toolkit-search-media', 'tr-ai-toolkit-update-media-metadata',
			'tr-ai-toolkit-find-unused-media', 'tr-ai-toolkit-upload-media-from-url',
			'tr-ai-toolkit-find-media-without-alt',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-list-media'              => $this->execute_list_media( $args ),
			'tr-ai-toolkit-get-media-details'       => $this->execute_get_media_details( $args ),
			'tr-ai-toolkit-search-media'            => $this->execute_search_media( $args ),
			'tr-ai-toolkit-update-media-metadata'   => $this->execute_update_media_metadata( $args ),
			'tr-ai-toolkit-find-unused-media'       => $this->execute_find_unused_media( $args ),
			'tr-ai-toolkit-upload-media-from-url'   => $this->execute_upload_media_from_url( $args ),
			'tr-ai-toolkit-find-media-without-alt'  => $this->execute_find_media_without_alt( $args ),
			default                                 => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array( 'name' => 'tr-ai-toolkit-list-media', 'description' => 'Browse the media library. Returns ID, filename, URL, type, size and alt text.', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'per_page' => array( 'type' => 'integer' ), 'page' => array( 'type' => 'integer' ), 'mime_type' => array( 'type' => 'string', 'description' => 'e.g. image/jpeg, image/png, video/mp4' ) ) ) ),
			array( 'name' => 'tr-ai-toolkit-get-media-details', 'description' => 'Returns full details of a single media attachment including all metadata.', 'inputSchema' => array( 'type' => 'object', 'required' => array( 'attachment_id' ), 'properties' => array( 'attachment_id' => array( 'type' => 'integer' ) ) ) ),
			array( 'name' => 'tr-ai-toolkit-search-media', 'description' => 'Search the media library by filename or title.', 'inputSchema' => array( 'type' => 'object', 'required' => array( 'query' ), 'properties' => array( 'query' => array( 'type' => 'string' ), 'per_page' => array( 'type' => 'integer' ), 'mime_type' => array( 'type' => 'string' ) ) ) ),
			array( 'name' => 'tr-ai-toolkit-update-media-metadata', 'description' => 'Update title, alt text or caption of a media attachment.', 'inputSchema' => array( 'type' => 'object', 'required' => array( 'attachment_id' ), 'properties' => array( 'attachment_id' => array( 'type' => 'integer' ), 'title' => array( 'type' => 'string' ), 'alt_text' => array( 'type' => 'string' ), 'caption' => array( 'type' => 'string' ), 'description' => array( 'type' => 'string' ) ) ) ),
			array( 'name' => 'tr-ai-toolkit-find-unused-media', 'description' => 'Find media files not attached to any post or used as featured image.', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'per_page' => array( 'type' => 'integer', 'description' => 'Max results (1-100). Default 20.' ) ) ) ),
			array(
				'name'        => 'tr-ai-toolkit-upload-media-from-url',
				'description' => 'Download a file from a public URL and add it to the media library. Accepts images and PDF only. Blocks private/local addresses.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'url' ), 'properties' => array(
					'url'      => array( 'type' => 'string' ),
					'title'    => array( 'type' => 'string' ),
					'alt_text' => array( 'type' => 'string' ),
					'post_id'  => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-find-media-without-alt',
				'description' => 'Returns images missing alt text — use for accessibility and SEO audits.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array(
					'per_page' => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
				) ),
			),
		);
	}

	private function is_private_host( string $host ): bool {
		$ip = gethostbyname( $host );
		return false === filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	/**
	 * Formats a WP_Post (attachment) into a standard response.
	 *
	 * @param \WP_Post $attachment
	 * @return array<string, mixed>
	 */
	private function format_attachment( \WP_Post $attachment ): array {
		$url  = wp_get_attachment_url( $attachment->ID );
		$meta = wp_get_attachment_metadata( $attachment->ID );

		return array(
			'id'          => $attachment->ID,
			'title'       => $attachment->post_title,
			'url'         => $url ?: '',
			'mime_type'   => $attachment->post_mime_type,
			'date'        => $attachment->post_date_gmt,
			'file_size'   => is_array( $meta ) && isset( $meta['filesize'] ) ? (int) $meta['filesize'] : null,
			'width'       => is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : null,
			'height'      => is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : null,
			'parent_id'   => $attachment->post_parent,
			'author_id'   => (int) $attachment->post_author,
		);
	}
}
