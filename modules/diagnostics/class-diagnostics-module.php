<?php
/**
 * Diagnostics module.
 *
 * Covers six areas:
 *  1. Server info — web server, PHP, OS, WordPress and database environment.
 *  2. PHP configuration — ini values, loaded extensions, OPcache status.
 *  3. Database stats — table sizes, row counts, WP vs non-WP tables.
 *  4. Disk usage — uploads, plugins, themes and total wp-content sizes.
 *  5. Error log — tail the active PHP/WordPress error log with optional search.
 *  6. Scheduled tasks — WordPress cron event list with schedule metadata.
 *
 * Security notes:
 *  - All tools require admin capability.
 *  - Error log returns basename only, never the full filesystem path.
 *  - Server constants (DB_HOST, DB_NAME) are already in wp-config.php;
 *    exposing them to an authenticated admin is acceptable.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Server diagnostics MCP tools.
 */
class TR_AI_Toolkit_Diagnostics_Module {

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
		$this->register_get_server_info();
		$this->register_get_php_configuration();
		$this->register_get_database_stats();
		$this->register_get_disk_usage();
		$this->register_get_error_log();
		$this->register_get_scheduled_tasks();
	}

	// =========================================================================
	// SERVER INFO
	// =========================================================================

	private function register_get_server_info(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-server-info',
			array(
				'label'         => __( 'Get Server Info', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Returns a full snapshot of the server and WordPress environment: web server software, PHP version and SAPI, OS, database version, WordPress version, memory limits, debug flags, and SSL status.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_server_info( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_server_info( array $input ): array {
		global $wpdb;

		$this->logger->log( 'tr-ai-toolkit-get-server-info', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'web_server'        => sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ),
			'php_version'       => PHP_VERSION,
			'php_sapi'          => PHP_SAPI,
			'os'                => PHP_OS_FAMILY,
			'os_detail'         => php_uname( 's' ) . ' ' . php_uname( 'r' ),
			'server_hostname'   => sanitize_text_field( php_uname( 'n' ) ),
			'wp_root'           => ABSPATH,
			'wp_content_dir'    => WP_CONTENT_DIR,
			'wp_version'        => get_bloginfo( 'version' ),
			'db_version'        => $wpdb->db_version(),
			'db_host'           => DB_HOST,
			'db_name'           => DB_NAME,
			'site_url'          => get_site_url(),
			'is_multisite'      => is_multisite(),
			'php_memory_limit'  => ini_get( 'memory_limit' ),
			'php_max_execution' => ini_get( 'max_execution_time' ) === '-1' ? 'unlimited' : ini_get( 'max_execution_time' ) . 's',
			'php_upload_max'    => ini_get( 'upload_max_filesize' ),
			'php_post_max'      => ini_get( 'post_max_size' ),
			'wp_memory_limit'   => WP_MEMORY_LIMIT,
			'wp_max_memory'     => WP_MAX_MEMORY_LIMIT,
			'wp_debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log'      => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'wp_debug_display'  => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'https'             => is_ssl(),
		);
	}

	// =========================================================================
	// PHP CONFIGURATION
	// =========================================================================

	private function register_get_php_configuration(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-php-configuration',
			array(
				'label'         => __( 'Get PHP Configuration', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Returns PHP ini values relevant to WordPress performance and security, a full sorted list of loaded extensions, a quick-check of key extensions (curl, gd, imagick, etc.), and OPcache memory/hit-rate statistics.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_php_configuration( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_php_configuration( array $input ): array {
		// Key ini values relevant to WordPress.
		$php_ini_values = array(
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'max_input_vars'     => ini_get( 'max_input_vars' ),
			'post_max_size'      => ini_get( 'post_max_size' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'date.timezone'      => ini_get( 'date.timezone' ),
			'error_reporting'    => ini_get( 'error_reporting' ),
		);

		// Key extensions.
		$key_ext_names  = array( 'curl', 'gd', 'imagick', 'intl', 'mbstring', 'openssl', 'zip', 'zlib', 'opcache' );
		$key_extensions = array();
		foreach ( $key_ext_names as $ext ) {
			$key_extensions[ $ext ] = extension_loaded( $ext );
		}

		// All loaded extensions — sorted.
		$all_extensions = get_loaded_extensions();
		sort( $all_extensions );

		// OPcache status.
		$opcache = array( 'enabled' => false );
		if ( function_exists( 'opcache_get_status' ) ) {
			$status = opcache_get_status( false );
			if ( is_array( $status ) && ! empty( $status['opcache_enabled'] ) ) {
				$mem              = $status['memory_usage'] ?? array();
				$used_bytes       = (int) ( $mem['used_memory'] ?? 0 );
				$free_bytes       = (int) ( $mem['free_memory'] ?? 0 );
				$stats            = $status['opcache_statistics'] ?? array();
				$hits             = (int) ( $stats['hits'] ?? 0 );
				$misses           = (int) ( $stats['misses'] ?? 0 );
				$total_requests   = $hits + $misses;
				$hit_rate         = $total_requests > 0
					? round( ( $hits / $total_requests ) * 100, 2 )
					: 0.0;

				$opcache = array(
					'enabled'      => true,
					'memory_used'  => $used_bytes,
					'memory_free'  => $free_bytes,
					'hit_rate'     => $hit_rate,
					'hits'         => $hits,
					'misses'       => $misses,
					'cached_files' => (int) ( $stats['num_cached_scripts'] ?? 0 ),
				);
			}
		}

		$this->logger->log( 'tr-ai-toolkit-get-php-configuration', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'php_version'    => PHP_VERSION,
			'php_ini_values' => $php_ini_values,
			'key_extensions' => $key_extensions,
			'all_extensions' => $all_extensions,
			'opcache'        => $opcache,
		);
	}

	// =========================================================================
	// DATABASE STATS
	// =========================================================================

	private function register_get_database_stats(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-database-stats',
			array(
				'label'         => __( 'Get Database Stats', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Returns every table in the WordPress database with its size in KB and row count, the total database size, and separates WordPress-prefixed tables from other tables.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_database_stats( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_database_stats( array $input ): array {
		global $wpdb;

		$tables = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT table_name AS `name`,
				        ROUND( (data_length + index_length) / 1024, 2 ) AS size_kb,
				        table_rows AS `rows`
				 FROM information_schema.TABLES
				 WHERE table_schema = %s
				 ORDER BY (data_length + index_length) DESC",
				DB_NAME
			)
		);

		$total_kb     = array_sum( array_column( (array) $tables, 'size_kb' ) );
		$wp_tables    = array_values( array_filter( (array) $tables, fn( $t ) => str_starts_with( $t->name, $wpdb->prefix ) ) );
		$other_tables = array_values( array_filter( (array) $tables, fn( $t ) => ! str_starts_with( $t->name, $wpdb->prefix ) ) );

		$this->logger->log( 'tr-ai-toolkit-get-database-stats', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'database_name' => DB_NAME,
			'total_size_kb' => round( $total_kb, 2 ),
			'total_size_mb' => round( $total_kb / 1024, 2 ),
			'table_count'   => count( (array) $tables ),
			'wp_tables'     => $wp_tables,
			'other_tables'  => $other_tables,
		);
	}

	// =========================================================================
	// DISK USAGE
	// =========================================================================

	private function register_get_disk_usage(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-disk-usage',
			array(
				'label'         => __( 'Get Disk Usage', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Reports disk usage for key WordPress directories: uploads, plugins, themes, and total wp-content. Returns size in bytes and MB, plus file count for each directory. Large directories are capped at 10,000 files for performance.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_disk_usage( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_disk_usage( array $input ): array {
		$upload_dir   = wp_upload_dir();
		$uploads_path = $upload_dir['basedir'];
		$plugins_path = WP_CONTENT_DIR . '/plugins';
		$themes_path  = WP_CONTENT_DIR . '/themes';
		$content_path = WP_CONTENT_DIR;

		$build = function ( string $path ): array {
			$info      = $this->get_dir_size( $path );
			$bytes     = $info['size_bytes'];
			return array(
				'path'       => $path,
				'size_bytes' => $bytes,
				'size_mb'    => round( $bytes / 1048576, 2 ),
				'file_count' => $info['file_count'],
			);
		};

		$this->logger->log( 'tr-ai-toolkit-get-disk-usage', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'uploads'    => $build( $uploads_path ),
			'plugins'    => $build( $plugins_path ),
			'themes'     => $build( $themes_path ),
			'wp_content' => $build( $content_path ),
		);
	}

	// =========================================================================
	// ERROR LOG
	// =========================================================================

	private function register_get_error_log(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-error-log',
			array(
				'label'         => __( 'Get Error Log', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Reads the last N lines from the active PHP/WordPress error log. Supports an optional search string to filter lines. Returns the log file size, lines returned, and each line sanitized. The full log path is never returned — only the filename.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array(
					'type'       => 'object',
					'properties' => array(
						'lines'  => array( 'type' => 'integer', 'description' => __( 'Number of lines to return from the end of the log. Default: 50. Max: 200.', 'tr-wordpress-ai-toolkit' ) ),
						'search' => array( 'type' => 'string',  'description' => __( 'Optional text filter — only lines containing this string are returned.', 'tr-wordpress-ai-toolkit' ) ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_error_log( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_error_log( array $input ): array {
		$lines_requested = min( 200, max( 1, absint( $input['lines'] ?? 50 ) ) );
		$search          = sanitize_text_field( (string) ( $input['search'] ?? '' ) );

		// Determine log path.
		$log_path = '';
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) {
			$log_path = WP_DEBUG_LOG;
		} elseif ( defined( 'WP_DEBUG_LOG' ) && true === WP_DEBUG_LOG ) {
			$log_path = WP_CONTENT_DIR . '/debug.log';
		} else {
			$log_path = (string) ini_get( 'error_log' );
		}

		if ( '' === $log_path || ! file_exists( $log_path ) || ! is_readable( $log_path ) ) {
			$this->logger->log( 'tr-ai-toolkit-get-error-log', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
			return array(
				'available' => false,
				'message'   => __( 'No error log found. Enable WP_DEBUG_LOG in wp-config.php or check php.ini error_log directive.', 'tr-wordpress-ai-toolkit' ),
			);
		}

		$file_size = (int) filesize( $log_path );

		// For large files use SplFileObject to avoid loading the whole file.
		if ( $file_size > 5 * 1024 * 1024 ) {
			$lines_raw = $this->read_last_lines_spl( $log_path, $lines_requested * 10 );
		} else {
			$lines_raw = file( $log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
			if ( false === $lines_raw ) {
				$lines_raw = array();
			}
		}

		// Apply search filter.
		if ( '' !== $search ) {
			$lines_raw = array_values(
				array_filter( $lines_raw, fn( string $line ) => false !== strpos( $line, $search ) )
			);
		}

		// Take last N lines.
		$lines_raw = array_slice( $lines_raw, - $lines_requested );

		// Sanitize.
		$lines = array_map( fn( string $line ) => sanitize_text_field( $line ), $lines_raw );

		$this->logger->log( 'tr-ai-toolkit-get-error-log', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'available'     => true,
			'log_filename'  => basename( $log_path ),
			'file_size_kb'  => round( $file_size / 1024, 2 ),
			'lines_returned' => count( $lines ),
			'search_filter' => '' !== $search ? $search : null,
			'lines'         => $lines,
		);
	}

	// =========================================================================
	// SCHEDULED TASKS
	// =========================================================================

	private function register_get_scheduled_tasks(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-scheduled-tasks',
			array(
				'label'         => __( 'Get Scheduled Tasks', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Lists all WordPress cron events with their next run time, recurrence schedule, and argument hash. Optionally filter by hook name. Also reports whether WP-Cron is disabled or running in alternative mode.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array(
					'type'       => 'object',
					'properties' => array(
						'hook' => array( 'type' => 'string', 'description' => __( 'Optional hook name to filter events.', 'tr-wordpress-ai-toolkit' ) ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_scheduled_tasks( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_scheduled_tasks( array $input ): array {
		$hook_filter = sanitize_text_field( (string) ( $input['hook'] ?? '' ) );

		$cron = _get_cron_array();
		if ( ! is_array( $cron ) ) {
			$cron = array();
		}

		$events = array();

		foreach ( $cron as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $callbacks ) {
				// Apply hook filter.
				if ( '' !== $hook_filter && false === strpos( $hook, $hook_filter ) ) {
					continue;
				}

				foreach ( $callbacks as $args_hash => $event ) {
					$schedule         = $event['schedule'] ?? false;
					$interval_seconds = $event['interval'] ?? null;

					$events[] = array(
						'hook'             => $hook,
						'timestamp'        => (int) $timestamp,
						'next_run'         => wp_date( 'Y-m-d H:i:s', (int) $timestamp ),
						'schedule'         => $schedule ?: 'one-time',
						'interval_seconds' => $interval_seconds,
						'args_hash'        => $args_hash,
					);
				}
			}
		}

		// Sort by next run time ascending.
		usort( $events, fn( array $a, array $b ) => $a['timestamp'] - $b['timestamp'] );

		// Count distinct hooks.
		$unique_hooks = count( array_unique( array_column( $events, 'hook' ) ) );

		$this->logger->log( 'tr-ai-toolkit-get-scheduled-tasks', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'disabled'         => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'alternative_cron' => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
			'total_hooks'      => $unique_hooks,
			'total_events'     => count( $events ),
			'hook_filter'      => '' !== $hook_filter ? $hook_filter : null,
			'events'           => $events,
		);
	}

	// =========================================================================
	// Module interface
	// =========================================================================

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-get-server-info',
			'tr-ai-toolkit-get-php-configuration',
			'tr-ai-toolkit-get-database-stats',
			'tr-ai-toolkit-get-disk-usage',
			'tr-ai-toolkit-get-error-log',
			'tr-ai-toolkit-get-scheduled-tasks',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-get-server-info'       => $this->execute_get_server_info( $args ),
			'tr-ai-toolkit-get-php-configuration' => $this->execute_get_php_configuration( $args ),
			'tr-ai-toolkit-get-database-stats'    => $this->execute_get_database_stats( $args ),
			'tr-ai-toolkit-get-disk-usage'        => $this->execute_get_disk_usage( $args ),
			'tr-ai-toolkit-get-error-log'         => $this->execute_get_error_log( $args ),
			'tr-ai-toolkit-get-scheduled-tasks'   => $this->execute_get_scheduled_tasks( $args ),
			default                               => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-get-server-info',
				'description' => 'Returns a snapshot of the server and WordPress environment: web server, PHP version and SAPI, OS, database version, WordPress version, memory limits, debug flags, and SSL status.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-php-configuration',
				'description' => 'Returns PHP ini values relevant to WordPress (memory_limit, max_execution_time, etc.), a sorted list of all loaded extensions, quick-check of key extensions (curl, gd, imagick, intl, mbstring, openssl, zip, zlib, opcache), and OPcache memory and hit-rate statistics.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-database-stats',
				'description' => 'Returns all database tables with size in KB and row count, total database size, and separates WordPress-prefixed tables from non-WordPress tables.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-disk-usage',
				'description' => 'Reports disk usage for uploads, plugins, themes, and total wp-content directories. Returns size in bytes and MB plus file count for each. Capped at 10,000 files per directory.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-error-log',
				'description' => 'Reads the last N lines from the active PHP/WordPress error log. Supports an optional search filter. Returns file size, lines returned, and each line sanitized. Log filename only — not the full path.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'lines'  => array( 'type' => 'integer' ),
						'search' => array( 'type' => 'string' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-scheduled-tasks',
				'description' => 'Lists all WordPress cron events with next run timestamp, human-readable time, recurrence schedule, interval in seconds, and argument hash. Optional hook filter. Also reports DISABLE_WP_CRON and ALTERNATE_WP_CRON flags.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'hook' => array( 'type' => 'string' ),
					),
				),
			),
		);
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Recursively calculates the size and file count of a directory.
	 * Stops counting after $limit files to prevent timeout on huge trees.
	 *
	 * @param string $path  Absolute directory path.
	 * @param int    $limit Maximum files to count. Default: 10000.
	 * @return array{size_bytes: int, file_count: int}
	 */
	private function get_dir_size( string $path, int $limit = 10000 ): array {
		if ( ! is_dir( $path ) ) {
			return array( 'size_bytes' => 0, 'file_count' => 0 );
		}

		$size  = 0;
		$count = 0;

		try {
			$iter = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ( $iter as $file ) {
				if ( $count >= $limit ) {
					break;
				}
				$size += $file->getSize();
				$count++;
			}
		} catch ( \Exception $e ) {
			// Permission denied or unreadable path — return partial result.
		}

		return array( 'size_bytes' => $size, 'file_count' => $count );
	}

	/**
	 * Reads the last N lines from a large file using SplFileObject,
	 * avoiding loading the entire file into memory.
	 *
	 * @param string $path  Absolute file path.
	 * @param int    $count Number of lines to read from the end.
	 * @return string[]
	 */
	private function read_last_lines_spl( string $path, int $count ): array {
		try {
			$file = new \SplFileObject( $path, 'r' );
			$file->seek( PHP_INT_MAX );
			$total_lines = $file->key();

			$start = max( 0, $total_lines - $count );
			$file->seek( $start );

			$lines = array();
			while ( ! $file->eof() ) {
				$line = $file->current();
				if ( is_string( $line ) ) {
					$trimmed = rtrim( $line, "\r\n" );
					if ( '' !== $trimmed ) {
						$lines[] = $trimmed;
					}
				}
				$file->next();
			}

			return $lines;
		} catch ( \Exception $e ) {
			return array();
		}
	}
}
