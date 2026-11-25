<?php
/**
 * Backup module.
 *
 * Covers five areas:
 *  1. Backup status    — detect which backup plugin is active and report health.
 *  2. List backups     — enumerate existing backup files (UpdraftPlus primary).
 *  3. Trigger backup   — programmatically start a backup via the active plugin.
 *  4. Backup schedule  — read configured schedules and next run times.
 *  5. Storage info     — report backup directories and disk usage.
 *
 * Security notes:
 *  - All tools require admin capability.
 *  - No credentials or secret keys are exposed in any response.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Backup MCP tools.
 */
class TR_AI_Toolkit_Backup_Module {

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
		$this->register_get_backup_status();
		$this->register_list_backups();
		$this->register_trigger_backup();
		$this->register_get_backup_schedule();
		$this->register_get_backup_storage_info();
	}

	// =========================================================================
	// DETECT BACKUP PLUGIN (private helper)
	// =========================================================================

	/** @return array<string, mixed> */
	private function detect_backup_plugin(): array {
		// UpdraftPlus
		if ( defined( 'UPDRAFTPLUS_VERSION' ) || class_exists( 'UpdraftPlus' ) ) {
			$last_backup_ts = get_option( 'updraft_last_backup', 0 );
			return array(
				'plugin'         => 'UpdraftPlus',
				'version'        => defined( 'UPDRAFTPLUS_VERSION' ) ? UPDRAFTPLUS_VERSION : 'unknown',
				'active'         => true,
				'last_backup'    => $last_backup_ts ? gmdate( 'Y-m-d H:i:s', (int) $last_backup_ts ) : null,
				'last_backup_ts' => (int) $last_backup_ts,
			);
		}
		// BackWPup
		if ( defined( 'BackWPup_PLUGIN_FILE' ) || class_exists( 'BackWPup' ) ) {
			return array( 'plugin' => 'BackWPup', 'version' => defined( 'BackWPup_VERSION' ) ? BackWPup_VERSION : 'unknown', 'active' => true, 'last_backup' => null, 'last_backup_ts' => 0 );
		}
		// Duplicator
		if ( defined( 'DUPLICATOR_VERSION' ) || class_exists( 'Duplicator' ) ) {
			return array( 'plugin' => 'Duplicator', 'version' => defined( 'DUPLICATOR_VERSION' ) ? DUPLICATOR_VERSION : 'unknown', 'active' => true, 'last_backup' => null, 'last_backup_ts' => 0 );
		}
		// All-in-One WP Migration
		if ( defined( 'AI1WM_PLUGIN_NAME' ) || class_exists( 'Ai1wm_Export' ) ) {
			return array( 'plugin' => 'All-in-One WP Migration', 'version' => defined( 'AI1WM_VERSION' ) ? AI1WM_VERSION : 'unknown', 'active' => true, 'last_backup' => null, 'last_backup_ts' => 0 );
		}
		// WPvivid
		if ( defined( 'WPVIVID_VERSION' ) || class_exists( 'WPvivid_Plugin' ) ) {
			return array( 'plugin' => 'WPvivid Backup', 'version' => defined( 'WPVIVID_VERSION' ) ? WPVIVID_VERSION : 'unknown', 'active' => true, 'last_backup' => null, 'last_backup_ts' => 0 );
		}
		return array( 'plugin' => null, 'version' => null, 'active' => false, 'last_backup' => null, 'last_backup_ts' => 0 );
	}

	// =========================================================================
	// GET BACKUP STATUS
	// =========================================================================

	private function register_get_backup_status(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-backup-status',
			array(
				'label'               => __( 'Get Backup Status', 'tr-wordpress-ai-toolkit' ),
				'description'         => __( 'Detects which backup plugin is active and returns overall backup health, including the last backup date and a recommendation.', 'tr-wordpress-ai-toolkit' ),
				'category'            => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_backup_status( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_backup_status( array $input ): array {
		$info              = $this->detect_backup_plugin();
		$days_since_backup = null;
		$backup_ok         = false;

		if ( $info['last_backup_ts'] > 0 ) {
			$days_since_backup = (int) floor( ( time() - $info['last_backup_ts'] ) / DAY_IN_SECONDS );
			$backup_ok         = $days_since_backup <= 7;
		}

		$this->logger->log( 'tr-ai-toolkit-get-backup-status', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'backup_plugin_detected' => $info['active'],
			'plugin_name'            => $info['plugin'],
			'plugin_version'         => $info['version'],
			'last_backup'            => $info['last_backup'],
			'days_since_backup'      => $days_since_backup,
			'backup_health'          => ! $info['active'] ? 'no_plugin' : ( $backup_ok ? 'ok' : ( $days_since_backup === null ? 'unknown' : 'stale' ) ),
			'recommendation'         => ! $info['active']
				? __( 'No backup plugin detected. Install UpdraftPlus, BackWPup or similar.', 'tr-wordpress-ai-toolkit' )
				: ( $backup_ok ? __( 'Backup is recent.', 'tr-wordpress-ai-toolkit' ) : __( 'Last backup is more than 7 days old. Run a backup now.', 'tr-wordpress-ai-toolkit' ) ),
		);
	}

	// =========================================================================
	// LIST BACKUPS
	// =========================================================================

	private function register_list_backups(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-backups',
			array(
				'label'               => __( 'List Backups', 'tr-wordpress-ai-toolkit' ),
				'description'         => __( 'Lists existing backup files from the active backup plugin. UpdraftPlus backup ZIPs are enumerated directly; other plugins surface available metadata.', 'tr-wordpress-ai-toolkit' ),
				'category'            => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'default'     => 20,
							'description' => __( 'Maximum number of backup files to return (1–50).', 'tr-wordpress-ai-toolkit' ),
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_backups( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_backups( array $input ): array|\WP_Error {
		$limit = min( 50, max( 1, absint( $input['limit'] ?? 20 ) ) );
		$info  = $this->detect_backup_plugin();

		if ( ! $info['active'] ) {
			$error = new \WP_Error( 'no_backup_plugin', __( 'No supported backup plugin is active.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 503 ) );
			$this->logger->log( 'tr-ai-toolkit-list-backups', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return $error;
		}

		$backups = array();

		// UpdraftPlus: backup files stored in wp-content/updraft/ by default
		if ( 'UpdraftPlus' === $info['plugin'] ) {
			$backup_dir = defined( 'UPDRAFTPLUS_DIR' ) ? UPDRAFTPLUS_DIR : ( WP_CONTENT_DIR . '/updraft' );
			if ( is_dir( $backup_dir ) ) {
				$files = glob( $backup_dir . '/*.zip' ) ?: array();
				arsort( $files ); // newest first by filename (they include timestamps)
				foreach ( array_slice( $files, 0, $limit ) as $file ) {
					$backups[] = array(
						'filename' => basename( $file ),
						'size_mb'  => round( filesize( $file ) / MB_IN_BYTES, 2 ),
						'modified' => gmdate( 'Y-m-d H:i:s', filemtime( $file ) ),
						'type'     => 'updraftplus',
					);
				}
			}
		}

		// BackWPup: check configured destination dirs
		if ( 'BackWPup' === $info['plugin'] && class_exists( 'BackWPup_Option' ) ) {
			// BackWPup stores jobs in options — getting files requires knowing each job's folder
			// Surface what we can: just the jobs list
			$backups[] = array( 'note' => 'BackWPup backup file listing requires checking each job\'s destination folder. Use BackWPup admin panel for details.' );
		}

		$this->logger->log( 'tr-ai-toolkit-list-backups', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'plugin'  => $info['plugin'],
			'total'   => count( $backups ),
			'backups' => $backups,
		);
	}

	// =========================================================================
	// TRIGGER BACKUP
	// =========================================================================

	private function register_trigger_backup(): void {
		wp_register_ability(
			'tr-ai-toolkit-trigger-backup',
			array(
				'label'               => __( 'Trigger Backup', 'tr-wordpress-ai-toolkit' ),
				'description'         => __( 'Triggers a backup using the active plugin\'s programmatic API. Requires confirm: true. UpdraftPlus schedules an immediate WP-Cron event.', 'tr-wordpress-ai-toolkit' ),
				'category'            => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'confirm' ),
					'properties' => array(
						'confirm' => array(
							'type'        => 'boolean',
							'description' => __( 'Must be true to proceed. This may take several minutes.', 'tr-wordpress-ai-toolkit' ),
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_trigger_backup( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_trigger_backup( array $input ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			$error = new \WP_Error( 'confirmation_required', __( 'Set confirm: true to trigger a backup. This may take several minutes.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
			$this->logger->log( 'tr-ai-toolkit-trigger-backup', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return $error;
		}

		$info = $this->detect_backup_plugin();

		if ( ! $info['active'] ) {
			$error = new \WP_Error( 'no_backup_plugin', __( 'No supported backup plugin is active.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 503 ) );
			$this->logger->log( 'tr-ai-toolkit-trigger-backup', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return $error;
		}

		// UpdraftPlus programmatic backup
		if ( 'UpdraftPlus' === $info['plugin'] ) {
			global $updraftplus;
			if ( isset( $updraftplus ) && method_exists( $updraftplus, 'backup_time_nonce' ) ) {
				// Schedule immediate backup via WP-Cron
				$timestamp = time() + 5; // 5 seconds from now
				wp_schedule_single_event( $timestamp, 'updraft_backup_resume', array( 0, false, '' ) );

				$this->logger->log( 'tr-ai-toolkit-trigger-backup', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

				return array(
					'success' => true,
					'plugin'  => 'UpdraftPlus',
					'message' => __( 'UpdraftPlus backup scheduled. It will begin within seconds via WP-Cron. Check UpdraftPlus admin panel for progress.', 'tr-wordpress-ai-toolkit' ),
					'note'    => __( 'If WP-Cron is disabled, trigger wp-cron.php manually or use a real cron job.', 'tr-wordpress-ai-toolkit' ),
				);
			}
		}

		// BackWPup
		if ( 'BackWPup' === $info['plugin'] && class_exists( 'BackWPup_Job' ) ) {
			$this->logger->log( 'tr-ai-toolkit-trigger-backup', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

			return array(
				'success' => false,
				'plugin'  => 'BackWPup',
				'message' => __( 'BackWPup manual triggers require a job ID. Use BackWPup admin → Jobs → Run Now.', 'tr-wordpress-ai-toolkit' ),
			);
		}

		$this->logger->log( 'tr-ai-toolkit-trigger-backup', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success' => false,
			'plugin'  => $info['plugin'],
			'message' => sprintf( __( '%s does not support programmatic backup triggers via this tool. Use the plugin\'s admin panel.', 'tr-wordpress-ai-toolkit' ), $info['plugin'] ),
		);
	}

	// =========================================================================
	// GET BACKUP SCHEDULE
	// =========================================================================

	private function register_get_backup_schedule(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-backup-schedule',
			array(
				'label'               => __( 'Get Backup Schedule', 'tr-wordpress-ai-toolkit' ),
				'description'         => __( 'Returns the configured backup schedules for the active plugin, including intervals, retention counts, remote storage destinations and next scheduled run times.', 'tr-wordpress-ai-toolkit' ),
				'category'            => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_backup_schedule( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_backup_schedule( array $input ): array|\WP_Error {
		$info = $this->detect_backup_plugin();

		if ( ! $info['active'] ) {
			$error = new \WP_Error( 'no_backup_plugin', __( 'No supported backup plugin is active.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 503 ) );
			$this->logger->log( 'tr-ai-toolkit-get-backup-schedule', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return $error;
		}

		$schedule = array( 'plugin' => $info['plugin'], 'schedules' => array() );

		if ( 'UpdraftPlus' === $info['plugin'] ) {
			$db_interval    = get_option( 'updraft_interval_database', 'manual' );
			$files_interval = get_option( 'updraft_interval', 'manual' );
			$retain_db      = (int) get_option( 'updraft_retain_db', 2 );
			$retain_files   = (int) get_option( 'updraft_retain', 2 );
			$remote_storage = get_option( 'updraft_service', array() );

			$schedule['schedules'] = array(
				array( 'type' => 'database', 'interval' => $db_interval,    'retain' => $retain_db ),
				array( 'type' => 'files',    'interval' => $files_interval,  'retain' => $retain_files ),
			);
			$schedule['remote_storage'] = is_array( $remote_storage ) ? $remote_storage : array( $remote_storage );

			// Next scheduled WP-Cron event
			$next_db    = wp_next_scheduled( 'updraft_backup_database' );
			$next_files = wp_next_scheduled( 'updraft_backup' );

			$schedule['next_database_backup'] = $next_db    ? gmdate( 'Y-m-d H:i:s', $next_db )    : null;
			$schedule['next_files_backup']    = $next_files ? gmdate( 'Y-m-d H:i:s', $next_files ) : null;
		}

		$this->logger->log( 'tr-ai-toolkit-get-backup-schedule', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return $schedule;
	}

	// =========================================================================
	// GET BACKUP STORAGE INFO
	// =========================================================================

	private function register_get_backup_storage_info(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-backup-storage-info',
			array(
				'label'               => __( 'Get Backup Storage Info', 'tr-wordpress-ai-toolkit' ),
				'description'         => __( 'Reports where backups are stored on disk and how much space they occupy, scanning known backup directories for the active plugin.', 'tr-wordpress-ai-toolkit' ),
				'category'            => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_backup_storage_info( $input ),
				'meta'                => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_backup_storage_info( array $input ): array {
		$info = $this->detect_backup_plugin();

		// Known backup directory paths by plugin
		$dirs = array();
		if ( 'UpdraftPlus' === $info['plugin'] ) {
			$dirs[] = WP_CONTENT_DIR . '/updraft';
		}
		if ( 'BackWPup' === $info['plugin'] ) {
			$dirs[] = WP_CONTENT_DIR . '/uploads/backwpup-*-backups'; // glob pattern
		}
		// Generic: wp-content/backups
		$dirs[] = WP_CONTENT_DIR . '/backups';

		$storage = array();
		foreach ( $dirs as $pattern ) {
			// Handle glob patterns
			$expanded = ( str_contains( $pattern, '*' ) ) ? ( glob( $pattern, GLOB_ONLYDIR ) ?: array() ) : array( $pattern );
			foreach ( $expanded as $dir ) {
				if ( ! is_dir( $dir ) ) {
					continue;
				}
				$size  = 0;
				$count = 0;
				try {
					$iter = new \RecursiveIteratorIterator(
						new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
					);
					foreach ( $iter as $file ) {
						$size  += $file->getSize();
						$count++;
						if ( $count >= 5000 ) {
							break;
						}
					}
				} catch ( \Exception $e ) {
					// permission denied — partial result is acceptable
				}
				$storage[] = array(
					'path'       => $dir,
					'size_mb'    => round( $size / MB_IN_BYTES, 2 ),
					'file_count' => $count,
				);
			}
		}

		$this->logger->log( 'tr-ai-toolkit-get-backup-storage-info', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'plugin'           => $info['plugin'] ?? 'none',
			'backup_locations' => $storage,
			'total_size_mb'    => round( array_sum( array_column( $storage, 'size_mb' ) ), 2 ),
		);
	}

	// =========================================================================
	// MCP ADAPTER
	// =========================================================================

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-get-backup-status',
			'tr-ai-toolkit-list-backups',
			'tr-ai-toolkit-trigger-backup',
			'tr-ai-toolkit-get-backup-schedule',
			'tr-ai-toolkit-get-backup-storage-info',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-get-backup-status'      => $this->execute_get_backup_status( $args ),
			'tr-ai-toolkit-list-backups'            => $this->execute_list_backups( $args ),
			'tr-ai-toolkit-trigger-backup'          => $this->execute_trigger_backup( $args ),
			'tr-ai-toolkit-get-backup-schedule'     => $this->execute_get_backup_schedule( $args ),
			'tr-ai-toolkit-get-backup-storage-info' => $this->execute_get_backup_storage_info( $args ),
			default                                 => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-get-backup-status',
				'description' => 'Detects the active backup plugin and returns overall backup health, last backup date, days since last backup and a plain-language recommendation.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-list-backups',
				'description' => 'Lists existing backup files from the active backup plugin. Returns filename, size and modification date for each file. UpdraftPlus ZIPs are enumerated directly.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'default'     => 20,
							'description' => 'Maximum number of backup files to return (1–50).',
						),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-trigger-backup',
				'description' => 'Triggers a backup using the active plugin\'s API. UpdraftPlus schedules an immediate WP-Cron event. Requires confirm: true.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'confirm' ),
					'properties' => array(
						'confirm' => array(
							'type'        => 'boolean',
							'description' => 'Must be true to proceed. The backup may take several minutes.',
						),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-backup-schedule',
				'description' => 'Returns configured backup schedules for the active plugin: intervals, retention counts, remote storage destinations and next scheduled WP-Cron run times.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-backup-storage-info',
				'description' => 'Reports backup storage locations on disk with size in MB and file count. Scans known plugin directories plus the generic wp-content/backups folder.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
		);
	}
}
