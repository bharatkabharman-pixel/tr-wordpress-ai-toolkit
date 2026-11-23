<?php
/**
 * Audit logger.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes ability invocations to the audit log table.
 */
class TR_AI_Toolkit_Logger {

	public const STATUS_SUCCESS = 'success';
	public const STATUS_ERROR   = 'error';
	public const STATUS_DENIED  = 'denied';

	private TR_AI_Toolkit_Settings $settings;

	public function __construct( TR_AI_Toolkit_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Records one ability execution.
	 *
	 * @param string               $ability       Ability ID, e.g. 'tr-ai-toolkit/list-posts'.
	 * @param array<string, mixed> $input         Sanitized input parameters.
	 * @param string               $result_status One of the STATUS_* constants.
	 * @param string               $message       Optional human-readable outcome note.
	 */
	public function log(
		string $ability,
		array $input,
		string $result_status = self::STATUS_SUCCESS,
		string $message = ''
	): void {
		if ( ! $this->settings->get( 'logging.enabled', true ) ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'tr_ai_toolkit_logs';

		// Strip any sensitive keys from input before storing.
		$safe_input = $this->sanitize_input_for_log( $input );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'log_time'     => current_time( 'mysql', true ),
				'ability'      => $ability,
				'user_id'      => get_current_user_id(),
				'input_data'   => wp_json_encode( $safe_input ),
				'result_status' => $result_status,
				'message'      => $message,
				'ip_address'   => $this->get_client_ip(),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Returns recent log entries for the dashboard.
	 *
	 * @param int $limit Maximum rows to return.
	 * @return array<int, object>
	 */
	public function get_recent( int $limit = 50 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'tr_ai_toolkit_logs';

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM `{$table}` ORDER BY log_time DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			)
		);
	}

	/**
	 * Deletes all log entries and returns the count removed.
	 */
	public function clear_all(): int {
		global $wpdb;
		$table  = $wpdb->prefix . 'tr_ai_toolkit_logs';
		$result = $wpdb->query( "DELETE FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return ( false === $result ) ? 0 : (int) $result;
	}

	/**
	 * Deletes log entries older than the configured retention period.
	 */
	public function prune(): void {
		$days = absint( $this->settings->get( 'logging.retention_days', 30 ) );
		if ( $days <= 0 ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'tr_ai_toolkit_logs';

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE log_time < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$days
			)
		);
	}

	/**
	 * Removes fields that must never be stored (passwords, tokens, nonces).
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function sanitize_input_for_log( array $input ): array {
		$blocked = array( 'password', 'pass', 'token', 'secret', 'nonce', 'auth', 'key', 'api_key' );

		foreach ( array_keys( $input ) as $key ) {
			foreach ( $blocked as $blocked_key ) {
				if ( str_contains( strtolower( (string) $key ), $blocked_key ) ) {
					$input[ $key ] = '[REDACTED]';
					break;
				}
			}
		}

		return $input;
	}

	/**
	 * Returns the visitor IP address without exposing server infrastructure details.
	 */
	private function get_client_ip(): string {
		$ip = '';

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
