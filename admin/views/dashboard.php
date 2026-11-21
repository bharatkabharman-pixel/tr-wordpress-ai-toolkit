<?php
/**
 * Dashboard view.
 *
 * Available variables (extracted by class-admin.php):
 *   $settings            TR_AI_Toolkit_Settings
 *   $abilities_manager   TR_AI_Toolkit_Abilities_Manager
 *   $logger              TR_AI_Toolkit_Logger
 *   $mcp_adapter_active  bool
 *   $abilities_api_active bool
 *   $recent_logs         array
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

$plugin_enabled = $settings->get( 'general.plugin_enabled', true );
$exposed_modules = (array) $settings->get( 'mcp.exposed_modules', array() );

$status_class   = ( $mcp_adapter_active && $abilities_api_active && $plugin_enabled ) ? 'tr-status-ok' : 'tr-status-warn';
$status_label   = ( $mcp_adapter_active && $abilities_api_active && $plugin_enabled )
	? __( 'Connected', 'tr-wordpress-ai-toolkit' )
	: __( 'Needs Setup', 'tr-wordpress-ai-toolkit' );
?>
<div class="wrap tr-ai-toolkit-wrap">
	<h1 class="tr-page-title">
		<span class="dashicons dashicons-superhero"></span>
		<?php esc_html_e( 'TR WordPress AI Toolkit', 'tr-wordpress-ai-toolkit' ); ?>
		<span class="tr-version">v<?php echo esc_html( TR_AI_TOOLKIT_VERSION ); ?></span>
	</h1>

	<?php if ( ! $plugin_enabled ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'Plugin is currently disabled in Settings. All abilities are inactive.', 'tr-wordpress-ai-toolkit' ); ?></p>
	</div>
	<?php endif; ?>

	<!-- Status Cards -->
	<div class="tr-cards">

		<div class="tr-card tr-card-status <?php echo esc_attr( $status_class ); ?>">
			<div class="tr-card-icon"><span class="dashicons dashicons-cloud-saved"></span></div>
			<div class="tr-card-body">
				<h3><?php esc_html_e( 'MCP Status', 'tr-wordpress-ai-toolkit' ); ?></h3>
				<p class="tr-status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></p>
			</div>
		</div>

		<div class="tr-card">
			<div class="tr-card-icon"><span class="dashicons dashicons-superhero-alt"></span></div>
			<div class="tr-card-body">
				<h3><?php esc_html_e( 'MCP Adapter', 'tr-wordpress-ai-toolkit' ); ?></h3>
				<p>
					<?php if ( $mcp_adapter_active ) : ?>
						<span class="tr-badge tr-badge-success"><?php esc_html_e( 'Active', 'tr-wordpress-ai-toolkit' ); ?></span>
					<?php else : ?>
						<span class="tr-badge tr-badge-error"><?php esc_html_e( 'Not Active', 'tr-wordpress-ai-toolkit' ); ?></span>
						<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=mcp-adapter&tab=search&type=term' ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Install', 'tr-wordpress-ai-toolkit' ); ?>
						</a>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<div class="tr-card">
			<div class="tr-card-icon"><span class="dashicons dashicons-database-view"></span></div>
			<div class="tr-card-body">
				<h3><?php esc_html_e( 'Abilities API', 'tr-wordpress-ai-toolkit' ); ?></h3>
				<p>
					<?php if ( $abilities_api_active ) : ?>
						<span class="tr-badge tr-badge-success"><?php esc_html_e( 'Available (WP 6.9+)', 'tr-wordpress-ai-toolkit' ); ?></span>
					<?php else : ?>
						<span class="tr-badge tr-badge-error"><?php esc_html_e( 'Not Available — Upgrade WordPress', 'tr-wordpress-ai-toolkit' ); ?></span>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<div class="tr-card">
			<div class="tr-card-icon"><span class="dashicons dashicons-layout"></span></div>
			<div class="tr-card-body">
				<h3><?php esc_html_e( 'Active Modules', 'tr-wordpress-ai-toolkit' ); ?></h3>
				<p class="tr-big-number"><?php echo esc_html( count( $exposed_modules ) ); ?> / 8</p>
			</div>
		</div>

	</div>

	<!-- Module Status -->
	<div class="tr-section">
		<h2><?php esc_html_e( 'Module Status', 'tr-wordpress-ai-toolkit' ); ?></h2>
		<table class="widefat tr-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Module', 'tr-wordpress-ai-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Abilities', 'tr-wordpress-ai-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Status', 'tr-wordpress-ai-toolkit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$module_info = array(
					'content'     => array( 'name' => __( 'Content', 'tr-wordpress-ai-toolkit' ),     'count' => 14, 'desc' => __( 'Posts, pages, categories, tags', 'tr-wordpress-ai-toolkit' ) ),
					'media'       => array( 'name' => __( 'Media', 'tr-wordpress-ai-toolkit' ),       'count' => 5,  'desc' => __( 'Media library management', 'tr-wordpress-ai-toolkit' ) ),
					'seo'         => array( 'name' => __( 'SEO', 'tr-wordpress-ai-toolkit' ),         'count' => 5,  'desc' => __( 'SEO analysis and audits', 'tr-wordpress-ai-toolkit' ) ),
					'health'      => array( 'name' => __( 'Health', 'tr-wordpress-ai-toolkit' ),      'count' => 7,  'desc' => __( 'Site health and system info', 'tr-wordpress-ai-toolkit' ) ),
					'users'       => array( 'name' => __( 'Users', 'tr-wordpress-ai-toolkit' ),       'count' => 4,  'desc' => __( 'User listing and roles', 'tr-wordpress-ai-toolkit' ) ),
					'woocommerce' => array( 'name' => __( 'WooCommerce', 'tr-wordpress-ai-toolkit' ), 'count' => 12, 'desc' => __( 'Products, orders, customers, coupons', 'tr-wordpress-ai-toolkit' ) ),
					'comments'    => array( 'name' => __( 'Comments', 'tr-wordpress-ai-toolkit' ),    'count' => 6,  'desc' => __( 'Comment moderation and replies', 'tr-wordpress-ai-toolkit' ) ),
					'cpt'         => array( 'name' => __( 'Custom Post Types', 'tr-wordpress-ai-toolkit' ), 'count' => 5, 'desc' => __( 'Manage any registered CPT', 'tr-wordpress-ai-toolkit' ) ),
				);
				foreach ( $module_info as $slug => $info ) :
					$active = in_array( $slug, $exposed_modules, true );
				?>
				<tr>
					<td><strong><?php echo esc_html( $info['name'] ); ?></strong><br><span class="description"><?php echo esc_html( $info['desc'] ); ?></span></td>
					<td><?php echo esc_html( $info['count'] ); ?></td>
					<td>
						<?php if ( $active ) : ?>
							<span class="tr-badge tr-badge-success"><?php esc_html_e( 'Enabled', 'tr-wordpress-ai-toolkit' ); ?></span>
						<?php else : ?>
							<span class="tr-badge tr-badge-muted"><?php esc_html_e( 'Disabled', 'tr-wordpress-ai-toolkit' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Quick Links -->
	<div class="tr-section tr-quick-links">
		<h2><?php esc_html_e( 'Quick Links', 'tr-wordpress-ai-toolkit' ); ?></h2>
		<div class="tr-cards tr-cards-small">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tr-ai-toolkit-settings' ) ); ?>" class="tr-card tr-card-link">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Settings', 'tr-wordpress-ai-toolkit' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tr-ai-toolkit-connect' ) ); ?>" class="tr-card tr-card-link">
				<span class="dashicons dashicons-share"></span>
				<?php esc_html_e( 'Connection Guide', 'tr-wordpress-ai-toolkit' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tr-ai-toolkit-logs' ) ); ?>" class="tr-card tr-card-link">
				<span class="dashicons dashicons-list-view"></span>
				<?php esc_html_e( 'Activity Log', 'tr-wordpress-ai-toolkit' ); ?>
			</a>
			<a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noopener" class="tr-card tr-card-link">
				<span class="dashicons dashicons-external"></span>
				<?php esc_html_e( 'MCP Adapter Docs', 'tr-wordpress-ai-toolkit' ); ?>
			</a>
		</div>
	</div>

	<!-- Recent Activity -->
	<?php if ( ! empty( $recent_logs ) ) : ?>
	<div class="tr-section">
		<h2><?php esc_html_e( 'Recent Activity', 'tr-wordpress-ai-toolkit' ); ?></h2>
		<table class="widefat tr-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'tr-wordpress-ai-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Ability', 'tr-wordpress-ai-toolkit' ); ?></th>
					<th><?php esc_html_e( 'User', 'tr-wordpress-ai-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Status', 'tr-wordpress-ai-toolkit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent_logs as $log ) : ?>
				<tr>
					<td><?php echo esc_html( $log->log_time ); ?></td>
					<td><code><?php echo esc_html( $log->ability ); ?></code></td>
					<td><?php echo esc_html( $log->user_id ? get_userdata( $log->user_id )->display_name ?? $log->user_id : '—' ); ?></td>
					<td>
						<span class="tr-badge tr-badge-<?php echo esc_attr( 'success' === $log->result_status ? 'success' : ( 'denied' === $log->result_status ? 'warning' : 'error' ) ); ?>">
							<?php echo esc_html( $log->result_status ); ?>
						</span>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=tr-ai-toolkit-logs' ) ); ?>"><?php esc_html_e( 'View full log →', 'tr-wordpress-ai-toolkit' ); ?></a></p>
	</div>
	<?php endif; ?>

</div>
