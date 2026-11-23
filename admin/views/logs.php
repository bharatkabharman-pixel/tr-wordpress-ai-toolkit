<?php
/**
 * Activity Log view.
 *
 * Available variables: $logs (array of stdClass rows)
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap tr-ai-toolkit-wrap">
	<h1 class="tr-page-title">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'TR AI Toolkit — Activity Log', 'tr-wordpress-ai-toolkit' ); ?>
	</h1>

	<p class="description">
		<?php esc_html_e( 'Shows the 100 most recent ability invocations. Sensitive input fields are automatically redacted.', 'tr-wordpress-ai-toolkit' ); ?>
	</p>

	<?php if ( ! empty( $logs ) ) : ?>
	<!-- Filter bar -->
	<div class="tr-log-toolbar">
		<div class="tr-log-filters">
			<input
				type="search"
				id="tr-log-search"
				class="regular-text"
				placeholder="<?php esc_attr_e( 'Filter by ability name…', 'tr-wordpress-ai-toolkit' ); ?>"
			>
			<select id="tr-log-status-filter">
				<option value=""><?php esc_html_e( 'All statuses', 'tr-wordpress-ai-toolkit' ); ?></option>
				<option value="success"><?php esc_html_e( 'Success', 'tr-wordpress-ai-toolkit' ); ?></option>
				<option value="denied"><?php esc_html_e( 'Denied', 'tr-wordpress-ai-toolkit' ); ?></option>
				<option value="error"><?php esc_html_e( 'Error', 'tr-wordpress-ai-toolkit' ); ?></option>
			</select>
			<span id="tr-log-count" class="tr-log-count"></span>
		</div>
		<div class="tr-log-actions">
			<button id="tr-clear-logs-btn" class="button tr-clear-logs-btn" type="button">
				<span class="dashicons dashicons-trash" style="vertical-align:middle;margin-right:3px;font-size:14px;width:14px;height:14px;"></span>
				<?php esc_html_e( 'Clear All Logs', 'tr-wordpress-ai-toolkit' ); ?>
			</button>
		</div>
	</div>
	<div id="tr-clear-logs-notice" class="notice inline" style="display:none;margin:8px 0;"></div>
	<?php endif; ?>

	<?php if ( empty( $logs ) ) : ?>
		<p><?php esc_html_e( 'No activity recorded yet. Connect an AI client and invoke an ability to see logs here.', 'tr-wordpress-ai-toolkit' ); ?></p>
	<?php else : ?>
	<table class="widefat tr-table striped" id="tr-log-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time (UTC)', 'tr-wordpress-ai-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Ability', 'tr-wordpress-ai-toolkit' ); ?></th>
				<th><?php esc_html_e( 'User', 'tr-wordpress-ai-toolkit' ); ?></th>
				<th><?php esc_html_e( 'IP', 'tr-wordpress-ai-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Status', 'tr-wordpress-ai-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Message', 'tr-wordpress-ai-toolkit' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $logs as $log ) :
				$user_data = $log->user_id ? get_userdata( (int) $log->user_id ) : false;
				$user_label = $user_data ? esc_html( $user_data->display_name ) : '#' . esc_html( $log->user_id );
				$status_badge = match ( $log->result_status ) {
					'success' => 'tr-badge-success',
					'denied'  => 'tr-badge-warning',
					default   => 'tr-badge-error',
				};
			?>
			<tr data-ability="<?php echo esc_attr( strtolower( $log->ability ) ); ?>" data-status="<?php echo esc_attr( $log->result_status ); ?>">
				<td><?php echo esc_html( $log->log_time ); ?></td>
				<td><code><?php echo esc_html( $log->ability ); ?></code></td>
				<td><?php echo $user_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above ?></td>
				<td><?php echo esc_html( $log->ip_address ); ?></td>
				<td><span class="tr-badge <?php echo esc_attr( $status_badge ); ?>"><?php echo esc_html( $log->result_status ); ?></span></td>
				<td><?php echo esc_html( $log->message ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p id="tr-log-no-results" style="display:none;"><?php esc_html_e( 'No log entries match your filter.', 'tr-wordpress-ai-toolkit' ); ?></p>
	<?php endif; ?>
</div>
