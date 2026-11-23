<?php
/**
 * Tests for TR_AI_Toolkit_Logger.
 *
 * @package TRWordPressAIToolkit
 */

class LoggerTest extends WP_UnitTestCase {

	private TR_AI_Toolkit_Settings $settings;
	private TR_AI_Toolkit_Logger   $logger;

	public function setUp(): void {
		parent::setUp();
		TR_AI_Toolkit_Activator::activate();
		$this->settings = new TR_AI_Toolkit_Settings();
		$this->logger   = new TR_AI_Toolkit_Logger( $this->settings );
	}

	public function test_log_writes_row(): void {
		global $wpdb;

		$this->logger->log( 'tr-ai-toolkit-list-posts', array( 'per_page' => 10 ), TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		$table = $wpdb->prefix . 'tr_ai_toolkit_logs';
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

		$this->assertGreaterThanOrEqual( 1, (int) $count );
	}

	public function test_log_does_not_write_when_disabled(): void {
		global $wpdb;

		$this->settings->update_group( 'logging', array( 'enabled' => false ) );
		$logger = new TR_AI_Toolkit_Logger( $this->settings );
		$logger->log( 'tr-ai-toolkit-list-posts', array(), TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		$table = $wpdb->prefix . 'tr_ai_toolkit_logs';
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

		$this->assertSame( '0', $count );
	}

	public function test_log_redacts_password_key(): void {
		global $wpdb;

		$this->logger->log(
			'test/ability',
			array( 'password' => 'secret123', 'title' => 'Hello' ),
			TR_AI_Toolkit_Logger::STATUS_SUCCESS
		);

		$table = $wpdb->prefix . 'tr_ai_toolkit_logs';
		$row   = $wpdb->get_row( "SELECT input_data FROM `{$table}` ORDER BY id DESC LIMIT 1" );
		$data  = json_decode( $row->input_data, true );

		$this->assertSame( '[REDACTED]', $data['password'] );
		$this->assertSame( 'Hello', $data['title'] );
	}

	public function test_get_recent_returns_array(): void {
		$this->logger->log( 'tr-ai-toolkit-test', array(), TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		$rows = $this->logger->get_recent( 10 );

		$this->assertIsArray( $rows );
		$this->assertGreaterThanOrEqual( 1, count( $rows ) );
	}

	public function test_clear_all_deletes_all_rows(): void {
		global $wpdb;

		$this->logger->log( 'tr-ai-toolkit-list-posts', array(), TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		$this->logger->log( 'tr-ai-toolkit-get-post', array( 'post_id' => 1 ), TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		$deleted = $this->logger->clear_all();

		$this->assertGreaterThanOrEqual( 2, $deleted );

		$table = $wpdb->prefix . 'tr_ai_toolkit_logs';
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		$this->assertSame( '0', $count );
	}

	public function test_clear_all_returns_zero_when_table_empty(): void {
		$deleted = $this->logger->clear_all();
		$this->assertSame( 0, $deleted );
	}

	public function test_prune_removes_old_entries(): void {
		global $wpdb;

		$this->settings->update_group( 'logging', array( 'enabled' => true, 'retention_days' => 1 ) );
		$logger = new TR_AI_Toolkit_Logger( $this->settings );

		$table = $wpdb->prefix . 'tr_ai_toolkit_logs';

		// Insert an old row manually.
		$wpdb->insert( $table, array(
			'log_time'     => '2000-01-01 00:00:00',
			'ability'      => 'old/ability',
			'user_id'      => 0,
			'result_status' => 'success',
			'ip_address'   => '',
		), array( '%s', '%s', '%d', '%s', '%s' ) );

		$logger->prune();

		$old = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE ability = %s",
			'old/ability'
		) );

		$this->assertSame( '0', $old );
	}
}
