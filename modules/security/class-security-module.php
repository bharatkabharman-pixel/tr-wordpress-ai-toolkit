<?php
/**
 * Security module.
 *
 * Covers seven areas:
 *  1. Security report    — aggregated score with issues and warnings.
 *  2. File permissions   — key WP files and directories vs. recommended modes.
 *  3. Admin users        — all admins/editors with account age flags.
 *  4. Inactive plugins   — unused plugins that may carry vulnerabilities.
 *  5. Core integrity     — compare core checksums against WordPress.org API.
 *  6. SSL status         — HTTPS, FORCE_SSL_ADMIN, certificate details.
 *  7. User activity log  — tail the plugin's own activity log table.
 *
 * Security notes:
 *  - Every tool requires the admin capability.
 *  - No password hashes, session tokens or private meta are ever returned.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Security MCP tools.
 */
class TR_AI_Toolkit_Security_Module {

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
		$this->register_get_security_report();
		$this->register_check_file_permissions();
		$this->register_list_admin_users();
		$this->register_check_inactive_plugins();
		$this->register_check_core_integrity();
		$this->register_check_ssl_status();
		$this->register_get_user_activity_log();
	}

	// =========================================================================
	// SECURITY REPORT
	// =========================================================================

	private function register_get_security_report(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-security-report',
			array(
				'label'         => __( 'Get Security Report', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Returns an aggregated security score with categorised issues and warnings. Checks SSL, WordPress core updates, plugin updates, file editor status, debug mode, wp-config.php permissions, default admin user, and administrator account count.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_security_report( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_security_report( array $input ): array {
		global $wpdb;

		// Count admin users.
		$admin_count = count( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) );

		// WP version up to date?
		$wp_version  = get_bloginfo( 'version' );
		$update_core = get_site_transient( 'update_core' );
		$wp_up_to_date = true;
		if ( $update_core && isset( $update_core->updates ) ) {
			foreach ( $update_core->updates as $update ) {
				if ( 'upgrade' === $update->response ) {
					$wp_up_to_date = false;
					break;
				}
			}
		}

		// Plugins with updates.
		$update_plugins        = get_site_transient( 'update_plugins' );
		$plugins_needing_update = $update_plugins ? count( (array) $update_plugins->response ) : 0;

		// File editor enabled.
		$file_editor_disabled = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;

		// Debug mode.
		$debug_on = defined( 'WP_DEBUG' ) && WP_DEBUG;

		// SSL.
		$ssl = is_ssl();

		// wp-config.php permissions.
		$config_path  = ABSPATH . 'wp-config.php';
		$config_perms = file_exists( $config_path ) ? substr( sprintf( '%o', fileperms( $config_path ) ), -4 ) : 'unknown';
		$config_ok    = in_array( $config_perms, array( '0400', '0440', '0600', '0640' ), true );

		// Default admin user exists?
		$default_admin = get_user_by( 'login', 'admin' );

		// Build issues/warnings arrays.
		$issues   = array();
		$warnings = array();

		if ( ! $ssl ) {
			$issues[] = 'Site is not served over HTTPS.';
		}
		if ( ! $wp_up_to_date ) {
			$issues[] = 'WordPress core update is available.';
		}
		if ( $plugins_needing_update > 0 ) {
			$warnings[] = "{$plugins_needing_update} plugin(s) have updates available.";
		}
		if ( ! $file_editor_disabled ) {
			$warnings[] = 'Theme/plugin file editor is enabled (DISALLOW_FILE_EDIT not set).';
		}
		if ( $debug_on ) {
			$warnings[] = 'WP_DEBUG is enabled — disable on production.';
		}
		if ( ! $config_ok ) {
			$warnings[] = "wp-config.php permissions ({$config_perms}) may be too permissive. Recommended: 0600.";
		}
		if ( $default_admin ) {
			$warnings[] = 'A user with the login "admin" exists — consider renaming.';
		}
		if ( $admin_count > 3 ) {
			$warnings[] = "{$admin_count} administrator accounts exist — review if all are needed.";
		}

		$this->logger->log( 'tr-ai-toolkit-get-security-report', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'score'                  => max( 0, 100 - ( count( $issues ) * 20 ) - ( count( $warnings ) * 5 ) ),
			'ssl'                    => $ssl,
			'wp_version'             => $wp_version,
			'wp_up_to_date'          => $wp_up_to_date,
			'plugins_needing_update' => $plugins_needing_update,
			'file_editor_disabled'   => $file_editor_disabled,
			'debug_mode_on'          => $debug_on,
			'admin_user_count'       => $admin_count,
			'default_admin_exists'   => (bool) $default_admin,
			'wp_config_permissions'  => $config_perms,
			'issues'                 => $issues,
			'warnings'               => $warnings,
			'issue_count'            => count( $issues ),
			'warning_count'          => count( $warnings ),
		);
	}

	// =========================================================================
	// FILE PERMISSIONS
	// =========================================================================

	private function register_check_file_permissions(): void {
		wp_register_ability(
			'tr-ai-toolkit-check-file-permissions',
			array(
				'label'         => __( 'Check File Permissions', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Checks octal permissions on key WordPress files and directories (wp-config.php, .htaccess, wp-admin, wp-includes, wp-content and sub-directories) against recommended values.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_check_file_permissions( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_check_file_permissions( array $input ): array {
		$checks = array(
			array( 'path' => ABSPATH . 'wp-config.php',        'recommended' => '0600', 'label' => 'wp-config.php' ),
			array( 'path' => ABSPATH . '.htaccess',             'recommended' => '0644', 'label' => '.htaccess' ),
			array( 'path' => ABSPATH . 'wp-admin',              'recommended' => '0755', 'label' => 'wp-admin/' ),
			array( 'path' => ABSPATH . 'wp-includes',           'recommended' => '0755', 'label' => 'wp-includes/' ),
			array( 'path' => WP_CONTENT_DIR,                    'recommended' => '0755', 'label' => 'wp-content/' ),
			array( 'path' => WP_CONTENT_DIR . '/uploads',       'recommended' => '0755', 'label' => 'wp-content/uploads/' ),
			array( 'path' => WP_CONTENT_DIR . '/plugins',       'recommended' => '0755', 'label' => 'wp-content/plugins/' ),
			array( 'path' => WP_CONTENT_DIR . '/themes',        'recommended' => '0755', 'label' => 'wp-content/themes/' ),
		);

		$results = array();

		foreach ( $checks as $check ) {
			if ( ! file_exists( $check['path'] ) ) {
				$results[] = array( 'label' => $check['label'], 'exists' => false, 'permissions' => null, 'ok' => null );
				continue;
			}

			$perms = substr( sprintf( '%o', fileperms( $check['path'] ) ), -4 );

			$results[] = array(
				'label'       => $check['label'],
				'exists'      => true,
				'permissions' => $perms,
				'recommended' => $check['recommended'],
				'ok'          => $perms <= $check['recommended'],
			);
		}

		$issues = array_filter( $results, fn( $r ) => $r['exists'] && $r['ok'] === false );

		$this->logger->log( 'tr-ai-toolkit-check-file-permissions', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'total_checked' => count( $results ),
			'issues_found'  => count( $issues ),
			'checks'        => $results,
		);
	}

	// =========================================================================
	// ADMIN USERS
	// =========================================================================

	private function register_list_admin_users(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-admin-users',
			array(
				'label'         => __( 'List Admin Users', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Returns all administrator and editor accounts ordered by registration date, including account age in days and a flag for accounts created within the last 7 days.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_admin_users( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_admin_users( array $input ): array {
		$admins  = get_users( array( 'role__in' => array( 'administrator', 'editor' ), 'orderby' => 'registered', 'order' => 'ASC' ) );
		$results = array();

		foreach ( $admins as $user ) {
			$registered_ts = strtotime( $user->user_registered );
			$days_old      = (int) floor( ( time() - $registered_ts ) / DAY_IN_SECONDS );

			$results[] = array(
				'id'           => $user->ID,
				'login'        => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'role'         => implode( ', ', $user->roles ),
				'registered'   => $user->user_registered,
				'days_old'     => $days_old,
				'is_new'       => $days_old < 7,
				'edit_url'     => admin_url( "user-edit.php?user_id={$user->ID}" ),
			);
		}

		$new_admins = array_filter( $results, fn( $u ) => $u['is_new'] );

		$this->logger->log( 'tr-ai-toolkit-list-admin-users', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'total'              => count( $results ),
			'new_accounts_7days' => count( $new_admins ),
			'users'              => $results,
		);
	}

	// =========================================================================
	// INACTIVE PLUGINS
	// =========================================================================

	private function register_check_inactive_plugins(): void {
		wp_register_ability(
			'tr-ai-toolkit-check-inactive-plugins',
			array(
				'label'         => __( 'Check Inactive Plugins', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Lists all installed but inactive plugins, including whether an update is available. Inactive plugins can still be exploited if they contain vulnerabilities — the security note recommends deleting unused plugins.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_check_inactive_plugins( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_check_inactive_plugins( array $input ): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$update_data    = get_site_transient( 'update_plugins' );
		$inactive       = array();

		foreach ( $all_plugins as $file => $data ) {
			if ( in_array( $file, $active_plugins, true ) ) {
				continue;
			}

			$has_update = isset( $update_data->response[ $file ] );
			$inactive[] = array(
				'file'             => $file,
				'name'             => $data['Name'],
				'version'          => $data['Version'],
				'author'           => wp_strip_all_tags( $data['Author'] ),
				'update_available' => $has_update,
				'new_version'      => $has_update ? $update_data->response[ $file ]->new_version : null,
			);
		}

		$this->logger->log( 'tr-ai-toolkit-check-inactive-plugins', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'total_inactive' => count( $inactive ),
			'security_note'  => __( 'Inactive plugins still execute file scanning and can be exploited if vulnerable. Consider deleting plugins you no longer use.', 'tr-wordpress-ai-toolkit' ),
			'plugins'        => $inactive,
		);
	}

	// =========================================================================
	// CORE INTEGRITY
	// =========================================================================

	private function register_check_core_integrity(): void {
		wp_register_ability(
			'tr-ai-toolkit-check-core-integrity',
			array(
				'label'         => __( 'Check Core Integrity', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Compares WordPress core file checksums against the official WordPress.org API for the current version and locale. Reports modified and missing files. wp-content is excluded. Results are capped at 20 files per category.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_check_core_integrity( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_check_core_integrity( array $input ): array|\WP_Error {
		$wp_version = get_bloginfo( 'version' );
		$locale     = get_locale();

		$api_url  = "https://api.wordpress.org/core/checksums/1.0/?version={$wp_version}&locale={$locale}";
		$response = wp_remote_get( $api_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'tr-ai-toolkit-check-core-integrity', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, $response->get_error_message() );
			return new \WP_Error( 'api_error', 'Could not reach WordPress.org checksums API: ' . $response->get_error_message(), array( 'status' => 503 ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['checksums'] ) ) {
			$this->logger->log( 'tr-ai-toolkit-check-core-integrity', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, 'No checksums returned' );
			return new \WP_Error( 'api_error', 'No checksums returned for this WordPress version.', array( 'status' => 503 ) );
		}

		$checksums = $body['checksums'];
		$modified  = array();
		$missing   = array();

		foreach ( $checksums as $file => $expected_md5 ) {
			if ( str_starts_with( $file, 'wp-content' ) ) {
				continue;
			}

			$full_path = ABSPATH . $file;

			if ( ! file_exists( $full_path ) ) {
				$missing[] = $file;
				continue;
			}

			if ( md5_file( $full_path ) !== $expected_md5 ) {
				$modified[] = $file;
			}
		}

		$this->logger->log( 'tr-ai-toolkit-check-core-integrity', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'wp_version'     => $wp_version,
			'locale'         => $locale,
			'files_checked'  => count( $checksums ),
			'modified_files' => count( $modified ),
			'missing_files'  => count( $missing ),
			'modified'       => array_slice( $modified, 0, 20 ),
			'missing'        => array_slice( $missing, 0, 20 ),
			'status'         => ( count( $modified ) === 0 && count( $missing ) === 0 ) ? 'clean' : 'issues_found',
			'note'           => __( 'Modified core files may indicate a hack or manual customisation. Reinstall WordPress core to restore clean files.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	// =========================================================================
	// SSL STATUS
	// =========================================================================

	private function register_check_ssl_status(): void {
		wp_register_ability(
			'tr-ai-toolkit-check-ssl-status',
			array(
				'label'         => __( 'Check SSL Status', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Reports whether the site is served over HTTPS, whether FORCE_SSL_ADMIN is set, and — when accessible — SSL certificate subject, issuer, validity dates and days remaining before expiry.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_check_ssl_status( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_check_ssl_status( array $input ): array {
		$site_url   = get_site_url();
		$is_https   = str_starts_with( $site_url, 'https://' );
		$force_ssl  = defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN;
		$home_url   = get_home_url();
		$home_https = str_starts_with( $home_url, 'https://' );

		$cert_info = null;

		if ( $is_https ) {
			$host = parse_url( $site_url, PHP_URL_HOST );

			if ( $host ) {
				$context = stream_context_create( array( 'ssl' => array( 'capture_peer_cert' => true ) ) );
				$socket  = @stream_socket_client( "ssl://{$host}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context );

				if ( $socket ) {
					$params = stream_context_get_params( $socket );
					$cert   = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] ?? '' );

					if ( $cert ) {
						$cert_info = array(
							'subject'    => $cert['subject']['CN'] ?? '',
							'issuer'     => $cert['issuer']['O'] ?? '',
							'valid_from' => date( 'Y-m-d', $cert['validFrom_time_t'] ?? 0 ),
							'valid_to'   => date( 'Y-m-d', $cert['validTo_time_t'] ?? 0 ),
							'days_left'  => (int) floor( ( ( $cert['validTo_time_t'] ?? 0 ) - time() ) / DAY_IN_SECONDS ),
							'expired'    => ( $cert['validTo_time_t'] ?? 0 ) < time(),
						);
					}

					fclose( $socket );
				}
			}
		}

		$recommendation = ! $is_https
			? 'Enable HTTPS and update siteurl/home to https://'
			: ( $cert_info && $cert_info['days_left'] < 30
				? 'SSL certificate expires soon — renew immediately.'
				: 'HTTPS is active.' );

		$this->logger->log( 'tr-ai-toolkit-check-ssl-status', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'site_url'        => $site_url,
			'is_https'        => $is_https,
			'home_url'        => $home_url,
			'home_https'      => $home_https,
			'force_ssl_admin' => $force_ssl,
			'certificate'     => $cert_info,
			'recommendation'  => $recommendation,
		);
	}

	// =========================================================================
	// USER ACTIVITY LOG
	// =========================================================================

	private function register_get_user_activity_log(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-user-activity-log',
			array(
				'label'         => __( 'Get User Activity Log', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Returns recent entries from the plugin\'s own activity log table (tr_ai_toolkit_logs), ordered newest first. Each entry includes the ability name, user, result status, IP address and timestamp.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Number of log entries to return (1–200). Default 50.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_user_activity_log( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_user_activity_log( array $input ): array {
		global $wpdb;

		$limit = min( 200, max( 1, absint( $input['limit'] ?? 50 ) ) );
		$table = $wpdb->prefix . 'tr_ai_toolkit_logs';

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ability, user_id, result_status, message, ip_address, log_time FROM {$table} ORDER BY log_time DESC LIMIT %d",
			$limit
		) );

		$results = array();

		foreach ( $rows as $row ) {
			$user      = get_userdata( (int) $row->user_id );
			$results[] = array(
				'time'       => $row->log_time,
				'ability'    => $row->ability,
				'user_id'    => (int) $row->user_id,
				'user_login' => $user ? $user->user_login : '(deleted)',
				'status'     => $row->result_status,
				'ip'         => $row->ip_address,
				'message'    => $row->message,
			);
		}

		$this->logger->log( 'tr-ai-toolkit-get-user-activity-log', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array( 'total' => count( $results ), 'limit' => $limit, 'log' => $results );
	}

	// =========================================================================
	// ROUTER
	// =========================================================================

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-get-security-report',
			'tr-ai-toolkit-check-file-permissions',
			'tr-ai-toolkit-list-admin-users',
			'tr-ai-toolkit-check-inactive-plugins',
			'tr-ai-toolkit-check-core-integrity',
			'tr-ai-toolkit-check-ssl-status',
			'tr-ai-toolkit-get-user-activity-log',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-get-security-report'    => $this->execute_get_security_report( $args ),
			'tr-ai-toolkit-check-file-permissions' => $this->execute_check_file_permissions( $args ),
			'tr-ai-toolkit-list-admin-users'       => $this->execute_list_admin_users( $args ),
			'tr-ai-toolkit-check-inactive-plugins' => $this->execute_check_inactive_plugins( $args ),
			'tr-ai-toolkit-check-core-integrity'   => $this->execute_check_core_integrity( $args ),
			'tr-ai-toolkit-check-ssl-status'       => $this->execute_check_ssl_status( $args ),
			'tr-ai-toolkit-get-user-activity-log'  => $this->execute_get_user_activity_log( $args ),
			default                                => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-get-security-report',
				'description' => 'Returns an aggregated security score (0–100) with separate issues and warnings arrays. Checks SSL, core/plugin updates, file editor, debug mode, wp-config.php permissions, default admin user and administrator count.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-check-file-permissions',
				'description' => 'Checks octal permissions on key WordPress paths (wp-config.php, .htaccess, wp-admin, wp-includes, wp-content and sub-directories) against recommended values and flags anything too permissive.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-list-admin-users',
				'description' => 'Returns all administrator and editor accounts with account age in days and a flag for accounts less than 7 days old.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-check-inactive-plugins',
				'description' => 'Lists all installed but inactive plugins. Flags those with available updates. Inactive plugins can still be exploited if vulnerable.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-check-core-integrity',
				'description' => 'Compares WordPress core file checksums against WordPress.org API for the current version and locale. Reports modified and missing files (capped at 20 each). wp-content is excluded.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-check-ssl-status',
				'description' => 'Reports HTTPS status, FORCE_SSL_ADMIN flag, and SSL certificate details (subject, issuer, expiry date, days remaining) when the host is reachable over port 443.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-get-user-activity-log',
				'description' => 'Returns recent entries from the plugin activity log table, newest first. Each row includes ability name, user login, result status, IP address and timestamp.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer', 'description' => 'Number of entries to return (1–200). Default 50.' ),
					),
				),
			),
		);
	}
}
