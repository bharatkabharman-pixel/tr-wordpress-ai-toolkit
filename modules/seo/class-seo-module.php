<?php
/**
 * SEO analysis module abilities.
 *
 * Works without any SEO plugin — also supports Yoast SEO, Rank Math and
 * All in One SEO Pack through separate meta key lookups.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers abilities for analysing SEO metadata across posts and pages.
 */
class TR_AI_Toolkit_SEO_Module {

	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	/**
	 * SEO meta key adapters keyed by plugin slug.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $seo_adapters = array(
		'yoast' => array(
			'title'       => '_yoast_wpseo_title',
			'description' => '_yoast_wpseo_metadesc',
		),
		'rankmath' => array(
			'title'       => 'rank_math_title',
			'description' => 'rank_math_description',
		),
		'aioseo' => array(
			'title'       => '_aioseo_title',
			'description' => '_aioseo_description',
		),
	);

	public function __construct(
		TR_AI_Toolkit_Permissions $permissions,
		TR_AI_Toolkit_Logger $logger
	) {
		$this->permissions = $permissions;
		$this->logger      = $logger;
	}

	public function register(): void {
		$this->register_get_seo_summary();
		$this->register_find_missing_meta_titles();
		$this->register_find_missing_meta_descriptions();
		$this->register_find_posts_without_featured_images();
		$this->register_analyze_content_structure();
	}

	private function register_get_seo_summary(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-seo-summary',
			array(
				'label'       => __( 'Get SEO Summary', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns an overall SEO health summary: total posts, posts missing meta title, missing meta description, missing featured images, short content, and detected SEO plugin.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_seo_summary( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_seo_summary( array $input ): array {
		$total_posts = (int) wp_count_posts()->publish;

		$missing_titles       = $this->count_missing_meta( 'title' );
		$missing_descriptions = $this->count_missing_meta( 'description' );
		$missing_thumbnails   = $this->count_missing_featured_images();
		$seo_plugin           = $this->detect_seo_plugin();

		$score = 100;
		if ( $total_posts > 0 ) {
			$score -= (int) round( ( $missing_titles / $total_posts ) * 30 );
			$score -= (int) round( ( $missing_descriptions / $total_posts ) * 30 );
			$score -= (int) round( ( $missing_thumbnails / $total_posts ) * 20 );
		}
		$score = max( 0, $score );

		$this->logger->log( 'tr-ai-toolkit/get-seo-summary', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'total_published_posts'       => $total_posts,
			'missing_meta_titles'         => $missing_titles,
			'missing_meta_descriptions'   => $missing_descriptions,
			'missing_featured_images'     => $missing_thumbnails,
			'seo_score_estimate'          => $score,
			'detected_seo_plugin'         => $seo_plugin,
			'note'                        => __( 'Score is an estimate. Use a dedicated SEO tool for accurate audits.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	private function register_find_missing_meta_titles(): void {
		wp_register_ability(
			'tr-ai-toolkit/find-missing-meta-titles',
			array(
				'label'       => __( 'Find Missing Meta Titles', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns published posts and pages that do not have a custom SEO meta title set in any supported SEO plugin (Yoast, Rank Math, AIOSEO). Posts without a custom title will use the post title, which may not be optimal for SEO.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string',  'description' => 'post or page or any. Default post.' ),
						'per_page'  => array( 'type' => 'integer', 'description' => 'Max results (1–100). Default 20.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_find_missing_meta( $input, 'title' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function register_find_missing_meta_descriptions(): void {
		wp_register_ability(
			'tr-ai-toolkit/find-missing-meta-descriptions',
			array(
				'label'       => __( 'Find Missing Meta Descriptions', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns published posts and pages that have no meta description set in any supported SEO plugin. Meta descriptions are important for search engine result click-through rates.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string',  'description' => 'post or page or any. Default post.' ),
						'per_page'  => array( 'type' => 'integer', 'description' => 'Max results (1–100). Default 20.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_find_missing_meta( $input, 'description' ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @param string               $type 'title' or 'description'
	 */
	private function execute_find_missing_meta( array $input, string $type ): array {
		$post_type = in_array( $input['post_type'] ?? 'post', array( 'post', 'page', 'any' ), true )
			? ( $input['post_type'] ?? 'post' )
			: 'post';
		$per_page  = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );

		$query = new \WP_Query( array(
			'post_type'      => 'any' === $post_type ? array( 'post', 'page' ) : $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page * 3, // Fetch more since we filter in PHP.
			'fields'         => 'ids',
		) );

		$missing = array();
		$meta_keys = $this->get_meta_keys_for( $type );

		foreach ( $query->posts as $post_id ) {
			$has_meta = false;
			foreach ( $meta_keys as $key ) {
				$value = get_post_meta( $post_id, $key, true );
				if ( ! empty( $value ) ) {
					$has_meta = true;
					break;
				}
			}

			if ( ! $has_meta ) {
				$post     = get_post( $post_id );
				$missing[] = array(
					'id'        => $post_id,
					'title'     => $post ? $post->post_title : '',
					'url'       => get_permalink( $post_id ),
					'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
					'post_type' => $post ? $post->post_type : '',
				);

				if ( count( $missing ) >= $per_page ) {
					break;
				}
			}
		}

		$ability = 'tr-ai-toolkit/find-missing-meta-' . ( 'title' === $type ? 'titles' : 'descriptions' );
		$this->logger->log( $ability, $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'missing' => $missing,
			'count'   => count( $missing ),
			'type'    => $type,
		);
	}

	private function register_find_posts_without_featured_images(): void {
		wp_register_ability(
			'tr-ai-toolkit/find-posts-without-featured-images',
			array(
				'label'       => __( 'Find Posts Without Featured Images', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns published posts that have no featured image set. Featured images improve SEO and social sharing appearance.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'Max results (1–100). Default 20.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_find_posts_without_featured_images( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_find_posts_without_featured_images( array $input ): array {
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );

		$query = new \WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'url'      => get_permalink( $post->ID ),
				'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
				'date'     => $post->post_date_gmt,
			);
		}

		$this->logger->log( 'tr-ai-toolkit/find-posts-without-featured-images', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'posts' => $posts,
			'total' => (int) $query->found_posts,
		);
	}

	private function register_analyze_content_structure(): void {
		wp_register_ability(
			'tr-ai-toolkit/analyze-content-structure',
			array(
				'label'       => __( 'Analyze Content Structure', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Analyses a single post or page and returns word count, heading structure, internal/external link counts, image count, readability notes and content length assessment.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post or page ID to analyse.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_analyze_content_structure( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_analyze_content_structure( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		$content    = $post->post_content;
		$plain_text = wp_strip_all_tags( $content );
		$word_count = str_word_count( $plain_text );

		// Heading counts.
		preg_match_all( '/<h([1-6])[^>]*>/i', $content, $heading_matches );
		$headings = array_count_values( array_map( fn( $n ) => 'h' . $n, $heading_matches[1] ?? array() ) );

		// Image count.
		preg_match_all( '/<img[^>]+>/i', $content, $img_matches );
		$image_count = count( $img_matches[0] ?? array() );

		// Internal and external links.
		$site_url = home_url();
		preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $link_matches );
		$internal_links = 0;
		$external_links = 0;

		foreach ( $link_matches[1] ?? array() as $href ) {
			if ( str_starts_with( $href, $site_url ) || str_starts_with( $href, '/' ) ) {
				++$internal_links;
			} elseif ( str_starts_with( $href, 'http' ) ) {
				++$external_links;
			}
		}

		// Content length rating.
		$length_rating = match ( true ) {
			$word_count >= 1500 => 'long',
			$word_count >= 600  => 'medium',
			$word_count >= 300  => 'short',
			default             => 'very_short',
		};

		// SEO meta.
		$seo_plugin  = $this->detect_seo_plugin();
		$title_key   = $this->get_meta_keys_for( 'title' );
		$desc_key    = $this->get_meta_keys_for( 'description' );
		$has_title   = false;
		$has_desc    = false;

		foreach ( $title_key as $key ) {
			if ( ! empty( get_post_meta( $post_id, $key, true ) ) ) {
				$has_title = true;
				break;
			}
		}
		foreach ( $desc_key as $key ) {
			if ( ! empty( get_post_meta( $post_id, $key, true ) ) ) {
				$has_desc = true;
				break;
			}
		}

		$this->logger->log( 'tr-ai-toolkit/analyze-content-structure', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'post_id'         => $post_id,
			'title'           => $post->post_title,
			'word_count'      => $word_count,
			'content_length'  => $length_rating,
			'headings'        => $headings,
			'image_count'     => $image_count,
			'internal_links'  => $internal_links,
			'external_links'  => $external_links,
			'has_featured_image'    => (bool) get_post_thumbnail_id( $post_id ),
			'has_seo_title'         => $has_title,
			'has_seo_description'   => $has_desc,
			'detected_seo_plugin'   => $seo_plugin,
		);
	}

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit/get-seo-summary', 'tr-ai-toolkit/find-missing-meta-titles',
			'tr-ai-toolkit/find-missing-meta-descriptions',
			'tr-ai-toolkit/find-posts-without-featured-images',
			'tr-ai-toolkit/analyze-content-structure',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit/get-seo-summary'                    => $this->execute_get_seo_summary( $args ),
			'tr-ai-toolkit/find-missing-meta-titles'           => $this->execute_find_missing_meta( $args, 'title' ),
			'tr-ai-toolkit/find-missing-meta-descriptions'     => $this->execute_find_missing_meta( $args, 'description' ),
			'tr-ai-toolkit/find-posts-without-featured-images' => $this->execute_find_posts_without_featured_images( $args ),
			'tr-ai-toolkit/analyze-content-structure'          => $this->execute_analyze_content_structure( $args ),
			default                                            => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array( 'name' => 'tr-ai-toolkit/get-seo-summary', 'description' => 'Returns a site-wide SEO health overview including post counts, missing meta, and plugin detection.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
			array( 'name' => 'tr-ai-toolkit/find-missing-meta-titles', 'description' => 'Returns posts that are missing SEO meta titles (checks Yoast, Rank Math, AIOSEO).', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'per_page' => array( 'type' => 'integer' ), 'page' => array( 'type' => 'integer' ) ) ) ),
			array( 'name' => 'tr-ai-toolkit/find-missing-meta-descriptions', 'description' => 'Returns posts that are missing SEO meta descriptions.', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'per_page' => array( 'type' => 'integer' ), 'page' => array( 'type' => 'integer' ) ) ) ),
			array( 'name' => 'tr-ai-toolkit/find-posts-without-featured-images', 'description' => 'Returns published posts that have no featured image set.', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'per_page' => array( 'type' => 'integer' ), 'page' => array( 'type' => 'integer' ) ) ) ),
			array( 'name' => 'tr-ai-toolkit/analyze-content-structure', 'description' => 'Analyses a single post for SEO signals: headings, word count, links, images, meta.', 'inputSchema' => array( 'type' => 'object', 'required' => array( 'post_id' ), 'properties' => array( 'post_id' => array( 'type' => 'integer' ) ) ) ),
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the meta keys to check for a given field across all SEO adapters.
	 *
	 * @param string $field 'title' or 'description'
	 * @return string[]
	 */
	private function get_meta_keys_for( string $field ): array {
		$keys = array();
		foreach ( $this->seo_adapters as $adapter ) {
			if ( isset( $adapter[ $field ] ) ) {
				$keys[] = $adapter[ $field ];
			}
		}

		return $keys;
	}

	/**
	 * Returns the slug of the first recognised SEO plugin that is active,
	 * or 'none' when none is found.
	 */
	private function detect_seo_plugin(): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'aioseo';
		}

		return 'none';
	}

	/**
	 * Returns the number of published posts missing meta of a given type.
	 */
	private function count_missing_meta( string $type ): int {
		$query = new \WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$meta_keys = $this->get_meta_keys_for( $type );
		$count     = 0;

		foreach ( $query->posts as $post_id ) {
			$found = false;
			foreach ( $meta_keys as $key ) {
				if ( ! empty( get_post_meta( $post_id, $key, true ) ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Returns the number of published posts without a featured image.
	 */
	private function count_missing_featured_images(): int {
		$query = new \WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		return (int) $query->found_posts;
	}
}
