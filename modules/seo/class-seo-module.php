<?php
/**
 * SEO analysis and management module.
 *
 * Works without any SEO plugin — also supports Yoast SEO, Rank Math and
 * All in One SEO Pack through separate meta key lookups and write paths.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers abilities for SEO metadata management, content analysis,
 * robots.txt inspection, and sitemap detection.
 */
class TR_AI_Toolkit_SEO_Module {

	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	/**
	 * Full SEO meta key adapters for every supported field, keyed by plugin slug.
	 *
	 * Notes:
	 * - rankmath 'robots' is a serialised PHP array, not a plain string.
	 * - aioseo 'robots_noindex'/'robots_nofollow' are "1" or "" strings.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $seo_adapters = array(
		'yoast' => array(
			'title'           => '_yoast_wpseo_title',
			'description'     => '_yoast_wpseo_metadesc',
			'focus_keyword'   => '_yoast_wpseo_focuskw',
			'canonical_url'   => '_yoast_wpseo_canonical',
			'robots_noindex'  => '_yoast_wpseo_meta-robots-noindex',
			'robots_nofollow' => '_yoast_wpseo_meta-robots-nofollow',
			'robots_advanced' => '_yoast_wpseo_meta-robots-adv',
			'og_title'        => '_yoast_wpseo_opengraph-title',
			'og_description'  => '_yoast_wpseo_opengraph-description',
			'og_image_url'    => '_yoast_wpseo_opengraph-image',
			'twitter_title'   => '_yoast_wpseo_twitter-title',
			'twitter_desc'    => '_yoast_wpseo_twitter-description',
			'twitter_image'   => '_yoast_wpseo_twitter-image',
		),
		'rankmath' => array(
			'title'           => 'rank_math_title',
			'description'     => 'rank_math_description',
			'focus_keyword'   => 'rank_math_focus_keyword',
			'canonical_url'   => 'rank_math_canonical_url',
			'robots'          => 'rank_math_robots',
			'og_title'        => 'rank_math_facebook_title',
			'og_description'  => 'rank_math_facebook_description',
			'og_image_url'    => 'rank_math_facebook_image',
			'twitter_title'   => 'rank_math_twitter_title',
			'twitter_desc'    => 'rank_math_twitter_description',
			'twitter_image'   => 'rank_math_twitter_image_url',
		),
		'aioseo' => array(
			'title'           => '_aioseo_title',
			'description'     => '_aioseo_description',
			'focus_keyword'   => '_aioseo_keywords',
			'og_title'        => '_aioseo_og_title',
			'og_description'  => '_aioseo_og_description',
			'robots_noindex'  => '_aioseo_robots_noindex',
			'robots_nofollow' => '_aioseo_robots_nofollow',
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
		$this->register_get_post_seo();
		$this->register_analyze_content_structure();
		$this->register_update_post_seo();
		$this->register_get_robots_txt();
		$this->register_get_sitemap_status();
	}

	// =========================================================================
	// Tool: get-seo-summary
	// =========================================================================

	private function register_get_seo_summary(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-seo-summary',
			array(
				'label'       => __( 'Get SEO Summary', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a site-wide SEO health overview: total posts, missing meta title, missing meta description, missing featured images, estimated SEO score, and detected SEO plugin.', 'tr-wordpress-ai-toolkit' ),
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
		$total_posts          = (int) wp_count_posts()->publish;
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

		$this->logger->log( 'tr-ai-toolkit-get-seo-summary', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'total_published_posts'     => $total_posts,
			'missing_meta_titles'       => $missing_titles,
			'missing_meta_descriptions' => $missing_descriptions,
			'missing_featured_images'   => $missing_thumbnails,
			'seo_score_estimate'        => $score,
			'detected_seo_plugin'       => $seo_plugin,
			'note'                      => __( 'Score is an estimate. Use a dedicated SEO tool for accurate audits.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	// =========================================================================
	// Tool: find-missing-meta-titles / find-missing-meta-descriptions
	// =========================================================================

	private function register_find_missing_meta_titles(): void {
		wp_register_ability(
			'tr-ai-toolkit-find-missing-meta-titles',
			array(
				'label'       => __( 'Find Missing Meta Titles', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns published posts and pages that have no custom SEO meta title set in any supported SEO plugin (Yoast, Rank Math, AIOSEO).', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string',  'description' => 'post, page, or any. Default: post.' ),
						'per_page'  => array( 'type' => 'integer', 'description' => 'Max results (1–100). Default: 20.' ),
						'page'      => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
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
			'tr-ai-toolkit-find-missing-meta-descriptions',
			array(
				'label'       => __( 'Find Missing Meta Descriptions', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns published posts and pages that have no meta description set in any supported SEO plugin.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string',  'description' => 'post, page, or any. Default: post.' ),
						'per_page'  => array( 'type' => 'integer', 'description' => 'Max results (1–100). Default: 20.' ),
						'page'      => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
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
		$page      = max( 1, (int) ( $input['page'] ?? 1 ) );

		$meta_keys  = $this->get_meta_keys_for( $type );
		$meta_query = array( 'relation' => 'AND' );

		foreach ( $meta_keys as $key ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
				array( 'key' => $key, 'value' => '', 'compare' => '=' ),
			);
		}

		$query_args = array(
			'post_type'      => 'any' === $post_type ? array( 'post', 'page' ) : $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'fields'         => 'ids',
		);

		if ( ! empty( $meta_keys ) ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$query   = new \WP_Query( $query_args );
		$missing = array();

		foreach ( $query->posts as $post_id ) {
			$post      = get_post( $post_id );
			$missing[] = array(
				'id'        => $post_id,
				'title'     => $post ? $post->post_title : '',
				'url'       => get_permalink( $post_id ),
				'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
				'post_type' => $post ? $post->post_type : '',
			);
		}

		$ability = 'tr-ai-toolkit-find-missing-meta-' . ( 'title' === $type ? 'titles' : 'descriptions' );
		$this->logger->log( $ability, $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'missing'     => $missing,
			'count'       => count( $missing ),
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
			'type'        => $type,
		);
	}

	// =========================================================================
	// Tool: find-posts-without-featured-images
	// =========================================================================

	private function register_find_posts_without_featured_images(): void {
		wp_register_ability(
			'tr-ai-toolkit-find-posts-without-featured-images',
			array(
				'label'       => __( 'Find Posts Without Featured Images', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns published posts that have no featured image set.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'Max results (1–100). Default: 20.' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
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

		$this->logger->log( 'tr-ai-toolkit-find-posts-without-featured-images', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'posts' => $posts,
			'total' => (int) $query->found_posts,
		);
	}

	// =========================================================================
	// Tool: get-post-seo (NEW — comprehensive metadata getter)
	// =========================================================================

	private function register_get_post_seo(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-post-seo',
			array(
				'label'       => __( 'Get Post SEO Metadata', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all SEO metadata for a post or page: meta title, meta description, focus keyword, canonical URL, robots directives, Open Graph data, and Twitter Card data. Reads from the active SEO plugin (Yoast, Rank Math, AIOSEO) or native meta fallback.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post or page ID.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_post_seo( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_post_seo( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		$seo_plugin = $this->detect_seo_plugin();
		$adapter    = $this->seo_adapters[ $seo_plugin ] ?? array();

		$meta = function( string $field ) use ( $post_id, $adapter ): string {
			if ( ! isset( $adapter[ $field ] ) ) {
				return '';
			}
			return (string) get_post_meta( $post_id, $adapter[ $field ], true );
		};

		$robots = $this->read_robots_meta( $post_id, $seo_plugin, $adapter );

		$og = array(
			'title'       => $meta( 'og_title' ) ?: null,
			'description' => $meta( 'og_description' ) ?: null,
			'image_url'   => $meta( 'og_image_url' ) ?: null,
		);

		$twitter = array(
			'title'       => $meta( 'twitter_title' ) ?: null,
			'description' => $meta( 'twitter_desc' ) ?: null,
			'image_url'   => $meta( 'twitter_image' ) ?: null,
		);

		$this->logger->log( 'tr-ai-toolkit-get-post-seo', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'post_id'      => $post_id,
			'post_title'   => $post->post_title,
			'post_url'     => get_permalink( $post_id ),
			'seo_provider' => $seo_plugin,
			'meta'         => array(
				'title'         => $meta( 'title' ) ?: null,
				'description'   => $meta( 'description' ) ?: null,
				'focus_keyword' => $meta( 'focus_keyword' ) ?: null,
				'canonical_url' => $meta( 'canonical_url' ) ?: null,
				'robots'        => $robots,
				'og'            => $og,
				'twitter'       => $twitter,
			),
		);
	}

	// =========================================================================
	// Tool: analyze-content-structure (enhanced)
	// =========================================================================

	private function register_analyze_content_structure(): void {
		wp_register_ability(
			'tr-ai-toolkit-analyze-content-structure',
			array(
				'label'       => __( 'Analyze Content Structure', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Analyses a post or page and returns structured SEO and readability data: heading hierarchy, word count, paragraph count, image alt coverage, internal/external links, empty links, featured image status, SEO meta status, and actionable recommendations.', 'tr-wordpress-ai-toolkit' ),
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
			return new \WP_Error( 'not_found', __( 'Post not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		$content    = $post->post_content;
		$plain_text = wp_strip_all_tags( $content );
		$word_count = str_word_count( $plain_text );

		// Heading hierarchy with levels and text.
		preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $content, $heading_matches );
		$heading_counts = array_count_values( array_map( fn( $n ) => 'h' . $n, $heading_matches[1] ?? array() ) );
		$h1_count       = $heading_counts['h1'] ?? 0;

		// Paragraph count (block and classic).
		preg_match_all( '/<p[^>]*>/i', $content, $para_matches );
		$paragraph_count = count( $para_matches[0] ?? array() );

		// Images — count and check alt text.
		preg_match_all( '/<img([^>]+)>/i', $content, $img_matches );
		$total_images       = count( $img_matches[0] ?? array() );
		$images_missing_alt = 0;
		foreach ( $img_matches[1] ?? array() as $img_attrs ) {
			if ( ! preg_match( '/\balt=["\'][^"\']+["\']/', $img_attrs ) ) {
				++$images_missing_alt;
			}
		}

		// Links — internal, external, empty href.
		$site_url         = home_url();
		$internal_links   = 0;
		$external_links   = 0;
		$empty_links      = 0;
		preg_match_all( '/<a([^>]+)>/i', $content, $anchor_matches );

		foreach ( $anchor_matches[1] ?? array() as $anchor_attrs ) {
			if ( preg_match( '/\bhref=["\']([^"\']*)["\']/', $anchor_attrs, $href_match ) ) {
				$href = $href_match[1];
				if ( '' === trim( $href ) || '#' === $href ) {
					++$empty_links;
				} elseif ( str_starts_with( $href, $site_url ) || str_starts_with( $href, '/' ) ) {
					++$internal_links;
				} elseif ( str_starts_with( $href, 'http' ) ) {
					++$external_links;
				}
			} else {
				++$empty_links;
			}
		}

		// Content length rating.
		$content_length = match ( true ) {
			$word_count >= 1500 => 'long',
			$word_count >= 600  => 'medium',
			$word_count >= 300  => 'short',
			default             => 'very_short',
		};

		// SEO meta status.
		$seo_plugin = $this->detect_seo_plugin();
		$has_title  = false;
		$has_desc   = false;
		foreach ( $this->get_meta_keys_for( 'title' ) as $key ) {
			if ( ! empty( get_post_meta( $post_id, $key, true ) ) ) {
				$has_title = true;
				break;
			}
		}
		foreach ( $this->get_meta_keys_for( 'description' ) as $key ) {
			if ( ! empty( get_post_meta( $post_id, $key, true ) ) ) {
				$has_desc = true;
				break;
			}
		}

		$has_featured_image = (bool) get_post_thumbnail_id( $post_id );

		// Heading sequence: flag multiple H1s or H2 before any H1.
		$heading_issues = array();
		if ( $h1_count > 1 ) {
			$heading_issues[] = sprintf( __( 'Multiple H1 headings found (%d). Each page should have exactly one H1.', 'tr-wordpress-ai-toolkit' ), $h1_count );
		}
		if ( 0 === $h1_count && ! empty( $heading_counts ) ) {
			$heading_issues[] = __( 'No H1 heading found. Add one H1 that describes the page topic.', 'tr-wordpress-ai-toolkit' );
		}

		// Build issues/warnings/recommendations.
		$issues          = array();
		$warnings        = array();
		$recommendations = array();

		if ( ! $has_title ) {
			$issues[] = __( 'No SEO meta title set.', 'tr-wordpress-ai-toolkit' );
		}
		if ( ! $has_desc ) {
			$issues[] = __( 'No SEO meta description set.', 'tr-wordpress-ai-toolkit' );
		}
		foreach ( $heading_issues as $hi ) {
			$issues[] = $hi;
		}
		if ( $images_missing_alt > 0 ) {
			$issues[] = sprintf(
				__( '%d image(s) missing alt text — required for accessibility and image SEO.', 'tr-wordpress-ai-toolkit' ),
				$images_missing_alt
			);
		}
		if ( $empty_links > 0 ) {
			$warnings[] = sprintf( __( '%d empty or anchor-only link(s) found.', 'tr-wordpress-ai-toolkit' ), $empty_links );
		}
		if ( ! $has_featured_image ) {
			$warnings[] = __( 'No featured image. Featured images improve SEO and social sharing.', 'tr-wordpress-ai-toolkit' );
		}
		if ( 'very_short' === $content_length ) {
			$warnings[] = sprintf(
				__( 'Content is very short (%d words). Consider expanding to at least 300 words.', 'tr-wordpress-ai-toolkit' ),
				$word_count
			);
		}
		if ( 0 === $internal_links ) {
			$recommendations[] = __( 'Add internal links to help search engines discover related content.', 'tr-wordpress-ai-toolkit' );
		}
		if ( 'short' === $content_length ) {
			$recommendations[] = __( 'Content is short. Aim for 600+ words for competitive topics.', 'tr-wordpress-ai-toolkit' );
		}

		$this->logger->log( 'tr-ai-toolkit-analyze-content-structure', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'post_id'    => $post_id,
			'post_title' => $post->post_title,
			'summary'    => array(
				'word_count'         => $word_count,
				'content_length'     => $content_length,
				'paragraph_count'    => $paragraph_count,
				'has_seo_title'      => $has_title,
				'has_seo_desc'       => $has_desc,
				'has_featured_image' => $has_featured_image,
				'detected_seo'       => $seo_plugin,
			),
			'measured'   => array(
				'headings'            => $heading_counts,
				'h1_count'            => $h1_count,
				'total_images'        => $total_images,
				'images_missing_alt'  => $images_missing_alt,
				'internal_links'      => $internal_links,
				'external_links'      => $external_links,
				'empty_links'         => $empty_links,
			),
			'issues'          => $issues,
			'warnings'        => $warnings,
			'recommendations' => $recommendations,
		);
	}

	// =========================================================================
	// Tool: update-post-seo (enhanced — supports all SEO fields)
	// =========================================================================

	private function register_update_post_seo(): void {
		wp_register_ability(
			'tr-ai-toolkit-update-post-seo',
			array(
				'label'       => __( 'Update Post SEO Metadata', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates SEO metadata for a post: meta title, meta description, focus keyword, canonical URL, robots directives, and Open Graph fields. Writes to the active SEO plugin (Yoast, Rank Math, AIOSEO) or native meta fallback. Only supplied fields are updated — existing values for other fields are preserved.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id'          => array( 'type' => 'integer', 'description' => 'Post or page ID.' ),
						'meta_title'       => array( 'type' => 'string',  'description' => 'SEO meta title. Omit to leave unchanged.' ),
						'meta_description' => array( 'type' => 'string',  'description' => 'SEO meta description. Omit to leave unchanged.' ),
						'focus_keyword'    => array( 'type' => 'string',  'description' => 'Primary focus keyword. Omit to leave unchanged.' ),
						'canonical_url'    => array( 'type' => 'string',  'description' => 'Canonical URL. Use empty string to clear. Omit to leave unchanged.' ),
						'robots'           => array(
							'type'        => 'object',
							'description' => 'Robots directives. Omit to leave unchanged.',
							'properties'  => array(
								'noindex'   => array( 'type' => 'boolean' ),
								'nofollow'  => array( 'type' => 'boolean' ),
								'noarchive' => array( 'type' => 'boolean' ),
								'nosnippet' => array( 'type' => 'boolean' ),
							),
						),
						'og_title'       => array( 'type' => 'string', 'description' => 'Open Graph title. Omit to leave unchanged.' ),
						'og_description' => array( 'type' => 'string', 'description' => 'Open Graph description. Omit to leave unchanged.' ),
						'og_image_url'   => array( 'type' => 'string', 'description' => 'Open Graph image URL. Omit to leave unchanged.' ),
					),
				),
				'permission_callback' => fn() => $this->permissions->can_write() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_update_post_seo( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_update_post_seo( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->logger->log( 'tr-ai-toolkit-update-post-seo', $input, TR_AI_Toolkit_Logger::STATUS_DENIED );
			return new \WP_Error( 'forbidden', __( 'You cannot edit this post.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$seo_plugin = $this->detect_seo_plugin();
		$adapter    = $this->seo_adapters[ $seo_plugin ] ?? array();
		$updated    = array();

		$simple_fields = array(
			'meta_title'       => 'title',
			'meta_description' => 'description',
			'focus_keyword'    => 'focus_keyword',
			'canonical_url'    => 'canonical_url',
			'og_title'         => 'og_title',
			'og_description'   => 'og_description',
			'og_image_url'     => 'og_image_url',
		);

		foreach ( $simple_fields as $input_key => $adapter_field ) {
			if ( ! array_key_exists( $input_key, $input ) ) {
				continue;
			}

			$meta_key = $adapter[ $adapter_field ] ?? ( '_tr_seo_' . $adapter_field );
			$value    = in_array( $input_key, array( 'og_image_url', 'canonical_url' ), true )
				? esc_url_raw( (string) $input[ $input_key ] )
				: sanitize_text_field( (string) $input[ $input_key ] );

			if ( 'meta_description' === $input_key || 'og_description' === $input_key ) {
				$value = sanitize_textarea_field( (string) $input[ $input_key ] );
			}

			update_post_meta( $post_id, $meta_key, $value );
			$updated[ $input_key ] = $value;
		}

		// Robots directives — handled per-provider.
		if ( isset( $input['robots'] ) && is_array( $input['robots'] ) ) {
			$this->write_robots_meta( $post_id, $seo_plugin, $adapter, $input['robots'] );
			$updated['robots'] = $input['robots'];
		}

		if ( empty( $updated ) ) {
			return new \WP_Error( 'no_fields', __( 'Provide at least one field to update.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$this->logger->log( 'tr-ai-toolkit-update-post-seo', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'post_id'    => $post_id,
			'seo_plugin' => $seo_plugin,
			'updated'    => $updated,
		);
	}

	// =========================================================================
	// Tool: get-robots-txt
	// =========================================================================

	private function register_get_robots_txt(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-robots-txt',
			array(
				'label'       => __( 'Get Robots.txt', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns the content and source of the site\'s robots.txt. Reports whether a physical file exists or whether WordPress is serving a virtual robots.txt. Also detects known disallow rules and SEO plugin overrides.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_robots_txt( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_robots_txt( array $input ): array {
		$physical_path  = ABSPATH . 'robots.txt';
		$physical_exists = file_exists( $physical_path ) && is_readable( $physical_path );
		$source         = $physical_exists ? 'physical_file' : 'wordpress_virtual';
		$content        = '';

		if ( $physical_exists ) {
			$content = file_get_contents( $physical_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $content ) {
				$content = '';
				$source  = 'physical_file_unreadable';
			}
		} else {
			// Generate the virtual robots.txt WordPress would serve.
			$public = (bool) get_option( 'blog_public' );

			if ( ! $public ) {
				$content = "User-agent: *\nDisallow: /\n";
			} else {
				$admin_url_path = wp_parse_url( admin_url(), PHP_URL_PATH );
				$content        = "User-agent: *\n";
				$content       .= 'Disallow: ' . $admin_url_path . "\n";
				$content       .= 'Allow: ' . $admin_url_path . "admin-ajax.php\n";
			}

			// SEO plugins may add sitemap lines.
			$seo_plugin = $this->detect_seo_plugin();
			if ( 'yoast' === $seo_plugin ) {
				$content .= "\nSitemap: " . home_url( '/sitemap_index.xml' ) . "\n";
			} elseif ( 'rankmath' === $seo_plugin ) {
				$content .= "\nSitemap: " . home_url( '/sitemap_index.xml' ) . "\n";
			}
		}

		$discourage_indexing = ! (bool) get_option( 'blog_public' );
		$seo_plugin          = $this->detect_seo_plugin();

		$this->logger->log( 'tr-ai-toolkit-get-robots-txt', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'source'               => $source,
			'content'              => trim( (string) $content ),
			'discourage_indexing'  => $discourage_indexing,
			'seo_provider'         => $seo_plugin,
			'note'                 => $physical_exists
				? __( 'A physical robots.txt file exists and overrides the WordPress virtual robots.txt.', 'tr-wordpress-ai-toolkit' )
				: __( 'WordPress is serving a virtual robots.txt. To customise it, use a robots.txt plugin or create a physical file.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	// =========================================================================
	// Tool: get-sitemap-status
	// =========================================================================

	private function register_get_sitemap_status(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-sitemap-status',
			array(
				'label'       => __( 'Get Sitemap Status', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns detected sitemap configuration: provider (WordPress core, Yoast, Rank Math, AIOSEO), expected sitemap URLs, and whether sitemaps appear enabled. Does not fetch remote URLs.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_sitemap_status( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_sitemap_status( array $input ): array {
		$seo_plugin  = $this->detect_seo_plugin();
		$sitemaps    = array();
		$provider    = 'none';

		if ( 'yoast' === $seo_plugin ) {
			$provider  = 'yoast_seo';
			$sitemaps[] = array(
				'type' => 'index',
				'url'  => home_url( '/sitemap_index.xml' ),
				'note' => 'Yoast SEO sitemap index',
			);
		} elseif ( 'rankmath' === $seo_plugin ) {
			$provider   = 'rank_math';
			$sitemaps[] = array(
				'type' => 'index',
				'url'  => home_url( '/sitemap_index.xml' ),
				'note' => 'Rank Math sitemap index',
			);
		} elseif ( 'aioseo' === $seo_plugin ) {
			$provider   = 'aioseo';
			$sitemaps[] = array(
				'type' => 'index',
				'url'  => home_url( '/sitemap.xml' ),
				'note' => 'AIOSEO sitemap',
			);
		} else {
			// WordPress core sitemaps (WP 5.5+).
			$wp_sitemaps_enabled = function_exists( 'wp_sitemaps_get_server' );
			if ( $wp_sitemaps_enabled ) {
				$server   = wp_sitemaps_get_server();
				$enabled  = $server ? (bool) apply_filters( 'wp_sitemaps_enabled', true ) : false;

				if ( $enabled ) {
					$provider   = 'wordpress_core';
					$sitemaps[] = array(
						'type' => 'index',
						'url'  => home_url( '/wp-sitemap.xml' ),
						'note' => 'WordPress core sitemap index',
					);
				}
			}
		}

		$this->logger->log( 'tr-ai-toolkit-get-sitemap-status', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'provider'           => $provider,
			'seo_plugin_active'  => $seo_plugin,
			'sitemaps'           => $sitemaps,
			'discourage_indexing' => ! (bool) get_option( 'blog_public' ),
			'note'               => __( 'URLs are expected locations only — this tool does not fetch or validate them remotely.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	// =========================================================================
	// Module interface
	// =========================================================================

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-get-seo-summary',
			'tr-ai-toolkit-find-missing-meta-titles',
			'tr-ai-toolkit-find-missing-meta-descriptions',
			'tr-ai-toolkit-find-posts-without-featured-images',
			'tr-ai-toolkit-get-post-seo',
			'tr-ai-toolkit-analyze-content-structure',
			'tr-ai-toolkit-update-post-seo',
			'tr-ai-toolkit-get-robots-txt',
			'tr-ai-toolkit-get-sitemap-status',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-get-seo-summary'                    => $this->execute_get_seo_summary( $args ),
			'tr-ai-toolkit-find-missing-meta-titles'           => $this->execute_find_missing_meta( $args, 'title' ),
			'tr-ai-toolkit-find-missing-meta-descriptions'     => $this->execute_find_missing_meta( $args, 'description' ),
			'tr-ai-toolkit-find-posts-without-featured-images' => $this->execute_find_posts_without_featured_images( $args ),
			'tr-ai-toolkit-get-post-seo'                       => $this->execute_get_post_seo( $args ),
			'tr-ai-toolkit-analyze-content-structure'          => $this->execute_analyze_content_structure( $args ),
			'tr-ai-toolkit-update-post-seo'                    => $this->execute_update_post_seo( $args ),
			'tr-ai-toolkit-get-robots-txt'                     => $this->execute_get_robots_txt( $args ),
			'tr-ai-toolkit-get-sitemap-status'                 => $this->execute_get_sitemap_status( $args ),
			default                                            => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-get-seo-summary',
				'description' => 'Returns site-wide SEO health: post counts, missing meta, missing featured images, estimated score, and detected SEO plugin.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-find-missing-meta-titles',
				'description' => 'Returns published posts/pages missing a custom SEO meta title (checks Yoast, Rank Math, AIOSEO).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string' ),
						'per_page'  => array( 'type' => 'integer' ),
						'page'      => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-find-missing-meta-descriptions',
				'description' => 'Returns published posts/pages missing a custom SEO meta description.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string' ),
						'per_page'  => array( 'type' => 'integer' ),
						'page'      => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-find-posts-without-featured-images',
				'description' => 'Returns published posts that have no featured image.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-post-seo',
				'description' => 'Returns all SEO metadata for a post: meta title, description, focus keyword, canonical URL, robots directives, Open Graph, Twitter Card. Reads from active SEO plugin.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array( 'post_id' => array( 'type' => 'integer' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-analyze-content-structure',
				'description' => 'Analyses a post for SEO and readability: headings, word count, paragraph count, image alt coverage, links, issues, warnings, and recommendations.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array( 'post_id' => array( 'type' => 'integer' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-update-post-seo',
				'description' => 'Updates SEO metadata for a post: meta title, description, focus keyword, canonical URL, robots directives, Open Graph fields. Only supplied fields are changed.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id'          => array( 'type' => 'integer' ),
						'meta_title'       => array( 'type' => 'string' ),
						'meta_description' => array( 'type' => 'string' ),
						'focus_keyword'    => array( 'type' => 'string' ),
						'canonical_url'    => array( 'type' => 'string' ),
						'robots'           => array( 'type' => 'object' ),
						'og_title'         => array( 'type' => 'string' ),
						'og_description'   => array( 'type' => 'string' ),
						'og_image_url'     => array( 'type' => 'string' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-robots-txt',
				'description' => 'Returns robots.txt content and source (physical file or WordPress virtual). Reports discourage-indexing status and SEO plugin overrides.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-sitemap-status',
				'description' => 'Returns detected sitemap provider and expected sitemap URLs (WordPress core, Yoast, Rank Math, AIOSEO). Does not fetch remote URLs.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
		);
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Returns the meta keys to check for a given field across all SEO adapters.
	 *
	 * @param string $field e.g. 'title', 'description', 'focus_keyword'
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
	 * Returns 'yoast', 'rankmath', 'aioseo', or 'none'.
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
	 * Reads the normalised robots directives for a post across all providers.
	 *
	 * @param int                       $post_id
	 * @param string                    $seo_plugin  Detected plugin slug.
	 * @param array<string, string>     $adapter     Adapter meta key map.
	 * @return array<string, bool|null>
	 */
	private function read_robots_meta( int $post_id, string $seo_plugin, array $adapter ): array {
		$default = array(
			'noindex'   => null,
			'nofollow'  => null,
			'noarchive' => null,
			'nosnippet' => null,
		);

		if ( 'rankmath' === $seo_plugin && isset( $adapter['robots'] ) ) {
			$raw     = get_post_meta( $post_id, $adapter['robots'], true );
			$directives = maybe_unserialize( $raw );
			if ( is_array( $directives ) ) {
				return array(
					'noindex'   => in_array( 'noindex', $directives, true ),
					'nofollow'  => in_array( 'nofollow', $directives, true ),
					'noarchive' => in_array( 'noarchive', $directives, true ),
					'nosnippet' => in_array( 'nosnippet', $directives, true ),
				);
			}
			return $default;
		}

		// Yoast and AIOSEO both use separate noindex/nofollow keys.
		$noindex  = isset( $adapter['robots_noindex'] )
			? ( '1' === (string) get_post_meta( $post_id, $adapter['robots_noindex'], true ) )
			: null;
		$nofollow = isset( $adapter['robots_nofollow'] )
			? ( '1' === (string) get_post_meta( $post_id, $adapter['robots_nofollow'], true ) )
			: null;

		// Yoast advanced (noarchive, nosnippet stored as comma-separated string).
		$noarchive = null;
		$nosnippet = null;
		if ( 'yoast' === $seo_plugin && isset( $adapter['robots_advanced'] ) ) {
			$adv       = (string) get_post_meta( $post_id, $adapter['robots_advanced'], true );
			$noarchive = str_contains( $adv, 'noarchive' );
			$nosnippet = str_contains( $adv, 'nosnippet' );
		}

		if ( null === $noindex && null === $nofollow ) {
			return $default;
		}

		return array(
			'noindex'   => $noindex,
			'nofollow'  => $nofollow,
			'noarchive' => $noarchive,
			'nosnippet' => $nosnippet,
		);
	}

	/**
	 * Writes robots directives using the provider's storage format.
	 *
	 * @param int                   $post_id
	 * @param string                $seo_plugin
	 * @param array<string, string> $adapter
	 * @param array<string, mixed>  $robots   Input from the update request.
	 */
	private function write_robots_meta( int $post_id, string $seo_plugin, array $adapter, array $robots ): void {
		if ( 'rankmath' === $seo_plugin && isset( $adapter['robots'] ) ) {
			$directives = array();
			foreach ( array( 'noindex', 'nofollow', 'noarchive', 'nosnippet' ) as $d ) {
				if ( ! empty( $robots[ $d ] ) ) {
					$directives[] = $d;
				}
			}
			update_post_meta( $post_id, $adapter['robots'], $directives );
			return;
		}

		if ( isset( $adapter['robots_noindex'] ) ) {
			update_post_meta( $post_id, $adapter['robots_noindex'], ! empty( $robots['noindex'] ) ? '1' : '' );
		}
		if ( isset( $adapter['robots_nofollow'] ) ) {
			update_post_meta( $post_id, $adapter['robots_nofollow'], ! empty( $robots['nofollow'] ) ? '1' : '' );
		}

		// Yoast advanced directives.
		if ( 'yoast' === $seo_plugin && isset( $adapter['robots_advanced'] ) ) {
			$adv = array();
			if ( ! empty( $robots['noarchive'] ) ) {
				$adv[] = 'noarchive';
			}
			if ( ! empty( $robots['nosnippet'] ) ) {
				$adv[] = 'nosnippet';
			}
			update_post_meta( $post_id, $adapter['robots_advanced'], implode( ',', $adv ) );
		}
	}

	/**
	 * Counts published posts missing a given meta type (across all SEO adapters).
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
	 * Counts published posts without a featured image.
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
