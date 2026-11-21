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

	<?php if ( empty( $logs ) ) : ?>
		<p><?php esc_html_e( 'No activity recorded yet. Connect an AI client and invoke an ability to see logs here.', 'tr-wordpress-ai-toolkit' ); ?></p>
	<?php else : ?>
	<table class="widefat tr-table striped">
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
			<tr>
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
	<?php endif; ?>
</div>
