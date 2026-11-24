<?php
/**
 * Performance module.
 *
 * Covers three areas:
 *  1. PageSpeed Insights — Google PSI API v5 with transient caching.
 *  2. Cache plugin integrations — WP Rocket, LiteSpeed Cache, W3 Total Cache,
 *     WP Super Cache.
 *  3. Image optimisation — large-image finder, WebP conversion via
 *     WP_Image_Editor, thumbnail regeneration.
 *
 * Security notes:
 *  - PageSpeed URLs are validated to prevent SSRF.
 *  - API keys are stored in plugin settings, never echoed back.
 *  - Cache-clear tools require admin capability; full clear requires confirm.
 *  - Image operations preserve originals and create new copies.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Performance, speed and image-optimisation MCP tools.
 */
class TR_AI_Toolkit_Performance_Module {

	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;
	private TR_AI_Toolkit_Settings    $settings;

	/** Transient TTL for PageSpeed results (1 hour). */
	private const PSI_CACHE_TTL = HOUR_IN_SECONDS;

	/** Transient key prefix for PSI cache. */
	private const PSI_KEY_PREFIX = 'tr_psi_';

	/** Google PageSpeed Insights API v5 endpoint. */
	private const PSI_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	public function __construct(
		TR_AI_Toolkit_Permissions $permissions,
		TR_AI_Toolkit_Logger $logger,
		?TR_AI_Toolkit_Settings $settings = null
	) {
		$this->permissions = $permissions;
		$this->logger      = $logger;

		// $settings injected when available; fall back to loading directly.
		if ( $settings ) {
			$this->settings = $settings;
		} else {
			require_once TR_AI_TOOLKIT_PATH . 'includes/class-settings.php';
			$this->settings = new TR_AI_Toolkit_Settings();
		}
	}

	public function register(): void {
		// PageSpeed.
		$this->register_run_pagespeed_analysis();
		$this->register_get_core_web_vitals();
		$this->register_compare_pagespeed_results();
		// Cache.
		$this->register_get_cache_plugin_status();
		$this->register_clear_page_cache();
		$this->register_clear_all_cache();
		$this->register_get_cache_health();
		// Images.
		$this->register_get_large_images();
		$this->register_get_image_details();
		$this->register_optimize_image();
		$this->register_convert_to_webp();
		$this->register_generate_image_variants();
	}

	// =========================================================================
	// PAGESPEED INSIGHTS
	// =========================================================================

	private function register_run_pagespeed_analysis(): void {
		wp_register_ability(
			'tr-ai-toolkit-run-pagespeed-analysis',
			array(
				'label'       => __( 'Run PageSpeed Analysis', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Runs Google PageSpeed Insights for a URL and returns performance score, Core Web Vitals (LCP, CLS, INP, FCP, TBT, Speed Index), field data, top opportunities, and diagnostics. Results are cached for 1 hour. Set a PageSpeed API key in plugin settings to avoid rate limits.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'url' ),
					'properties' => array(
						'url'           => array( 'type' => 'string',  'description' => 'Page URL to analyse. Must be a public URL.' ),
						'strategy'      => array( 'type' => 'string',  'description' => 'mobile or desktop. Default: mobile.' ),
						'force_refresh' => array( 'type' => 'boolean', 'description' => 'Bypass cache and fetch fresh data. Default: false.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_run_pagespeed_analysis( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_run_pagespeed_analysis( array $input ): array|\WP_Error {
		$url      = esc_url_raw( (string) ( $input['url'] ?? '' ) );
		$strategy = in_array( $input['strategy'] ?? 'mobile', array( 'mobile', 'desktop' ), true )
			? ( $input['strategy'] ?? 'mobile' )
			: 'mobile';
		$force    = ! empty( $input['force_refresh'] );

		if ( empty( $url ) ) {
			return new \WP_Error( 'invalid_param', __( 'url is required.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}
		if ( $this->is_private_url( $url ) ) {
			return new \WP_Error( 'forbidden_url', __( 'Private or local URLs are not allowed. Provide a publicly accessible URL.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$cache_key = self::PSI_KEY_PREFIX . substr( md5( $url . $strategy ), 0, 20 );

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				$cached['_cache'] = array( 'hit' => true, 'expires_in' => (int) ( get_option( '_transient_timeout_' . $cache_key, 0 ) - time() ) );
				$this->logger->log( 'tr-ai-toolkit-run-pagespeed-analysis', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
				return $cached;
			}
		}

		$result = $this->call_psi_api( $url, $strategy );
		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'tr-ai-toolkit-run-pagespeed-analysis', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return $result;
		}

		set_transient( $cache_key, $result, self::PSI_CACHE_TTL );
		$result['_cache'] = array( 'hit' => false, 'expires_in' => self::PSI_CACHE_TTL );

		$this->logger->log( 'tr-ai-toolkit-run-pagespeed-analysis', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return $result;
	}

	private function register_get_core_web_vitals(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-core-web-vitals',
			array(
				'label'       => __( 'Get Core Web Vitals', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns only Core Web Vitals for a URL: LCP, CLS, INP (when available), FCP, and their real-user field data ratings. Uses cached PageSpeed result when available.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'url' ),
					'properties' => array(
						'url'      => array( 'type' => 'string' ),
						'strategy' => array( 'type' => 'string', 'description' => 'mobile or desktop. Default: mobile.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_core_web_vitals( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_core_web_vitals( array $input ): array|\WP_Error {
		$full = $this->execute_run_pagespeed_analysis( $input );
		if ( is_wp_error( $full ) ) {
			return $full;
		}

		$this->logger->log( 'tr-ai-toolkit-get-core-web-vitals', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'url'          => $full['url'] ?? '',
			'strategy'     => $full['strategy'] ?? '',
			'lab_data'     => $full['lab_data'] ?? array(),
			'field_data'   => $full['field_data'] ?? array(),
			'overall_category' => $full['field_data']['overall_category'] ?? null,
			'note'         => __( 'Lab data is simulated (Lighthouse). Field data is real-user experience from Chrome UX Report — may not be available for low-traffic pages.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	private function register_compare_pagespeed_results(): void {
		wp_register_ability(
			'tr-ai-toolkit-compare-pagespeed-results',
			array(
				'label'       => __( 'Compare PageSpeed Results', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Runs PageSpeed Insights for a URL on both mobile and desktop and returns a side-by-side comparison of scores and Core Web Vitals.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'url' ),
					'properties' => array(
						'url'           => array( 'type' => 'string', 'description' => 'URL to compare across strategies.' ),
						'force_refresh' => array( 'type' => 'boolean' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_read() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_compare_pagespeed_results( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_compare_pagespeed_results( array $input ): array|\WP_Error {
		$force = ! empty( $input['force_refresh'] );

		$mobile  = $this->execute_run_pagespeed_analysis( array( 'url' => $input['url'] ?? '', 'strategy' => 'mobile',  'force_refresh' => $force ) );
		$desktop = $this->execute_run_pagespeed_analysis( array( 'url' => $input['url'] ?? '', 'strategy' => 'desktop', 'force_refresh' => $force ) );

		if ( is_wp_error( $mobile ) )  { return $mobile; }
		if ( is_wp_error( $desktop ) ) { return $desktop; }

		$this->logger->log( 'tr-ai-toolkit-compare-pagespeed-results', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'url'     => $input['url'] ?? '',
			'mobile'  => array(
				'score'    => $mobile['score'] ?? null,
				'lab_data' => $mobile['lab_data'] ?? array(),
			),
			'desktop' => array(
				'score'    => $desktop['score'] ?? null,
				'lab_data' => $desktop['lab_data'] ?? array(),
			),
			'gap_analysis' => $this->build_gap_analysis( $mobile['lab_data'] ?? array(), $desktop['lab_data'] ?? array() ),
		);
	}

	// =========================================================================
	// CACHE PLUGIN INTEGRATIONS
	// =========================================================================

	private function register_get_cache_plugin_status(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-cache-plugin-status',
			array(
				'label'       => __( 'Get Cache Plugin Status', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Detects the active cache plugin (WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache) and returns its status and version.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_cache_plugin_status( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_cache_plugin_status( array $input ): array {
		$detected = $this->detect_cache_plugin();

		$this->logger->log( 'tr-ai-toolkit-get-cache-plugin-status', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'detected_plugin' => $detected['slug'],
			'name'            => $detected['name'],
			'version'         => $detected['version'],
			'active'          => 'none' !== $detected['slug'],
			'can_clear_page'  => $detected['can_clear_page'],
			'can_clear_all'   => $detected['can_clear_all'],
			'note'            => 'none' === $detected['slug']
				? __( 'No supported cache plugin detected. Supported: WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache.', 'tr-wordpress-ai-toolkit' )
				: null,
		);
	}

	private function register_clear_page_cache(): void {
		wp_register_ability(
			'tr-ai-toolkit-clear-page-cache',
			array(
				'label'       => __( 'Clear Page Cache', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Clears the cache for a specific post or URL using the active cache plugin. Returns error if no supported cache plugin is installed.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'Post ID to clear cache for.' ),
						'url'     => array( 'type' => 'string',  'description' => 'URL to clear cache for. Used if post_id not supplied.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_clear_page_cache( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_clear_page_cache( array $input ): array|\WP_Error {
		$detected = $this->detect_cache_plugin();
		if ( 'none' === $detected['slug'] ) {
			return new \WP_Error( 'no_cache_plugin', __( 'No supported cache plugin is active.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 503 ) );
		}
		if ( ! $detected['can_clear_page'] ) {
			return new \WP_Error( 'not_supported', sprintf( __( '%s does not support per-page cache clearing via API.', 'tr-wordpress-ai-toolkit' ), $detected['name'] ), array( 'status' => 501 ) );
		}

		$post_id = absint( $input['post_id'] ?? 0 );
		$url     = esc_url_raw( (string) ( $input['url'] ?? '' ) );

		if ( ! $post_id && ! $url ) {
			return new \WP_Error( 'invalid_param', __( 'Provide post_id or url.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		if ( ! $post_id && $url ) {
			$post_id = url_to_postid( $url );
		}

		$cleared = $this->do_clear_page_cache( $detected['slug'], $post_id, $url );

		$this->logger->log( 'tr-ai-toolkit-clear-page-cache', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'cleared'       => $cleared,
			'plugin'        => $detected['name'],
			'post_id'       => $post_id ?: null,
			'url'           => $url ?: ( $post_id ? get_permalink( $post_id ) : null ),
		);
	}

	private function register_clear_all_cache(): void {
		wp_register_ability(
			'tr-ai-toolkit-clear-all-cache',
			array(
				'label'       => __( 'Clear All Cache', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Clears the entire site cache using the active cache plugin. Requires confirm: true. Returns error if no supported cache plugin is installed.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'confirm' ),
					'properties' => array(
						'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true to clear entire cache.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_clear_all_cache( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_clear_all_cache( array $input ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to clear the entire site cache. This will cause temporary slowness as pages rebuild.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$detected = $this->detect_cache_plugin();
		if ( 'none' === $detected['slug'] ) {
			return new \WP_Error( 'no_cache_plugin', __( 'No supported cache plugin is active.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 503 ) );
		}
		if ( ! $detected['can_clear_all'] ) {
			return new \WP_Error( 'not_supported', sprintf( __( '%s does not support full cache clearing via API.', 'tr-wordpress-ai-toolkit' ), $detected['name'] ), array( 'status' => 501 ) );
		}

		$this->do_clear_all_cache( $detected['slug'] );

		$this->logger->log( 'tr-ai-toolkit-clear-all-cache', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'cleared' => true,
			'plugin'  => $detected['name'],
			'note'    => __( 'Full cache cleared. Pages will be re-cached as visitors load them.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	private function register_get_cache_health(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-cache-health',
			array(
				'label'       => __( 'Get Cache Health', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns cache setup health: active plugin, estimated cached file count, cache directory size, and known configuration issues.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_cache_health( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_cache_health( array $input ): array {
		$detected   = $this->detect_cache_plugin();
		$cache_path = $this->get_cache_directory( $detected['slug'] );
		$dir_info   = $cache_path ? $this->get_directory_info( $cache_path ) : array( 'files' => null, 'size_bytes' => null );

		$issues = array();
		if ( 'none' === $detected['slug'] ) {
			$issues[] = __( 'No cache plugin detected. Consider installing WP Rocket, LiteSpeed Cache, W3 Total Cache, or WP Super Cache.', 'tr-wordpress-ai-toolkit' );
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && 'none' !== $detected['slug'] ) {
			$issues[] = __( 'WP_DEBUG is enabled. Some cache plugins do not cache when debug mode is on.', 'tr-wordpress-ai-toolkit' );
		}

		$this->logger->log( 'tr-ai-toolkit-get-cache-health', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'plugin'          => $detected['name'],
			'plugin_slug'     => $detected['slug'],
			'plugin_version'  => $detected['version'],
			'cache_directory' => $cache_path ? basename( $cache_path ) : null,
			'cached_files'    => $dir_info['files'],
			'cache_size_bytes' => $dir_info['size_bytes'],
			'cache_size_human' => isset( $dir_info['size_bytes'] ) && null !== $dir_info['size_bytes']
				? size_format( (int) $dir_info['size_bytes'] )
				: null,
			'issues'          => $issues,
		);
	}

	// =========================================================================
	// IMAGE OPTIMISATION
	// =========================================================================

	private function register_get_large_images(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-large-images',
			array(
				'label'       => __( 'Get Large Images', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns images in the media library above a file-size threshold, sorted by size descending. Reports dimensions, format, and file size. Useful for identifying optimisation candidates.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'min_size_kb' => array( 'type' => 'integer', 'description' => 'Minimum file size in KB. Default: 100.' ),
						'per_page'    => array( 'type' => 'integer', 'description' => 'Max results (1–100). Default: 20.' ),
						'page'        => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_upload() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_large_images( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_large_images( array $input ): array {
		$min_kb   = max( 1, (int) ( $input['min_size_kb'] ?? 100 ) );
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Fetch a broad set and filter by file size (filesize not easily queryable via meta).
		$query = new \WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' ),
			'posts_per_page' => 500,
			'fields'         => 'ids',
		) );

		$large = array();
		foreach ( $query->posts as $id ) {
			$meta      = wp_get_attachment_metadata( (int) $id );
			$file_path = get_attached_file( (int) $id );
			$size_bytes = null;

			// WP 6.0+ stores filesize in metadata.
			if ( is_array( $meta ) && isset( $meta['filesize'] ) ) {
				$size_bytes = (int) $meta['filesize'];
			} elseif ( $file_path && file_exists( $file_path ) ) {
				$size_bytes = (int) filesize( $file_path );
			}

			if ( null === $size_bytes || $size_bytes < $min_kb * 1024 ) {
				continue;
			}

			$large[] = array(
				'id'          => (int) $id,
				'title'       => get_the_title( (int) $id ),
				'url'         => wp_get_attachment_url( (int) $id ),
				'mime_type'   => get_post_mime_type( (int) $id ),
				'size_bytes'  => $size_bytes,
				'size_human'  => size_format( $size_bytes ),
				'width'       => is_array( $meta ) ? ( $meta['width'] ?? null ) : null,
				'height'      => is_array( $meta ) ? ( $meta['height'] ?? null ) : null,
			);
		}

		// Sort by size descending.
		usort( $large, fn( $a, $b ) => $b['size_bytes'] - $a['size_bytes'] );

		$total  = count( $large );
		$sliced = array_slice( $large, $offset, $per_page );

		$this->logger->log( 'tr-ai-toolkit-get-large-images', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'images'      => $sliced,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
			'min_size_kb' => $min_kb,
		);
	}

	private function register_get_image_details(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-image-details',
			array(
				'label'       => __( 'Get Image Details', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns optimisation-focused details for a single image: dimensions, file size, format, WebP availability, all generated sizes, and EXIF orientation.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'attachment_id' ),
					'properties' => array(
						'attachment_id' => array( 'type' => 'integer' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_upload() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_image_details( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_image_details( array $input ): array|\WP_Error {
		$id   = absint( $input['attachment_id'] ?? 0 );
		$post = $id ? get_post( $id ) : null;

		if ( ! $post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Image attachment not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		$meta      = wp_get_attachment_metadata( $id );
		$file_path = get_attached_file( $id );
		$size_bytes = null;

		if ( is_array( $meta ) && isset( $meta['filesize'] ) ) {
			$size_bytes = (int) $meta['filesize'];
		} elseif ( $file_path && file_exists( $file_path ) ) {
			$size_bytes = (int) filesize( $file_path );
		}

		$sizes = array();
		if ( is_array( $meta ) && isset( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size_name => $size_data ) {
				$sizes[ $size_name ] = array(
					'width'  => $size_data['width'] ?? null,
					'height' => $size_data['height'] ?? null,
					'mime'   => $size_data['mime-type'] ?? null,
					'file'   => $size_data['file'] ?? null,
				);
			}
		}

		$orientation = null;
		if ( is_array( $meta ) && isset( $meta['image_meta']['orientation'] ) ) {
			$orientation = (int) $meta['image_meta']['orientation'];
		}

		$webp_supported = $this->server_supports_webp();

		$this->logger->log( 'tr-ai-toolkit-get-image-details', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'id'             => $id,
			'title'          => $post->post_title,
			'url'            => wp_get_attachment_url( $id ),
			'mime_type'      => $post->post_mime_type,
			'file_size'      => $size_bytes,
			'file_size_human' => $size_bytes ? size_format( $size_bytes ) : null,
			'width'          => is_array( $meta ) ? ( $meta['width'] ?? null ) : null,
			'height'         => is_array( $meta ) ? ( $meta['height'] ?? null ) : null,
			'orientation'    => $orientation,
			'alt_text'       => get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: null,
			'generated_sizes' => $sizes,
			'server_webp_support' => $webp_supported,
			'optimisation_note' => $size_bytes && $size_bytes > 200 * 1024
				? __( 'File is over 200 KB. Consider optimising or converting to WebP.', 'tr-wordpress-ai-toolkit' )
				: null,
		);
	}

	private function register_optimize_image(): void {
		wp_register_ability(
			'tr-ai-toolkit-optimize-image',
			array(
				'label'       => __( 'Optimize Image', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a quality-reduced copy of an image using WordPress image editors (GD or Imagick). The original is preserved. Returns the new attachment ID, new file size, and actual savings. Note: this uses lossy compression — quality cannot be restored after optimisation.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'attachment_id' ),
					'properties' => array(
						'attachment_id' => array( 'type' => 'integer', 'description' => 'Image attachment ID.' ),
						'quality'       => array( 'type' => 'integer', 'description' => 'JPEG/WebP quality 1–100. Default: 82.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_upload() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_optimize_image( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_optimize_image( array $input ): array|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$id      = absint( $input['attachment_id'] ?? 0 );
		$quality = min( 100, max( 1, (int) ( $input['quality'] ?? 82 ) ) );

		$post = $id ? get_post( $id ) : null;
		if ( ! $post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Image attachment not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot edit this attachment.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$file_path = get_attached_file( $id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_missing', __( 'Source image file not found on disk.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		$original_size = (int) filesize( $file_path );
		$mime_type     = $post->post_mime_type;

		// Save a new optimised copy (do not replace original).
		$new_path = $this->make_copy_path( $file_path, '-optimized' );
		if ( ! copy( $file_path, $new_path ) ) {
			return new \WP_Error( 'copy_failed', __( 'Could not create a copy of the image.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 500 ) );
		}

		$editor = wp_get_image_editor( $new_path );
		if ( is_wp_error( $editor ) ) {
			@unlink( $new_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new \WP_Error( 'editor_error', $editor->get_error_message(), array( 'status' => 500 ) );
		}

		$editor->set_quality( $quality );
		$saved = $editor->save( $new_path, $mime_type );

		if ( is_wp_error( $saved ) ) {
			@unlink( $new_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $saved;
		}

		$new_size     = (int) filesize( $new_path );
		$savings      = $original_size - $new_size;
		$savings_pct  = $original_size > 0 ? round( ( $savings / $original_size ) * 100, 1 ) : 0.0;

		// Register as new attachment.
		$new_id = $this->register_attachment( $new_path, $mime_type, $post->post_parent );

		$this->logger->log( 'tr-ai-toolkit-optimize-image', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'original_id'       => $id,
			'optimized_id'      => $new_id,
			'optimized_url'     => $new_id ? wp_get_attachment_url( $new_id ) : null,
			'original_size'     => $original_size,
			'new_size'          => $new_size,
			'savings_bytes'     => $savings,
			'savings_percent'   => $savings_pct,
			'quality_used'      => $quality,
			'compression_type'  => 'lossy',
			'note'              => __( 'Original image preserved. Optimised copy created as a new media attachment.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	private function register_convert_to_webp(): void {
		wp_register_ability(
			'tr-ai-toolkit-convert-to-webp',
			array(
				'label'       => __( 'Convert Image to WebP', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Converts an image attachment to WebP format using the available image editor (Imagick or GD). The original is preserved. Returns error if WebP is not supported by the server.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'attachment_id' ),
					'properties' => array(
						'attachment_id' => array( 'type' => 'integer' ),
						'quality'       => array( 'type' => 'integer', 'description' => 'WebP quality 1–100. Default: 80.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_upload() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_convert_to_webp( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_convert_to_webp( array $input ): array|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		if ( ! $this->server_supports_webp() ) {
			return new \WP_Error(
				'webp_not_supported',
				__( 'The server does not support WebP output. Install Imagick or ensure GD was compiled with WebP support (--with-webp-dir).', 'tr-wordpress-ai-toolkit' ),
				array( 'status' => 501 )
			);
		}

		$id      = absint( $input['attachment_id'] ?? 0 );
		$quality = min( 100, max( 1, (int) ( $input['quality'] ?? 80 ) ) );

		$post = $id ? get_post( $id ) : null;
		if ( ! $post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Image attachment not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot edit this attachment.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}
		if ( 'image/webp' === $post->post_mime_type ) {
			return new \WP_Error( 'already_webp', __( 'Image is already in WebP format.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$file_path = get_attached_file( $id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_missing', __( 'Source image file not found on disk.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		$original_size = (int) filesize( $file_path );
		$editor        = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			return new \WP_Error( 'editor_error', $editor->get_error_message(), array( 'status' => 500 ) );
		}

		$editor->set_quality( $quality );
		$webp_path = preg_replace( '/\.(jpe?g|png|gif|bmp|avif)$/i', '.webp', $file_path );
		if ( $webp_path === $file_path ) {
			$webp_path = $file_path . '.webp';
		}

		$saved = $editor->save( $webp_path, 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$new_size    = file_exists( $webp_path ) ? (int) filesize( $webp_path ) : 0;
		$savings     = $original_size - $new_size;
		$savings_pct = $original_size > 0 ? round( ( $savings / $original_size ) * 100, 1 ) : 0.0;

		$new_id = $this->register_attachment( $webp_path, 'image/webp', $post->post_parent );

		$this->logger->log( 'tr-ai-toolkit-convert-to-webp', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'original_id'    => $id,
			'webp_id'        => $new_id,
			'webp_url'       => $new_id ? wp_get_attachment_url( $new_id ) : null,
			'original_size'  => $original_size,
			'webp_size'      => $new_size,
			'savings_bytes'  => $savings,
			'savings_percent' => $savings_pct,
			'quality_used'   => $quality,
			'note'           => __( 'Original preserved. WebP registered as a new media attachment.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	private function register_generate_image_variants(): void {
		wp_register_ability(
			'tr-ai-toolkit-generate-image-variants',
			array(
				'label'       => __( 'Generate Image Variants', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Regenerates all WordPress thumbnail sizes for an image attachment. Useful after changing image size settings or adding new sizes via code.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'attachment_id' ),
					'properties' => array(
						'attachment_id' => array( 'type' => 'integer' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_upload() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_generate_image_variants( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_generate_image_variants( array $input ): array|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$id   = absint( $input['attachment_id'] ?? 0 );
		$post = $id ? get_post( $id ) : null;

		if ( ! $post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Image attachment not found.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot edit this attachment.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$file_path = get_attached_file( $id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_missing', __( 'Source image file not found on disk.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 404 ) );
		}

		$metadata = wp_generate_attachment_metadata( $id, $file_path );
		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}

		wp_update_attachment_metadata( $id, $metadata );

		$sizes_generated = isset( $metadata['sizes'] ) ? array_keys( $metadata['sizes'] ) : array();

		$this->logger->log( 'tr-ai-toolkit-generate-image-variants', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'attachment_id'   => $id,
			'sizes_generated' => $sizes_generated,
			'total_sizes'     => count( $sizes_generated ),
		);
	}

	// =========================================================================
	// Module interface
	// =========================================================================

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-run-pagespeed-analysis',
			'tr-ai-toolkit-get-core-web-vitals',
			'tr-ai-toolkit-compare-pagespeed-results',
			'tr-ai-toolkit-get-cache-plugin-status',
			'tr-ai-toolkit-clear-page-cache',
			'tr-ai-toolkit-clear-all-cache',
			'tr-ai-toolkit-get-cache-health',
			'tr-ai-toolkit-get-large-images',
			'tr-ai-toolkit-get-image-details',
			'tr-ai-toolkit-optimize-image',
			'tr-ai-toolkit-convert-to-webp',
			'tr-ai-toolkit-generate-image-variants',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-run-pagespeed-analysis'  => $this->execute_run_pagespeed_analysis( $args ),
			'tr-ai-toolkit-get-core-web-vitals'     => $this->execute_get_core_web_vitals( $args ),
			'tr-ai-toolkit-compare-pagespeed-results' => $this->execute_compare_pagespeed_results( $args ),
			'tr-ai-toolkit-get-cache-plugin-status' => $this->execute_get_cache_plugin_status( $args ),
			'tr-ai-toolkit-clear-page-cache'        => $this->execute_clear_page_cache( $args ),
			'tr-ai-toolkit-clear-all-cache'         => $this->execute_clear_all_cache( $args ),
			'tr-ai-toolkit-get-cache-health'        => $this->execute_get_cache_health( $args ),
			'tr-ai-toolkit-get-large-images'        => $this->execute_get_large_images( $args ),
			'tr-ai-toolkit-get-image-details'       => $this->execute_get_image_details( $args ),
			'tr-ai-toolkit-optimize-image'          => $this->execute_optimize_image( $args ),
			'tr-ai-toolkit-convert-to-webp'         => $this->execute_convert_to_webp( $args ),
			'tr-ai-toolkit-generate-image-variants' => $this->execute_generate_image_variants( $args ),
			default                                 => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-run-pagespeed-analysis',
				'description' => 'Runs Google PageSpeed Insights for a URL. Returns performance score, LCP, CLS, INP, FCP, TBT, Speed Index, field data, top opportunities and diagnostics. Results cached 1 hour.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'url' ),
					'properties' => array(
						'url'           => array( 'type' => 'string' ),
						'strategy'      => array( 'type' => 'string', 'enum' => array( 'mobile', 'desktop' ) ),
						'force_refresh' => array( 'type' => 'boolean' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-core-web-vitals',
				'description' => 'Returns Core Web Vitals only (LCP, CLS, INP, FCP) with lab data and real-user field data.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'url' ),
					'properties' => array( 'url' => array( 'type' => 'string' ), 'strategy' => array( 'type' => 'string' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-compare-pagespeed-results',
				'description' => 'Runs PageSpeed for a URL on both mobile and desktop and returns a side-by-side comparison of scores and Core Web Vitals.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'url' ),
					'properties' => array( 'url' => array( 'type' => 'string' ), 'force_refresh' => array( 'type' => 'boolean' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-cache-plugin-status',
				'description' => 'Detects active cache plugin (WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache) and returns version and clear capabilities.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-clear-page-cache',
				'description' => 'Clears cache for a specific post ID or URL using the active cache plugin.',
				'inputSchema' => array(
					'type' => 'object',
					'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'url' => array( 'type' => 'string' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-clear-all-cache',
				'description' => 'Clears the entire site cache. Requires confirm: true.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'confirm' ),
					'properties' => array( 'confirm' => array( 'type' => 'boolean' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-cache-health',
				'description' => 'Returns cache setup health: active plugin, cache directory size, estimated file count, and configuration issues.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-large-images',
				'description' => 'Returns images above a file-size threshold sorted by size. Useful for identifying optimisation candidates.',
				'inputSchema' => array(
					'type' => 'object',
					'properties' => array(
						'min_size_kb' => array( 'type' => 'integer' ),
						'per_page'    => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-image-details',
				'description' => 'Returns optimisation details for one image: dimensions, file size, format, WebP server support, generated sizes, EXIF orientation.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'attachment_id' ),
					'properties' => array( 'attachment_id' => array( 'type' => 'integer' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-optimize-image',
				'description' => 'Creates a quality-reduced (lossy) copy of an image. Original is preserved. Returns new attachment ID and file-size savings.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'attachment_id' ),
					'properties' => array( 'attachment_id' => array( 'type' => 'integer' ), 'quality' => array( 'type' => 'integer' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-convert-to-webp',
				'description' => 'Converts an image to WebP format. Original preserved. Returns error if server lacks WebP support.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'attachment_id' ),
					'properties' => array( 'attachment_id' => array( 'type' => 'integer' ), 'quality' => array( 'type' => 'integer' ) ),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-generate-image-variants',
				'description' => 'Regenerates all WordPress thumbnail sizes for an image attachment.',
				'inputSchema' => array(
					'type' => 'object', 'required' => array( 'attachment_id' ),
					'properties' => array( 'attachment_id' => array( 'type' => 'integer' ) ),
				),
			),
		);
	}

	// =========================================================================
	// Private helpers — PageSpeed
	// =========================================================================

	/**
	 * Calls the Google PageSpeed Insights API and returns a normalised result.
	 *
	 * @param string $url      Public page URL.
	 * @param string $strategy 'mobile' or 'desktop'.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function call_psi_api( string $url, string $strategy ): array|\WP_Error {
		$api_key = (string) $this->settings->get( 'integrations.pagespeed_api_key', '' );

		$query_args = array(
			'url'      => $url,
			'strategy' => $strategy,
			'category' => 'performance',
		);
		if ( '' !== $api_key ) {
			$query_args['key'] = $api_key;
		}

		$api_url  = add_query_arg( $query_args, self::PSI_ENDPOINT );
		$response = wp_remote_get( $api_url, array(
			'timeout'    => 60,
			'user-agent' => 'TR-AI-Toolkit/' . TR_AI_TOOLKIT_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
		) );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_error', sprintf( __( 'PageSpeed API request failed: %s', 'tr-wordpress-ai-toolkit' ), $response->get_error_message() ), array( 'status' => 502 ) );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( 200 !== $http_code ) {
			$error_msg = $data['error']['message'] ?? "HTTP {$http_code}";
			return new \WP_Error( 'api_error', sprintf( __( 'PageSpeed API error: %s', 'tr-wordpress-ai-toolkit' ), $error_msg ), array( 'status' => 502 ) );
		}

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'api_error', __( 'PageSpeed API returned invalid JSON.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 502 ) );
		}

		return $this->parse_psi_response( $data, $url, $strategy );
	}

	/**
	 * Parses a raw PSI API response into a clean structure.
	 *
	 * @param array<string, mixed> $data     Decoded API response.
	 * @param string               $url
	 * @param string               $strategy
	 * @return array<string, mixed>
	 */
	private function parse_psi_response( array $data, string $url, string $strategy ): array {
		$lhr    = $data['lighthouseResult'] ?? array();
		$audits = $lhr['audits'] ?? array();

		$score = isset( $lhr['categories']['performance']['score'] )
			? (int) round( (float) $lhr['categories']['performance']['score'] * 100 )
			: null;

		$metric = fn( string $id ) => isset( $audits[ $id ] ) ? array(
			'display_value' => $audits[ $id ]['displayValue'] ?? null,
			'numeric_ms'    => isset( $audits[ $id ]['numericValue'] ) ? (int) $audits[ $id ]['numericValue'] : null,
			'score'         => isset( $audits[ $id ]['score'] ) ? (float) $audits[ $id ]['score'] : null,
		) : null;

		$lab_data = array(
			'lcp'          => $metric( 'largest-contentful-paint' ),
			'cls'          => $metric( 'cumulative-layout-shift' ),
			'fcp'          => $metric( 'first-contentful-paint' ),
			'tbt'          => $metric( 'total-blocking-time' ),
			'speed_index'  => $metric( 'speed-index' ),
			'inp'          => $metric( 'interaction-to-next-paint' ),
		);

		// Remove null INP — it's often unavailable in lab mode.
		if ( null === $lab_data['inp'] ) {
			$lab_data['inp_note'] = 'INP not available in lab data for this page.';
		}

		// Field data (Chrome UX Report).
		$field = $data['loadingExperience']['metrics'] ?? array();
		$field_data = array(
			'overall_category'  => $data['loadingExperience']['overall_category'] ?? null,
			'lcp_percentile_ms' => $field['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ?? null,
			'cls_percentile'    => isset( $field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] )
				? round( $field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] / 100, 3 )
				: null,
			'inp_percentile_ms' => $field['INTERACTION_TO_NEXT_PAINT']['percentile'] ?? null,
			'fcp_percentile_ms' => $field['FIRST_CONTENTFUL_PAINT_MS']['percentile'] ?? null,
			'note'              => empty( $field )
				? 'No field data available — Chrome UX Report may not have enough data for this URL.'
				: null,
		);

		// Top opportunities (sorted by estimated savings, largest first).
		$opportunities = array();
		foreach ( $audits as $audit ) {
			if ( ( $audit['details']['type'] ?? '' ) === 'opportunity' && ( $audit['details']['overallSavingsMs'] ?? 0 ) > 0 ) {
				$opportunities[] = array(
					'id'           => $audit['id'] ?? '',
					'title'        => $audit['title'] ?? '',
					'savings_ms'   => (int) ( $audit['details']['overallSavingsMs'] ?? 0 ),
					'display_value' => $audit['displayValue'] ?? null,
				);
			}
		}
		usort( $opportunities, fn( $a, $b ) => $b['savings_ms'] - $a['savings_ms'] );
		$opportunities = array_slice( $opportunities, 0, 5 );

		// Diagnostics — failed audits that are not opportunities.
		$opp_ids    = array_column( $opportunities, 'id' );
		$diagnostics = array();
		foreach ( $audits as $audit ) {
			$score_val = $audit['score'] ?? null;
			if ( null !== $score_val && (float) $score_val < 1.0 && ! in_array( $audit['id'] ?? '', $opp_ids, true ) ) {
				$diagnostics[] = array(
					'id'    => $audit['id'] ?? '',
					'title' => $audit['title'] ?? '',
					'score' => (float) $score_val,
				);
			}
		}
		usort( $diagnostics, fn( $a, $b ) => $a['score'] <=> $b['score'] );
		$diagnostics = array_slice( $diagnostics, 0, 10 );

		return array(
			'url'           => $url,
			'strategy'      => $strategy,
			'score'         => $score,
			'lab_data'      => $lab_data,
			'field_data'    => $field_data,
			'opportunities' => $opportunities,
			'diagnostics'   => $diagnostics,
			'fetched_at'    => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Builds a gap analysis comparing mobile vs desktop lab metrics.
	 *
	 * @param array<string, mixed> $mobile_lab
	 * @param array<string, mixed> $desktop_lab
	 * @return array<string, mixed>
	 */
	private function build_gap_analysis( array $mobile_lab, array $desktop_lab ): array {
		$gap = array();
		foreach ( array( 'lcp', 'cls', 'fcp', 'tbt', 'speed_index' ) as $key ) {
			$m = $mobile_lab[ $key ]['numeric_ms'] ?? null;
			$d = $desktop_lab[ $key ]['numeric_ms'] ?? null;
			if ( null !== $m && null !== $d ) {
				$gap[ $key ] = array(
					'mobile'       => $m,
					'desktop'      => $d,
					'difference'   => $m - $d,
					'mobile_worse' => $m > $d,
				);
			}
		}
		return $gap;
	}

	// =========================================================================
	// Private helpers — Cache
	// =========================================================================

	/**
	 * Detects the active cache plugin.
	 *
	 * @return array{slug: string, name: string, version: string, can_clear_page: bool, can_clear_all: bool}
	 */
	private function detect_cache_plugin(): array {
		if ( defined( 'WP_ROCKET_VERSION' ) || function_exists( 'rocket_clean_domain' ) ) {
			return array(
				'slug'           => 'wp-rocket',
				'name'           => 'WP Rocket',
				'version'        => defined( 'WP_ROCKET_VERSION' ) ? WP_ROCKET_VERSION : '',
				'can_clear_page' => function_exists( 'rocket_clean_post' ),
				'can_clear_all'  => function_exists( 'rocket_clean_domain' ),
			);
		}

		if ( defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed_Cache' ) ) {
			return array(
				'slug'           => 'litespeed',
				'name'           => 'LiteSpeed Cache',
				'version'        => defined( 'LSCWP_V' ) ? LSCWP_V : '',
				'can_clear_page' => true,
				'can_clear_all'  => true,
			);
		}

		if ( defined( 'W3TC_VERSION' ) || function_exists( 'w3tc_flush_all' ) ) {
			return array(
				'slug'           => 'w3tc',
				'name'           => 'W3 Total Cache',
				'version'        => defined( 'W3TC_VERSION' ) ? W3TC_VERSION : '',
				'can_clear_page' => function_exists( 'w3tc_flush_post' ),
				'can_clear_all'  => function_exists( 'w3tc_flush_all' ),
			);
		}

		if ( defined( 'WPCACHEHOME' ) || function_exists( 'wp_cache_clear_cache' ) ) {
			return array(
				'slug'           => 'wp-super-cache',
				'name'           => 'WP Super Cache',
				'version'        => defined( 'WPCACHE_VERSION' ) ? WPCACHE_VERSION : '',
				'can_clear_page' => false,
				'can_clear_all'  => function_exists( 'wp_cache_clear_cache' ),
			);
		}

		return array(
			'slug'           => 'none',
			'name'           => 'None detected',
			'version'        => '',
			'can_clear_page' => false,
			'can_clear_all'  => false,
		);
	}

	private function do_clear_page_cache( string $plugin_slug, int $post_id, string $url ): bool {
		switch ( $plugin_slug ) {
			case 'wp-rocket':
				if ( $post_id && function_exists( 'rocket_clean_post' ) ) {
					rocket_clean_post( $post_id );
					return true;
				}
				break;
			case 'litespeed':
				if ( $post_id ) {
					do_action( 'litespeed_purge_post', $post_id );
				} elseif ( $url ) {
					do_action( 'litespeed_purge_url', $url );
				}
				return true;
			case 'w3tc':
				if ( $post_id && function_exists( 'w3tc_flush_post' ) ) {
					w3tc_flush_post( $post_id );
					return true;
				}
				if ( $url && function_exists( 'w3tc_flush_url' ) ) {
					w3tc_flush_url( $url );
					return true;
				}
				break;
		}
		return false;
	}

	private function do_clear_all_cache( string $plugin_slug ): void {
		switch ( $plugin_slug ) {
			case 'wp-rocket':
				if ( function_exists( 'rocket_clean_domain' ) ) {
					rocket_clean_domain();
				}
				break;
			case 'litespeed':
				do_action( 'litespeed_purge_all' );
				break;
			case 'w3tc':
				if ( function_exists( 'w3tc_flush_all' ) ) {
					w3tc_flush_all();
				}
				break;
			case 'wp-super-cache':
				if ( function_exists( 'wp_cache_clear_cache' ) ) {
					wp_cache_clear_cache();
				}
				break;
		}
	}

	/**
	 * Returns the cache directory path for the detected plugin, or null.
	 */
	private function get_cache_directory( string $plugin_slug ): ?string {
		switch ( $plugin_slug ) {
			case 'wp-rocket':
				return defined( 'WP_ROCKET_CACHE_PATH' ) ? WP_ROCKET_CACHE_PATH : WP_CONTENT_DIR . '/cache/wp-rocket/';
			case 'litespeed':
				return WP_CONTENT_DIR . '/cache/litespeed/';
			case 'w3tc':
				return WP_CONTENT_DIR . '/cache/';
			case 'wp-super-cache':
				return defined( 'WPCACHEHOME' ) ? WP_CONTENT_DIR . '/cache/' : null;
		}
		return null;
	}

	/**
	 * Returns file count and total size of a directory (non-recursive for speed).
	 *
	 * @return array{files: int|null, size_bytes: int|null}
	 */
	private function get_directory_info( string $path ): array {
		if ( ! is_dir( $path ) ) {
			return array( 'files' => 0, 'size_bytes' => 0 );
		}

		$total_size  = 0;
		$total_files = 0;
		$limit       = 5000;

		try {
			$iter = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iter as $file ) {
				if ( $file->isFile() ) {
					$total_size  += $file->getSize();
					++$total_files;
					if ( $total_files >= $limit ) {
						break;
					}
				}
			}
		} catch ( \Exception $e ) {
			return array( 'files' => null, 'size_bytes' => null );
		}

		return array( 'files' => $total_files, 'size_bytes' => $total_size );
	}

	// =========================================================================
	// Private helpers — Images
	// =========================================================================

	/**
	 * Returns true if the server's WP image editors support WebP output.
	 */
	private function server_supports_webp(): bool {
		return wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	}

	/**
	 * Builds a destination path for a copy of a file (adds suffix before extension).
	 */
	private function make_copy_path( string $file_path, string $suffix ): string {
		$ext      = pathinfo( $file_path, PATHINFO_EXTENSION );
		$basename = pathinfo( $file_path, PATHINFO_FILENAME );
		$dir      = dirname( $file_path );
		$counter  = 1;
		do {
			$name = $counter > 1
				? "{$basename}{$suffix}-{$counter}.{$ext}"
				: "{$basename}{$suffix}.{$ext}";
			$path = $dir . '/' . $name;
			++$counter;
		} while ( file_exists( $path ) && $counter < 100 );

		return $path;
	}

	/**
	 * Registers a new WordPress attachment for a file already on disk.
	 *
	 * @return int|null New attachment ID, or null on failure.
	 */
	private function register_attachment( string $file_path, string $mime_type, int $parent_post_id = 0 ): ?int {
		$upload_dir = wp_upload_dir();
		$rel_path   = str_replace( trailingslashit( $upload_dir['basedir'] ), '', $file_path );

		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_file_name( pathinfo( $file_path, PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
			'guid'           => $upload_dir['baseurl'] . '/' . $rel_path,
		);

		$id = wp_insert_attachment( $attachment, $file_path, $parent_post_id, true );
		if ( is_wp_error( $id ) ) {
			return null;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_generate_attachment_metadata( $id, $file_path );
		wp_update_attachment_metadata( $id, $meta );

		return $id;
	}

	/**
	 * Returns true if a URL resolves to a private/loopback address.
	 * Prevents SSRF when calling the PageSpeed API with untrusted URLs.
	 */
	private function is_private_url( string $url ): bool {
		$parsed = wp_parse_url( $url );
		$host   = $parsed['host'] ?? '';
		if ( '' === $host ) {
			return true;
		}
		$ip = gethostbyname( $host );
		return false === filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}
}
